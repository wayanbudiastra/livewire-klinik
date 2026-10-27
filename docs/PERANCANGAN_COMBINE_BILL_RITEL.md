# Perancangan: Combine Bill Registrasi + Bill Ritel (Susulan Hasil Lab)

Status: **DRAFT perancangan — belum ada kode yang diubah.**
Tanggal: 2026-10-27

---

## 1. Latar belakang & kebutuhan

Permintaan dokter:

1. Pasien periksa lab dan diberi obat pada hari kunjungan. Hasil lab belum selesai di hari yang sama, sehingga **bill kunjungan harus di-close (lunas) hari itu juga**.
2. Setelah hasil lab keluar, dokter dapat menyarankan **tambahan pemeriksaan lab dan/atau obat** tanpa harus membuat SOAP baru (alur ritel).
3. Permintaan asuransi: **bill pasien harus dijadikan satu** (bill kunjungan + tambahan susulan).
4. Combine **hanya berlaku untuk transaksi yang punya ID pasien** (`pasien_id` terisi).

---

## 2. Kondisi sistem saat ini (hasil pembacaan kode)

| Hal | Kondisi saat ini | Sumber |
|---|---|---|
| Relasi billing–kunjungan | 1 kunjungan = 1 billing (`kunjungan_id` **unique**) | `database/migrations/2026_01_01_000017_create_billing_table.php` |
| Status billing | `belum_bayar`, `sebagian`, `lunas`, `dibatalkan` | idem |
| Billing `lunas` | Tidak boleh disentuh ulang oleh `createOrRefresh()`; pelunasan sudah memposting jurnal + sharing fee | `app/Services/InvoiceService.php`, `app/Services/Kasir/BillingService.php` |
| Item billing | `jenis`: tindakan, alkes, penunjang, obat, racikan, manual | `invoice_item` |
| Lab/penunjang di billing | Hanya `permintaan_penunjang` berstatus **`selesai`** yang masuk `buildItems()` | `InvoiceService::buildItems()` |
| Transaksi ritel | Punya `pasien_id` (opsional), alur sendiri: draft → menunggu_kasir → dibayar → selesai | `app/Models/TransaksiRitel.php`, `ObatRitelService` |
| Item ritel | **Hanya barang** (`barang_id`), tidak bisa item penunjang/lab | `transaksi_ritel_item` |
| Hubungan ritel ↔ billing/kunjungan | **Tidak ada** (tidak ada `kunjungan_id`/`billing_id` di ritel) | `TransaksiRitel` |
| Jurnal ritel | Sendiri: `catatPenjualan` saat dibayar, `catatHpp` saat obat diserahkan | `RitelJurnalService` |
| Rekap kas | `SesiKasService` menjumlahkan billing + ritel per sesi (hanya penjumlahan laporan) | `SesiKasService.php:77-113` |
| Asuransi | `CoverCalculatorService::hitungCover(Invoice, Asuransi)` menghitung cover dari **satu Invoice**; `PenagihanService::buatPenagihan` bisa menggabungkan **banyak piutang** dalam satu penagihan | `app/Services/Asuransi/*` |

Kesimpulan: **tidak ada fitur combine.** Dua hambatan utama: (a) billing lunas tidak boleh diubah, (b) ritel tidak punya relasi ke kunjungan dan tidak bisa memuat item lab.

---

## 3. Pilihan pendekatan

### Opsi A — Buka kembali billing yang sudah lunas (reopen)
Billing lunas dibuka lagi, item susulan ditambahkan, dibayar ulang.
- ➕ Satu invoice sungguhan.
- ➖ Merusak integritas akuntansi: jurnal pelunasan, sharing fee dokter/perawat, rekap sesi kas yang sudah tutup, dan piutang asuransi sudah terbentuk. Perlu reversal berantai. **Berisiko tinggi, tidak disarankan.**

### Opsi B — Bill susulan terhubung + dokumen gabungan  ✅ **Direkomendasikan**
Billing lunas **tidak diubah**. Tambahan susulan tetap berupa transaksi ritel (alur, stok, dan jurnalnya sudah ada), tetapi **ditautkan ke kunjungan/billing asal**. "Satu bill" diwujudkan sebagai **Tagihan Gabungan**: tampilan, cetak, dan klaim asuransi yang menggabungkan billing asal + semua susulan.
- ➕ Jurnal, stok, sesi kas tetap benar tanpa reversal.
- ➕ Perubahan terlokalisasi; memakai alur ritel yang sudah teruji.
- ➖ Secara data tetap dua dokumen pembayaran (tapi satu dokumen gabungan untuk pasien/asuransi).

### Opsi C — Hanya tampilan gabungan (tanpa relasi)
Kasir memilih manual dua tagihan untuk dicetak bersama. Tidak ada relasi data.
- ➖ Tidak memenuhi syarat asuransi dan rawan salah pasangkan. Tidak disarankan.

**Rancangan di bawah memakai Opsi B.**

---

## 4. Rancangan Opsi B

### 4.1 Alur bisnis

```
Hari-1  Kunjungan → SOAP → order lab + resep → Billing LUNAS (close)
                                  │
Hari-N  Hasil lab keluar → Dokter buka riwayat pasien
        → "Order Susulan" (tanpa SOAP): tambah lab / obat
        → Ritel Susulan (draft) ditautkan ke kunjungan asal
        → Submit ke kasir → bayar (alur ritel biasa) → obat diserahkan
                                  │
        Tagihan Gabungan (billing asal + ritel susulan) → cetak / klaim asuransi
```

### 4.2 Aturan bisnis

1. Combine **hanya** jika `transaksi_ritel.pasien_id` terisi. Pembeli umum tanpa ID pasien tidak bisa ditautkan.
2. `kunjungan_id` yang ditautkan harus milik pasien yang sama dengan `pasien_id` ritel (divalidasi di service, bukan hanya di UI).
3. Billing asal harus berstatus `lunas` (kalau masih `belum_bayar`/`sebagian`, tambahan cukup masuk ke billing itu lewat alur normal).
4. Billing asal tidak diubah sama sekali.
5. Batas waktu susulan **14 hari** sejak tanggal kunjungan (**sudah dikonfirmasi klinik**), disimpan sebagai config agar mudah diubah.
6. Satu kunjungan boleh punya **banyak** ritel susulan.
7. Ritel susulan yang dibatalkan tidak ikut dihitung di tagihan gabungan.
8. Hanya role dengan permission baru (usulan `ritel.susulan` untuk dokter, `billing.gabung` untuk kasir) yang bisa membuat/melihat.
9. Semua aksi dicatat di audit (pola `AuditKasirService` yang sudah ada).

### 4.2a Perubahan keputusan: registrasi utama TIDAK wajib (perlu konfirmasi arti)

Klinik menyatakan: **"tidak harus ada registrasi utama"**. Tafsiran saya: transaksi susulan (lab/obat) untuk pasien ber-ID **boleh dibuat tanpa kunjungan asal**; penautan ke kunjungan bersifat **opsional**. Syarat tetap: `pasien_id` wajib terisi.

Dampak terhadap rancangan di atas (jika tafsiran benar):

| Hal | Sebelumnya | Sekarang |
|---|---|---|
| `transaksi_ritel.kunjungan_id` / `billing_id` | Wajib untuk susulan | **Opsional**; kosong = susulan mandiri milik pasien |
| Aturan billing asal harus `lunas` & batas 14 hari | Selalu berlaku | **Hanya jika ditautkan** ke kunjungan |
| "Tagihan Gabungan" | Selalu billing asal + susulan | Jika ditautkan: gabungan seperti rancangan. Jika mandiri: dokumen per pasien (daftar susulan pasien tsb), tidak ada billing asal |
| Lab susulan → `permintaan_penunjang` | Dibuat untuk kunjungan asal | **Terhalang:** `permintaan_penunjang.kunjungan_id` **NOT NULL** (FK ke `kunjungan`, terverifikasi di database). Lab susulan mandiri tidak punya kunjungan |
| Sharing fee | Penerima = `dokter_id` penyaran | Tidak berubah (tidak bergantung kunjungan) |
| Asuransi | Mengikuti data asuransi kunjungan (`pasien_asuransi_id`, `tipe_pembayaran`) | **Belum jelas:** tanpa kunjungan, data asuransi diambil dari kartu asuransi pasien; perlu diputuskan |

Keputusan yang dibutuhkan (lihat pertanyaan 10-12 di bagian 7).

---

### 4.3 Perubahan data (usulan)

**Tabel `transaksi_ritel`** — tambah kolom:

| Kolom | Tipe | Keterangan |
|---|---|---|
| `kunjungan_id` | FK nullable → kunjungan | Kunjungan asal yang ditautkan |
| `billing_id` | FK nullable → billing | Billing asal (untuk query cepat) |
| `dokter_id` | FK nullable → dokter/users | Dokter yang menyarankan susulan |
| `tipe` | enum(`ritel`,`susulan`) default `ritel` | Pembeda alur biasa vs susulan |

Constraint di service: `kunjungan_id` terisi ⇒ `pasien_id` wajib terisi dan cocok.

**Lab dan obat susulan dipisah** (keputusan klinik): satu transaksi ritel susulan hanya berisi **lab saja** atau **obat saja**, tidak dicampur. Jika dokter menyarankan lab dan obat sekaligus, sistem membuat **dua transaksi** yang sama-sama ditautkan ke kunjungan asal. Karena itu `transaksi_ritel` mendapat kolom pembeda, dan `transaksi_ritel_item` menyesuaikan:

| Kolom | Tabel | Keterangan |
|---|---|---|
| `jenis_susulan` | `transaksi_ritel` | enum(`obat`,`lab`), terisi jika `tipe=susulan` |
| `item_penunjang_id` | `transaksi_ritel_item` | FK nullable → item_penunjang; terisi untuk transaksi `lab` |
| `barang_id` | `transaksi_ritel_item` | jadi **nullable**; kosong untuk item lab |

Stok dan HPP hanya diproses untuk transaksi `obat`. Validasi di service: transaksi `lab` hanya boleh berisi item penunjang, transaksi `obat` hanya barang.

**Lab susulan:** saat ritel susulan dibayar, sistem membuat `permintaan_penunjang` untuk kunjungan asal agar lab tetap menerima order di alur penunjang yang sudah ada.

**Tagihan gabungan** tidak butuh tabel baru di tahap awal: dihitung dari `billing` + `transaksi_ritel` yang `kunjungan_id`-nya sama dan `status` ≠ `dibatalkan`. Nomor dokumen gabungan bisa memakai nomor invoice asal + sufiks (contoh: `INV-…-G`).

### 4.4 Komponen kode yang disentuh

| Komponen | Perubahan |
|---|---|
| Migrasi baru | Kolom di atas (kompatibel MySQL production). |
| `TransaksiRitel` / `TransaksiRitelItem` | Relasi `kunjungan()`, `billing()`, `dokter()`, scope `susulan()`. |
| `ObatRitelService` | Validasi pasien/kunjungan/billing lunas; dukung item `penunjang`; lewati stok & HPP untuk lab; buat `permintaan_penunjang`. |
| Livewire Dokter (baru) | Form "Order Susulan" di riwayat pasien: pilih kunjungan asal, tambah lab/obat, tanpa SOAP. |
| Livewire Kasir | Halaman **Tagihan Gabungan** (ringkasan billing asal + susulan, status bayar tiap bagian). |
| `CetakInvoiceService` | Cetak invoice gabungan (satu dokumen, dua bagian). |
| `SesiKasService` | Tidak diubah hitungannya; pastikan tampilan gabungan **tidak menjumlah dua kali**. |
| Permission & seeder | Permission baru + penempatan ke role. |

### 4.5 Dampak akuntansi

- Billing asal: **tidak berubah** (jurnal pelunasan & sharing fee tetap).
- Ritel susulan: memakai jurnal ritel yang ada (`catatPenjualan` + `catatHpp`).
- Karena lab dipisah dari obat, **pendapatan lab susulan masuk akun pendapatan penunjang/lab sendiri**, tidak ke akun pendapatan obat. Saat ini `RitelJurnalService::catatPenjualan` memposting seluruh `total_bayar` ke satu akun (`AKUN_PENDAPATAN_OBAT`), jadi jurnal ritel harus dibuat sadar-jenis: `obat` → akun obat (+ HPP), `lab` → akun lab (tanpa HPP barang). **Kode akun lab perlu ditentukan bagian keuangan** (periksa `ChartOfAccountSeeder`).
- **Sharing fee dokter tetap diberikan** untuk susulan (keputusan klinik). Keadaan kode saat ini:
  - `SharingFeeService` v1 hanya menghitung kategori **`tindakan`** pada billing; **lab/radiologi belum dihitung sama sekali**, bahkan untuk billing biasa (lihat komentar di kelas itu), karena belum ada kategorisasi `ItemPenunjang`.
  - **Keputusan klinik: fee lab susulan = "sama dengan sharing fee utama".** Setelah dicek ulang, kalimat ini bisa berarti dua hal, dan **datanya berbeda** (di database lokal: `tindakan` 15%, `lab` 10%):
    - **(i) Persentase `tindakan` dokter** (fee utama, yang dihitung sekarang di billing); atau
    - **(ii) Persentase `lab` dokter** — kategori ini **sudah ada dan bisa diatur** di SharingFeeForm (`fee_lab`), tetapi **belum dipakai** oleh `SharingFeeService` mana pun.
    - Saya sebelumnya mengasumsikan (i). **Perlu konfirmasi klinik** mana yang dimaksud (pertanyaan 8).
  - Dasar hitung mengikuti fee tindakan: jumlah `subtotal` item (sudah net diskon item) × persentase dokter. Untuk transaksi lab susulan, dasarnya adalah subtotal item lab di transaksi itu.
  - Penerima = `dokter_id` yang menyarankan di transaksi susulan (bukan otomatis dokter kunjungan asal). Kalau dokter itu tidak punya baris sharing fee untuk kategori yang dipakai, atau persentasenya 0, **tidak ada fee** (perilaku sama dengan billing sekarang).
  - Yang tetap baru: fungsi hitung fee untuk `TransaksiRitel` (bukan `Invoice`), karena `SharingFeeService` sekarang hanya menerima `Invoice`.
  - Perlu pola yang sama dengan fee yang ada: jurnal `sharing_fee_dokter` dengan sumber `transaksi_ritel`, dicatat saat transaksi lunas, dan **reversal** saat dibatalkan.
  - **Obat susulan: tidak ada setup sharing fee.** Tabel `sharing_fee` hanya mengenal kategori `tindakan`, `lab`, `radiologi`, `peralatan` (enum di `2026_01_03_000003_create_sharing_fee_table.php`), dan SharingFeeForm hanya menyediakan kategori itu. Tidak ada kategori `obat`, baik di tabel, form, maupun layanan fee. Jika klinik ingin dokter mendapat fee dari obat susulan, perlu **kategori baru `obat`** (migrasi mengubah enum — harus aman untuk MySQL dan SQLite), kolom di form, dan perhitungan fee. **Default perancangan: obat susulan tanpa fee.**

### 4.6 Dampak asuransi

Kebutuhan: asuransi menerima **satu bill**.

- `PenagihanService::buatPenagihan(asuransiId, piutangIds, …)` sudah bisa menggabungkan banyak piutang dalam satu penagihan, sehingga **satu surat penagihan** per kunjungan secara teknis memungkinkan.
- `CoverCalculatorService::hitungCover` saat ini menerima **satu `Invoice`**. Perlu diperluas agar bisa menghitung cover untuk item ritel susulan (obat/penunjang) memakai aturan cover yang sama, atau menerima kumpulan item.
- Ritel perlu menyimpan `asuransi_id`, `total_cover_asuransi`, `total_tanggungan_pasien` (seperti kolom di `billing`), dan membentuk piutang asuransi sendiri.
- **Belum saya verifikasi di kode:** detail `PembayaranAsuransiService::prosesPembayaranAsuransi` dan cara piutang dibentuk dari billing. Ini harus dibaca ulang tepat sebelum implementasi tahap asuransi.

---

## 5. Tahapan implementasi (usulan)

| Tahap | Isi | Catatan |
|---|---|---|
| 0 | Pastikan/terapkan penagihan lab hari H: order lab SOAP berstatus `selesai` + masuk bill (bagian 7a) | Prasyarat; cek dulu apakah ada di versi production |
| 1 | Migrasi + model + relasi + validasi service | Fondasi, belum terlihat di UI |
| 2 | Transaksi ritel susulan jenis `lab` (item penunjang) + pembuatan `permintaan_penunjang` + jurnal ke akun lab | Lab terpisah dari obat |
| 2b | Sharing fee dokter untuk lab susulan (persentase sesuai pertanyaan 8; jurnal + reversal) | Butuh jawaban pertanyaan 8; kode akun (9) **pending** |
| 3 | UI dokter "Order Susulan" + permission | Alur utama permintaan dokter |
| 4 | Halaman & cetak **Tagihan Gabungan** (kasir) | Kebutuhan "satu bill" |
| 5 | Asuransi: cover ritel susulan + penagihan satu surat | Setelah baca ulang service asuransi |
| 6 | Laporan (kasir/keuangan) & audit trail | Hindari hitung ganda |
| 7 | Tes otomatis (lihat bagian 6) | Berjalan di setiap tahap |

---

## 6. Rencana pengujian

- Ritel dengan `pasien_id` kosong ⇒ ditolak untuk ditautkan.
- Kunjungan milik pasien lain ⇒ ditolak.
- Billing asal belum lunas ⇒ ditolak (arahkan ke alur normal).
- Melewati batas hari susulan ⇒ ditolak.
- Dokter menyarankan lab + obat: terbentuk **dua** transaksi terpisah; stok hanya berkurang untuk transaksi obat; `permintaan_penunjang` terbentuk untuk transaksi lab.
- Transaksi susulan yang mencampur lab dan obat ditolak.
- Jurnal: lab susulan masuk akun pendapatan lab, obat ke akun obat + HPP.
- Sharing fee: terbentuk untuk `dokter_id` penyaran dengan persentase yang diputuskan (pertanyaan 8) saat lunas; nominal = subtotal lab × persentase; di-reversal saat dibatalkan; tidak terbentuk dua kali; tidak terbentuk jika persentase 0 / baris tidak ada; obat susulan tidak menghasilkan fee (kecuali kategori `obat` ditambahkan).
- Billing asal tidak berubah (total, status, jurnal) setelah susulan dibayar.
- Tagihan gabungan: total = billing asal + susulan non-batal; tidak ada hitung ganda di rekap sesi kas.
- Pembatalan susulan mengeluarkannya dari gabungan.
- Asuransi: satu penagihan memuat piutang billing asal + susulan.
- Hak akses: dokter bisa buat, kasir bisa gabung, role lain ditolak.
- Dijalankan di **MySQL** (sama dengan production), bukan hanya SQLite.

---

## 7. Pertanyaan terbuka (perlu keputusan klinik)

1. ~~**Lab di billing asal.**~~ **Dijawab klinik:** bill harus lunas di hari yang sama, tidak ada lab H-1; lab rawat jalan diinput di hari H. **Temuan baru, perlu keputusan lihat bagian 7a.**
2. ~~**Batas waktu susulan**~~ **Dijawab klinik: 14 hari.**
3. ~~**Sharing fee dokter** untuk susulan~~ **Dijawab klinik: dokter tetap mendapat sharing fee.** Rincian aturannya (persentase, dasar hitung) lihat pertanyaan 8.
4. ~~**Akun pendapatan** lab susulan~~ **Dijawab klinik: lab ritel dipisah dari obat** (transaksi dan akun terpisah). Kode akun lab tetap perlu ditentukan keuangan (pertanyaan 9).
5. **Pembayaran susulan** — selalu bayar sendiri oleh pasien, atau bisa langsung ditanggung asuransi (cover)?
6. **Retur/pembatalan** setelah obat diserahkan — memakai alur retur yang ada?
7. **Siapa yang boleh membuat order susulan** — dokter saja, atau juga perawat/apoteker atas arahan dokter?
8. **Persentase sharing fee lab susulan** — klinik menjawab "sama dengan sharing fee utama". Yang dimaksud persentase **`tindakan`** dokter (opsi i) atau persentase **`lab`** yang sudah ada di pengaturan dokter (opsi ii)? Keduanya bisa berbeda nilainya. **Obat susulan:** sudah dicek, **tidak ada setup sharing fee obat** (lihat 4.5); defaultnya tanpa fee, kecuali klinik meminta kategori `obat` baru.
10. **Arti "tidak harus ada registrasi utama"** — benar bahwa susulan boleh dibuat **tanpa kunjungan asal** (cukup `pasien_id`)? Atau maksudnya hal lain (mis. susulan tidak harus lewat SOAP)?
11. **Lab susulan tanpa kunjungan** — `permintaan_penunjang.kunjungan_id` wajib terisi. Pilihan: (a) buat kolom itu **nullable** + simpan `pasien_id` di permintaan; atau (b) lab susulan mandiri **tidak membuat** `permintaan_penunjang` (hanya tercatat di transaksi ritel). Karena lab dikerjakan pihak ketiga, (b) lebih sederhana; (a) lebih rapi bila hasil lab perlu dilacak di sistem.
12. **Asuransi tanpa kunjungan** — sumber data asuransi untuk susulan mandiri (kartu asuransi pasien?), dan apakah susulan mandiri boleh ditanggung asuransi.
9. **Akun pendapatan lab & beban sharing fee** — **DITUNDA (pending) atas keputusan pemilik.** Kode akun lab dan akun beban belum ditentukan; hanya memblokir pemostingan jurnal lab susulan dan sharing fee (Tahap 2 bagian jurnal dan Tahap 2b). Tahap 0, 1, 3, 4 tidak bergantung padanya.

---

## 7a. Penagihan lab hari H (SUDAH DIVERIFIKASI: permintaan lama belum terimplementasi di kode ini)

**Kebutuhan klinik (permintaan sebelumnya):** order lab yang diinput dokter saat SOAP **langsung berstatus `selesai`** saat disimpan, dan harga lab **masuk ke bill pasien**. Lab dikerjakan **pihak ketiga** di luar klinik; pasien membayar di hari H, hasil bisa belum jadi di hari H.

**Kondisi kode (repo ini, `main` = `origin/main`, 260 commit):**

- `InvoiceService::buildItems()` hanya menagih `permintaan_penunjang` berstatus `selesai` (`app/Services/InvoiceService.php`, ~baris 67-69).
- Order lab dibuat berstatus **`dipesan`** (`app/Livewire/Pemeriksaan/Penunjang.php:138,200`; `MasterdataService::buatPermintaan`, baris 462), dan tidak ada kode yang mengubahnya ke `selesai`. `batalkan()` hanya mengizinkan pembatalan saat `dipesan`.
- Saya mencari di kode, di riwayat git (judul commit terkait lab/penunjang/bill), dan di dokumen `prd/`: **tidak ada implementasi permintaan itu**.
- **Terbukti dengan uji (2026-10-27, MySQL lokal, data uji di-rollback):** order lab "Darah Lengkap" (Rp85.000) berstatus `dipesan` (seperti hasil `submitLab()`) → `InvoiceService::createOrRefresh()` menghasilkan **0 item, total tagihan Rp0**. Setelah status diubah ke `selesai` → 1 item, total Rp85.000. Jadi lab dari SOAP saat ini **tidak masuk bill**. Kemungkinan lain: perubahan itu ada di versi/branch lain (mis. server production) yang tidak ada di repo ini — **perlu dicek klinik/pengembang**.

**Keputusan perancangan (selaras dengan kebutuhan klinik):**

- Order lab (SOAP maupun susulan) dianggap **`selesai` untuk keperluan penagihan** saat disimpan; yang menunggu hanyalah hasil dari pihak ketiga. Pemisahan "tertagih" dan "hasil sudah ada" sebaiknya tidak memakai satu kolom `status`. Usulan: tetap pakai `status = 'selesai'` untuk penagihan (sesuai permintaan awal) dan tambahkan penanda hasil terpisah, mis. `hasil_diterima_at`/`hasil_url` (kolom `hasil_url` sudah ada di tabel).
- Konsekuensi: pembatalan lab hanya boleh sebelum bill lunas, dan memicu perhitungan ulang tagihan (aturan `batalkan()` saat ini bergantung pada status `dipesan` dan perlu disesuaikan).
- **Prasyarat (Tahap 0):** terapkan perubahan ini lebih dulu. Lab susulan memakai aturan yang sama: dianggap tertagih saat transaksi susulan dibayar.
- Perubahan ini menyentuh penagihan yang sudah berjalan, jadi perlu tes khusus dan persetujuan klinik.

**Cara membuktikan temuan ini:** buat kunjungan, order satu lab lewat SOAP, buka tagihan di kasir, lihat apakah item lab muncul.

---

## 8. Risiko

| Risiko | Mitigasi |
|---|---|
| Hitung ganda di laporan kas/keuangan | Tagihan gabungan hanya tampilan; rekap tetap per transaksi; ada tes khusus |
| Ritel ditautkan ke kunjungan pasien salah | Validasi di service (pasien harus cocok), bukan hanya di UI |
| Lab hari H tidak tertagih (bagian 7a) | Verifikasi di alur nyata & di production; terapkan dulu sebelum fitur combine |
| Migrasi memakai sintaks MySQL-only | Uji di MySQL; hindari `MODIFY COLUMN ... ENUM` pada migrasi baru |
| Klaim asuransi ditolak karena dokumen terpisah | Cetak & penagihan gabungan (Tahap 4–5) |
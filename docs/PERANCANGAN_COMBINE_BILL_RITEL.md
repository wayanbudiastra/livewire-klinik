# Perancangan: Combine Bill Registrasi + Bill Ritel (Susulan Hasil Lab)

Status: **Tahap 0–4 + sharing fee/jurnal lab SUDAH DIIMPLEMENTASI** (lihat bagian "Status implementasi" di bawah). Dokumen ini tetap menjadi acuan aturan bisnis.
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
Hari-N  Hasil lab keluar → Dokter menyarankan tambahan lab / obat
        → KASIR membuat "Order Susulan" (tanpa SOAP) atas saran dokter:
          pilih kunjungan asal, pilih dokter penyaran, tambah lab / obat
        → Ritel Susulan (draft) ditautkan ke kunjungan asal
        → Submit ke kasir → bayar (alur ritel biasa) → obat diserahkan
                                  │
        Tagihan Gabungan (billing asal + ritel susulan) → cetak / klaim asuransi
```

### 4.2 Aturan bisnis

1. Combine **hanya** jika `transaksi_ritel.pasien_id` terisi. Pembeli umum tanpa ID pasien tidak bisa ditautkan.
2. `kunjungan_id` yang ditautkan harus milik pasien yang sama dengan `pasien_id` ritel (divalidasi di service, bukan hanya di UI).
3. **Susulan wajib punya kunjungan asal** (`kunjungan_id` terisi). Billing asal harus berstatus `lunas` (kalau masih `belum_bayar`/`sebagian`, tambahan cukup masuk ke billing itu lewat alur normal).
4. Billing asal tidak diubah sama sekali.
5. Batas waktu susulan **14 hari** sejak tanggal kunjungan (**sudah dikonfirmasi klinik**), disimpan sebagai config agar mudah diubah.
6. Satu kunjungan boleh punya **banyak** ritel susulan.
7. Ritel susulan yang dibatalkan tidak ikut dihitung di tagihan gabungan.
8. **Yang membuat order susulan adalah kasir** (keputusan klinik), atas saran dokter. Permission baru `ritel.susulan` (membuat) dan `billing.gabung` (melihat/cetak tagihan gabungan) diberikan ke role kasir. Kasir **wajib memilih dokter penyaran** (`dokter_id`) karena menentukan penerima sharing fee lab.
8a. **Tidak ada retur** untuk susulan (keputusan klinik). Susulan hanya bisa **dibatalkan sebelum dibayar** (status `draft`/`menunggu_kasir`, sesuai `TransaksiRitel::bisaDibatalkan()`); setelah `dibayar` tidak bisa dibatalkan maupun diretur lewat fitur ini.
9. Semua aksi dicatat di audit (pola `AuditKasirService` yang sudah ada).

### 4.2a Keputusan final: susulan WAJIB punya registrasi utama

Klinik menegaskan: **susulan tidak boleh dibuat tanpa registrasi utama.** Artinya setiap transaksi susulan **wajib ditautkan ke kunjungan asal** (`kunjungan_id` terisi) milik pasien yang sama (`pasien_id` terisi). Aturan billing asal `lunas` dan batas 14 hari berlaku selalu. (Catatan: pada draf sebelumnya saya sempat menafsirkan sebaliknya; tafsiran itu dibatalkan.)

Konsekuensi data: di tingkat database kolom `transaksi_ritel.kunjungan_id` tetap **nullable** karena ritel biasa (non-susulan) tidak punya kunjungan; kewajiban untuk `tipe = 'susulan'` ditegakkan di **service** (dan idealnya constraint/validasi tes).

**Lab susulan tidak membuat `permintaan_penunjang`** (keputusan klinik, pilihan b): lab dikerjakan pihak ketiga, jadi cukup tercatat sebagai item di transaksi ritel susulan. Kolom `permintaan_penunjang.kunjungan_id` tidak perlu diubah.

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

**Lab susulan:** hanya tercatat sebagai item di transaksi ritel susulan; **tidak** membuat `permintaan_penunjang` (lihat 4.2a).

**Tagihan gabungan** tidak butuh tabel baru di tahap awal: dihitung dari `billing` + `transaksi_ritel` yang `kunjungan_id`-nya sama dan `status` ≠ `dibatalkan`. Nomor dokumen gabungan bisa memakai nomor invoice asal + sufiks (contoh: `INV-…-G`).

### 4.4 Komponen kode yang disentuh

| Komponen | Perubahan |
|---|---|
| Migrasi baru | Kolom di atas (kompatibel MySQL production). |
| `TransaksiRitel` / `TransaksiRitelItem` | Relasi `kunjungan()`, `billing()`, `dokter()`, scope `susulan()`. |
| `ObatRitelService` | Validasi pasien/kunjungan/billing lunas/14 hari; dukung transaksi lab (item penunjang); lewati stok & HPP untuk lab; **tidak** membuat `permintaan_penunjang`. |
| Livewire Kasir (baru) | Form "Order Susulan": cari pasien, pilih kunjungan asal (dalam 14 hari, billing lunas), pilih dokter penyaran, tambah lab **atau** obat (dipisah), tanpa SOAP. |
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
    - **Diputuskan: opsi (ii), persentase `lab`** (pertanyaan 8).
  - Dasar hitung mengikuti fee tindakan: jumlah `subtotal` item (sudah net diskon item) × persentase dokter. Untuk transaksi lab susulan, dasarnya adalah subtotal item lab di transaksi itu.
  - Penerima = `dokter_id` yang menyarankan di transaksi susulan (bukan otomatis dokter kunjungan asal). Kalau dokter itu tidak punya baris sharing fee untuk kategori yang dipakai, atau persentasenya 0, **tidak ada fee** (perilaku sama dengan billing sekarang).
  - Yang tetap baru: fungsi hitung fee untuk `TransaksiRitel` (bukan `Invoice`), karena `SharingFeeService` sekarang hanya menerima `Invoice`.
  - Perlu pola yang sama dengan fee yang ada: jurnal `sharing_fee_dokter` dengan sumber `transaksi_ritel`, dicatat saat transaksi lunas, dan **reversal** saat dibatalkan.
  - **Obat susulan: tidak ada setup sharing fee (dikonfirmasi klinik: obat susulan tanpa fee).** Tabel `sharing_fee` hanya mengenal kategori `tindakan`, `lab`, `radiologi`, `peralatan` (enum di `2026_01_03_000003_create_sharing_fee_table.php`), dan SharingFeeForm hanya menyediakan kategori itu. Tidak ada kategori `obat`, baik di tabel, form, maupun layanan fee. Jika klinik ingin dokter mendapat fee dari obat susulan, perlu **kategori baru `obat`** (migrasi mengubah enum — harus aman untuk MySQL dan SQLite), kolom di form, dan perhitungan fee. **Default perancangan: obat susulan tanpa fee.**

### 4.6 Dampak asuransi (disederhanakan: pembayaran reimburse)

Keputusan klinik: asuransi meminta bill **dijadikan satu**, dan pembayarannya **reimburse** — pasien **membayar sendiri lebih dulu**, lalu mengklaim ke asuransi.

- Susulan **dibayar penuh oleh pasien** lewat alur ritel biasa; **tidak ada** perhitungan cover, **tidak ada** piutang asuransi, dan **tidak ada** penagihan ke asuransi untuk susulan.
- Tidak perlu mengubah `CoverCalculatorService`, `PembayaranAsuransiService`, atau `PenagihanService`.
- **Tagihan Gabungan** berfungsi sebagai **dokumen bukti klaim reimburse** yang diserahkan pasien ke asuransi: satu dokumen berisi billing asal + semua susulan non-batal, dengan rincian pembayaran tiap bagian.
- **Dijawab klinik:** billing asal **dibayar cash** oleh pasien, sehingga **tidak masuk piutang**. Sistem hanya **mencetak bill**; proses reimburse adalah urusan pasien langsung dengan asuransi. Tagihan Gabungan tidak mengubah billing/jurnal apa pun.

---

## 5. Tahapan implementasi (usulan)

| Tahap | Isi | Catatan |
|---|---|---|
| 0 | Pastikan/terapkan penagihan lab hari H: order lab SOAP berstatus `selesai` + masuk bill (bagian 7a) | Prasyarat; cek dulu apakah ada di versi production |
| 1 | Migrasi + model + relasi + validasi service | Fondasi, belum terlihat di UI |
| 2 | Transaksi ritel susulan jenis `lab` (item penunjang) + pembuatan `permintaan_penunjang` + jurnal ke akun lab | Lab terpisah dari obat |
| 2b | Sharing fee dokter untuk lab susulan (persentase `lab` dokter; jurnal 5-1200 / 2-1200; tanpa reversal karena tidak ada retur) | Siap dikerjakan |
| 3 | UI kasir "Order Susulan" + permission (`ritel.susulan` ke role kasir) | Alur utama; dokter hanya menyarankan |
| 4 | Halaman & cetak **Tagihan Gabungan** (kasir) | Kebutuhan "satu bill" |
| 5 | (Dibatalkan) Tidak ada perubahan asuransi: susulan reimburse, tanpa cover/piutang. Dokumen gabungan = bukti klaim | Disederhanakan oleh keputusan klinik |
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
- Sharing fee: terbentuk untuk `dokter_id` penyaran dengan persentase **`lab`** dokter itu saat transaksi lab susulan lunas; nominal = subtotal lab × persentase; tidak terbentuk dua kali; tidak terbentuk jika persentase 0 / baris tidak ada; **obat susulan tidak menghasilkan fee**. Karena susulan tidak bisa dibatalkan/diretur setelah dibayar, reversal fee untuk susulan tidak diperlukan.
- Billing asal tidak berubah (total, status, jurnal) setelah susulan dibayar.
- Tagihan gabungan: total = billing asal + susulan non-batal; tidak ada hitung ganda di rekap sesi kas.
- Pembatalan susulan mengeluarkannya dari gabungan.
- Susulan tanpa `kunjungan_id` ditolak; kunjungan milik pasien lain ditolak.
- Lab susulan tidak membuat `permintaan_penunjang`.
- Susulan tidak membentuk piutang asuransi; tagihan gabungan memuat billing asal + susulan sebagai dokumen klaim reimburse.
- Hak akses: **kasir** bisa membuat order susulan dan melihat tagihan gabungan; role lain ditolak.
- Susulan yang sudah `dibayar` tidak bisa dibatalkan/diretur; yang `draft`/`menunggu_kasir` bisa dibatalkan.
- Membuat susulan tanpa memilih `dokter_id` ditolak.
- Dijalankan di **MySQL** (sama dengan production), bukan hanya SQLite.

---

## 7. Pertanyaan terbuka (perlu keputusan klinik)

1. ~~**Lab di billing asal.**~~ **Dijawab klinik:** bill harus lunas di hari yang sama, tidak ada lab H-1; lab rawat jalan diinput di hari H. **Temuan baru, perlu keputusan lihat bagian 7a.**
2. ~~**Batas waktu susulan**~~ **Dijawab klinik: 14 hari.**
3. ~~**Sharing fee dokter** untuk susulan~~ **Dijawab klinik: dokter tetap mendapat sharing fee.** Rincian aturannya (persentase, dasar hitung) lihat pertanyaan 8.
4. ~~**Akun pendapatan** lab susulan~~ **Dijawab klinik: lab ritel dipisah dari obat** (transaksi dan akun terpisah). Kode akun lab tetap perlu ditentukan keuangan (pertanyaan 9).
5. ~~**Pembayaran susulan**~~ **Dijawab klinik: reimburse** — pasien bayar sendiri, lalu klaim ke asuransi; tanpa cover/piutang (lihat 4.6).
6. ~~**Retur/pembatalan**~~ **Dijawab klinik: tidak ada retur** untuk susulan (lihat aturan 8a).
7. ~~**Siapa yang membuat order susulan**~~ **Dijawab klinik: kasir** (lihat aturan 8).
8. ~~**Persentase sharing fee lab susulan**~~ **Dijawab klinik: ya, mengikuti saran** — memakai persentase **`lab`** dokter (kategori yang sudah ada di SharingFeeForm); **obat susulan tanpa fee**.
10. ~~**Registrasi utama**~~ **Dijawab klinik: susulan TIDAK boleh tanpa registrasi utama** (kunjungan asal wajib).
11. ~~**Lab susulan dan `permintaan_penunjang`**~~ **Dijawab klinik: pilihan (b)** — lab susulan tidak membuat `permintaan_penunjang`.
12. ~~**Asuransi untuk susulan**~~ **Dijawab klinik: reimburse** (lihat 5 dan 4.6).
9. ~~**Akun pendapatan lab & beban sharing fee**~~ **Dijawab klinik: ya** — dipakai akun yang **sudah ada** di `ChartOfAccountSeeder`/`SharingFeeService`: pendapatan lab **4-1200** (Pendapatan Penunjang Lab/Radiologi), beban **5-1200** (Biaya Jasa Dokter) dan hutang **2-1200** (Hutang Jasa Dokter). *Jawaban "ya" saya tafsirkan sebagai persetujuan memakai akun-akun ini; koreksi bila keliru.*

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
| Klaim reimburse ditolak karena dokumen terpisah | Cetak tagihan gabungan satu dokumen (Tahap 4) |

---

## 9. Status implementasi (2026-10-28)

| Tahap | Status | Rujukan kode |
|---|---|---|
| 0 Lab SOAP `selesai` & masuk bill | ✅ selesai + tes | `Livewire/Pemeriksaan/Penunjang.php`, migrasi `hasil_diterima_at`, `LabSoapTagihanTest` |
| 1 Kolom & relasi susulan | ✅ | migrasi `…000002_add_susulan_columns…`, `TransaksiRitel`, `TransaksiRitelItem` |
| 2 Lab susulan (tanpa `permintaan_penunjang`), jurnal 4-1200 | ✅ | `Services/Farmasi/SusulanService.php`, `RitelJurnalService` |
| 2b Sharing fee dokter (persentase `lab`, 5-1200/2-1200) | ✅ | `SharingFeeService::catatSharingFeeSusulan` |
| 3 UI kasir Order Susulan + permission | ✅ | `Livewire/Kasir/Susulan/SusulanKasir.php`, route `kasir.susulan.index`, permission `ritel.susulan` |
| 4 Tagihan Gabungan (cetak) | ✅ | `Services/Kasir/TagihanGabunganService.php`, route `kasir.billing.gabungan`, permission `billing.gabung` |
| 5 Asuransi | Dibatalkan (reimburse) | — |

Catatan operasional:
- **Deploy:** jalankan `php artisan migrate --force` dan `php artisan db:seed --class=RolePermissionSeeder --force` (membuat permission baru dan memberikannya ke role kasir).
- **Tes:** `phpunit.xml` kini mengarah ke database `emr_test` (harus dibuat dulu). Tes baru memakai `RefreshDatabase`.
- **Belum dibuat:** UI untuk mencatat penerimaan hasil lab dari pihak ketiga (kolom `hasil_diterima_at`/`hasil_url` sudah ada, tampilan badge sudah membedakan "Tertagih · Menunggu Hasil" dan "Hasil Diterima").
- **Belum diverifikasi:** perilaku di production (apakah lab dari SOAP di sana sudah masuk bill).

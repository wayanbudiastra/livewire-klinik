# Product Requirements Document (PRD)
# Modul Akuntansi — Update: Jurnal Manual, Neraca & Laporan Arus Kas

| Info | Detail |
|:-----|:-------|
| **Versi** | 1.1.0 (Fase 6) |
| **Tanggal** | Juni 2026 |
| **Status** | Draft |
| **Depends On** | `modul_akuntansi.md` (Fase 1–5 — **sudah selesai diimplementasikan**, lihat §1.1) |
| **Tech Stack** | Laravel 12 · Livewire 3 · MySQL · Tailwind CSS |
| **Scope** | (1) Input Jurnal Manual bebas untuk biaya operasional non-sistem; (2) Neraca (Balance Sheet) lengkap; (3) Laporan Arus Kas formal (metode langsung) |
| **Prinsip Desain** | Melanjutkan, bukan mengganti, infrastruktur Fase 1–5 yang sudah ada — jurnal manual tetap lewat `jurnal_pending` → review → posting yang sama; Neraca & Arus Kas dihitung **dinamis** dari `jurnal_umum`, tanpa membangun mekanisme tutup buku formal (di luar scope, lihat §9) |

---

## Daftar Isi

1. [Ringkasan & Kondisi Existing](#1-ringkasan--kondisi-existing)
2. [Tujuan & Non-Tujuan](#2-tujuan--non-tujuan)
3. [Fitur A — Input Jurnal Manual](#3-fitur-a--input-jurnal-manual)
4. [Fitur B — Neraca (Balance Sheet) Lengkap](#4-fitur-b--neraca-balance-sheet-lengkap)
5. [Fitur C — Laporan Arus Kas (Cash Flow Statement)](#5-fitur-c--laporan-arus-kas-cash-flow-statement)
6. [Perubahan Skema Database](#6-perubahan-skema-database)
7. [Model, Service, & Komponen Baru](#7-model-service--komponen-baru)
8. [Role & Hak Akses Baru](#8-role--hak-akses-baru)
9. [Keputusan Desain & Batasan yang Disengaja](#9-keputusan-desain--batasan-yang-disengaja)
10. [Fase Implementasi](#10-fase-implementasi)
11. [Out of Scope](#11-out-of-scope)

---

## 1. Ringkasan & Kondisi Existing

### 1.1 Status Fase 1–5 (sudah selesai, per audit Juni 2026)

PRD `modul_akuntansi.md` versi 1.0 sudah **selesai diimplementasikan seluruhnya**:

| Komponen | Status |
|---|---|
| `chart_of_accounts` (20 akun ter-seed) | ✅ |
| `jurnal_pending` + `jurnal_umum` (1 baris = 1 pasang debit-kredit, kolom `metadata` json sudah ada) | ✅ |
| `JurnalService::catat() / posting() / abaikan() / reversal()` | ✅ |
| Generator jurnal: Billing, Deposit, Ritel, Inventori, Asuransi, Sharing Fee | ✅ |
| UI: ChartOfAccountManager, JurnalPendingTable, JurnalUmumTable | ✅ |
| Laporan: Buku Besar, **Neraca Saldo** (trial balance), Laba Rugi sederhana | ✅ |
| Permission: `akuntansi.coa.manage`, `akuntansi.jurnal.posting`, `akuntansi.jurnal.view`, `akuntansi.laporan.view` | ✅ |

> **Penting — jangan tertukar istilah:** "Neraca Saldo" (*Trial Balance*) yang sudah ada di Fase 5 adalah daftar flat semua akun + total debit/kredit untuk **cek keseimbangan internal**, bukan laporan posisi keuangan formal. PRD ini membangun **"Neraca"** (*Balance Sheet*) yang sesungguhnya — Aset = Liabilitas + Ekuitas, dikelompokkan dan disajikan sebagai laporan keuangan, lihat §4.

### 1.2 Yang Belum Ada (gap yang ditutup PRD ini)

Diambil dari `modul_akuntansi.md` §15 (Out of Scope v1.0), dua butir berikut menjadi scope PRD ini:

1. **Input jurnal manual bebas (non-sistem)** — biaya operasional seperti listrik, sewa, gaji karyawan non-dokter. Saat ini akun `5-3100 Biaya Operasional Lainnya` sudah ada di COA tapi **tidak ada satu pun generator jurnal yang mengisinya** — juga tidak ada jurnal apa pun untuk mutasi `3-1100 Modal Pemilik` (suntik modal, prive). Satu-satunya cara mencatat ini hari ini adalah lewat `php artisan tinker` manual (lihat riwayat audit), yang jelas bukan solusi produksi.
2. **Neraca (Balance Sheet) lengkap** dan **Laporan Arus Kas formal**.

### 1.3 Alur Data (diperluas dari diagram `modul_akuntansi.md` §1)

```
┌────────────────────────────────────────────────────────────────────────────┐
│  Kasir/Billing, Ritel, Pembelian, BHP, Opname, Piutang ─┐  (Fase 1-5, ada) │
│                                                          │                  │
│  Input Jurnal Manual (form, user pilih akun bebas) ─────┤  (BARU — §3)    │
│                                                          ▼                  │
│                                              Generator/Input ──► jurnal_pending│
│                                                                  (staging) │
│                                                                     │      │
│                                                                     ▼      │
│                                            Review & Posting (Fase 4, ada)  │
│                                                                     │      │
│                                                                     ▼      │
│                                                jurnal_umum (POSTED, final) │
│                                                                     │      │
│                       ┌─────────────────────────┬──────────────────┤      │
│                       ▼                         ▼                  ▼      │
│              Buku Besar (ada)        Neraca Saldo (ada)   ┌────────────┐  │
│                                                            │  BARU:     │  │
│                                                            │  Neraca    │  │
│                                                            │  (§4)      │  │
│                                                            │  Arus Kas  │  │
│                                                            │  (§5)      │  │
│                                                            └────────────┘  │
└────────────────────────────────────────────────────────────────────────────┘
```

---

## 2. Tujuan & Non-Tujuan

### Tujuan
- Staf keuangan dapat mencatat biaya operasional non-sistem (listrik, air, sewa, gaji karyawan non-dokter, dll) dan mutasi modal pemilik **tanpa perlu akses database langsung**, lewat layar input sederhana.
- Jurnal manual tetap melewati alur **review & posting** yang sama dengan jurnal otomatis (§3.4) — tidak ada jalur pintas yang melewati kontrol.
- Owner/Akuntan dapat melihat **posisi keuangan klinik** (Aset, Liabilitas, Ekuitas) per tanggal tertentu dalam format Neraca standar, bukan hanya daftar saldo akun mentah.
- Owner/Akuntan dapat melihat **dari mana kas masuk dan ke mana kas keluar**, dikelompokkan ke Aktivitas Operasi/Investasi/Pendanaan.

### Non-Tujuan
- Tidak membangun mekanisme tutup buku/closing period formal (lihat §9 untuk alasan & cara Neraca tetap akurat tanpa itu).
- Tidak membangun approval workflow berjenjang (maker-checker multi-level) — pemisahan tugas cukup lewat permission berbeda untuk *input* vs *posting* (lihat §8), bukan workflow status tambahan.
- Tidak membangun Laporan Arus Kas metode tidak langsung (*indirect method*) — lihat alasan di §5.4 & §9.
- Tidak mengubah struktur `jurnal_pending`/`jurnal_umum` menjadi multi-baris (tetap 1 debit : 1 kredit per baris, mengikuti keputusan desain `modul_akuntansi.md` §5.3).

---

## 3. Fitur A — Input Jurnal Manual

### 3.1 Konsep

Jurnal manual **bukan** alur baru yang sejajar dengan sistem — ia adalah **satu sumber tambahan** yang menulis ke `jurnal_pending` lewat `JurnalService::catat()` yang sudah ada, dengan `sumber_tipe = 'jurnal_manual'`. Setelah tercatat sebagai `pending`, baris itu **otomatis muncul** di layar "Jurnal Pending" (Fase 4, sudah ada) dan diposting lewat mekanisme yang sama persis dengan jurnal otomatis. Tidak ada tabel approval baru, tidak ada status tambahan di luar yang sudah ada (`pending` / `posted` / `diabaikan`).

Satu tabel kecil baru, `jurnal_manual`, hanya berfungsi sebagai **"kartu identitas"** tiap entri manual (siapa input, kategori biaya apa, dokumen pendukung apa) — supaya bisa ditelusuri dan difilter terpisah dari sumber otomatis lain, sama seperti `goods_receipt` atau `billing` berfungsi sebagai sumber bagi modul lain.

### 3.2 Cakupan Akun — "Bebas" Tapi Dengan Pagar

"Bebas" berarti user **boleh memilih akun debit dan kredit apa saja** dari Chart of Accounts (tidak dibatasi hanya ke akun Biaya) — supaya bisa juga mencatat hal lain seperti suntik modal pemilik (Debit Kas, Kredit Modal Pemilik) atau koreksi/penyesuaian kecil. Pagar yang tetap berlaku:

- Akun debit ≠ akun kredit (validasi sederhana, mencegah jurnal kosong/typo).
- Hanya akun dengan `is_aktif = true` yang muncul di dropdown.
- Nominal harus > 0.
- Tanggal transaksi tidak boleh di masa depan (validasi sederhana, mencegah salah ketik tanggal).

Untuk mempercepat input kasus paling umum (biaya operasional), form menyediakan dropdown **Kategori Biaya** dengan pilihan cepat (lihat §3.3) yang otomatis menyarankan pasangan akun, tapi user tetap bisa override manual.

### 3.3 Form Input (`JurnalManualForm`)

| Field | Tipe | Keterangan |
|---|---|---|
| Tanggal | date, required | Default hari ini, tidak boleh masa depan |
| Kategori Biaya | select, optional | `listrik`, `air`, `sewa`, `gaji_non_dokter`, `internet_telepon`, `pajak_retribusi`, `suntik_modal`, `lainnya` — memilih kategori otomatis isi saran akun (lihat tabel saran di bawah), tapi field akun tetap bisa diubah manual |
| Akun Debit | select (searchable), required | Dari `chart_of_accounts` aktif |
| Akun Kredit | select (searchable), required | Dari `chart_of_accounts` aktif, harus berbeda dari akun debit |
| Nominal | number, required, > 0 | |
| Keterangan | text, required | Deskripsi bebas, contoh: "Bayar listrik PLN bulan Juni 2026" |
| Dokumen Pendukung | file, optional | PDF/JPG/PNG, maks 5MB — bukti transfer/invoice supplier/kuitansi |

**Saran otomatis akun per kategori** (hanya prefill, tetap bisa diubah):

| Kategori | Akun Debit Disarankan | Akun Kredit Disarankan |
|---|---|---|
| Listrik / Air / Internet & Telepon | 5-3100 Biaya Operasional Lainnya | 1-1100 Kas atau 1-1200 Bank |
| Sewa | 5-3100 Biaya Operasional Lainnya | 1-1100 Kas atau 1-1200 Bank |
| Gaji Karyawan Non-Dokter | 5-3100 Biaya Operasional Lainnya | 1-1100 Kas atau 1-1200 Bank |
| Pajak & Retribusi | 5-3100 Biaya Operasional Lainnya | 1-1100 Kas atau 1-1200 Bank |
| Suntik Modal Pemilik | 1-1100 Kas atau 1-1200 Bank | 3-1100 Modal Pemilik |
| Lainnya | — (kosong, pilih manual) | — |

> Saran ini hardcode di komponen Livewire (`$saranAkun` array), bukan tabel baru — cukup untuk kebutuhan saat ini, mudah ditambah saat ada kategori baru.

### 3.4 Alur Status

```
Input form ──► jurnal_manual (record baru) + jurnal_pending (status: pending)
                                                      │
                                                      ▼
                                    Tampil di "Jurnal Pending" (Fase 4, sudah ada)
                                                      │
                                ┌─────────────────────┼─────────────────────┐
                                ▼                                           ▼
                    Diposting → jurnal_umum (final)              Diabaikan (alasan wajib)
                                │
                                ▼ (jika ternyata salah & sudah posted)
                    "Batalkan" di JurnalManualTable
                                │
                                ▼
                    JurnalService::reversal('jurnal_manual', $id, ['jurnal_manual'], $userId)
                    → otomatis buat & posting jurnal balik (mekanisme yang sama
                      dengan reversal billing/sharing fee di Fase 1-5)
```

Tidak ada state "diedit" — selaras dengan keputusan desain existing (`modul_akuntansi.md` §7 poin 5): kalau nominal/akun salah, jurnal manual yang sudah pending diabaikan dan diinput ulang; kalau sudah posted, dibatalkan (reversal) lalu input ulang.

### 3.5 Layar Riwayat (`JurnalManualTable`)

Daftar semua entri `jurnal_manual` dengan kolom: Tanggal, Kategori, Akun Debit → Kredit, Nominal, Keterangan, Dokumen (link unduh kalau ada), Status (warisan dari `jurnal_pending`/`jurnal_umum` terkait), Dibuat Oleh, Aksi (Batalkan — hanya untuk yang sudah posted, sesuai §3.4).

Filter: rentang tanggal (default bulan berjalan, konsisten dengan pola filter di modul lain — lihat preseden di Pemakaian BHP), kategori, status.

---

## 4. Fitur B — Neraca (Balance Sheet) Lengkap

### 4.1 Struktur Laporan

Neraca dihitung **per tanggal** (as-of date), bukan per rentang, dan **opsional dibandingkan** dengan satu tanggal pembanding (misal akhir bulan lalu) — dua kolom berdampingan.

```
NERACA — per [tanggal]                          [tanggal]      [tanggal pembanding]
═══════════════════════════════════════════════════════════════════════════════
ASET
  Aset Lancar
    Kas (1-1100)                                    xxx              xxx
    Bank (1-1200)                                   xxx              xxx
    Persediaan Barang (1-1300)                      xxx              xxx
    Piutang Asuransi/BPJS (1-1400)                  xxx              xxx
    Deposit Pasien (1-1500)                         xxx              xxx
    ─────────────────────────────────────────────────────────────────────
    Total Aset Lancar                               xxx              xxx
  Aset Tidak Lancar
    (belum ada akun — section tampil kosong/Rp 0, siap untuk akun masa depan
     seperti peralatan medis jika ditambahkan)
─────────────────────────────────────────────────────────────────────────
TOTAL ASET                                          xxx              xxx
═══════════════════════════════════════════════════════════════════════
LIABILITAS
  Liabilitas Jangka Pendek
    Hutang Dagang (2-1100)                          xxx              xxx
    Hutang Jasa Dokter (2-1200)                     xxx              xxx
    Titipan Deposit Pasien (2-1300)                 xxx              xxx
    ─────────────────────────────────────────────────────────────────────
    Total Liabilitas Jangka Pendek                  xxx              xxx
  Liabilitas Jangka Panjang
    (belum ada akun — section tampil kosong/Rp 0)
─────────────────────────────────────────────────────────────────────────
TOTAL LIABILITAS                                    xxx              xxx
═══════════════════════════════════════════════════════════════════════
EKUITAS
  Modal Pemilik (3-1100)                            xxx              xxx
  Laba Ditahan (3-1200, akumulasi s/d akhir
    tahun fiskal sebelumnya)                         xxx              xxx
  Laba/Rugi Tahun Berjalan (1 Jan s/d tanggal
    Neraca, dihitung dinamis dari Laba Rugi)         xxx              xxx
─────────────────────────────────────────────────────────────────────────
TOTAL EKUITAS                                       xxx              xxx
═══════════════════════════════════════════════════════════════════════
TOTAL LIABILITAS + EKUITAS                          xxx              xxx
SELISIH (harus = 0)                                 xxx              xxx
═══════════════════════════════════════════════════════════════════════
```

### 4.2 Logika Perhitungan

Tidak ada closing period — semua dihitung **dinamis** langsung dari `jurnal_umum` tiap kali laporan dibuka (sama seperti `ChartOfAccount::getSaldoAttribute()` & `AkuntansiLaporanService::neracaSaldo()` yang sudah ada):

1. **Saldo akun Aset & Liabilitas** per tanggal cutoff: pakai ulang logika `ChartOfAccount::getSaldoAttribute()` (Fase 1), tapi versi yang menerima parameter tanggal cutoff (`getSaldoSampai(string $tanggal)` — method baru, query `jurnal_umum` dengan filter `tanggal <= $cutoff`).
2. **Laba Ditahan**: panggil `AkuntansiLaporanService::labaRugi()` (Fase 5, sudah ada) dengan rentang `[awal_data, 31 Desember tahun_cutoff - 1]`. Kalau tanggal Neraca ada di tahun pertama sistem berjalan, nilainya 0.
3. **Laba/Rugi Tahun Berjalan**: panggil `labaRugi()` lagi dengan rentang `[1 Januari tahun_cutoff, tanggal_cutoff]`.
4. **Validasi keseimbangan**: `Total Aset - (Total Liabilitas + Total Ekuitas)` harus 0. Kalau tidak (selisih ≠ 0, mestinya tidak mungkin terjadi karena double-entry selalu seimbang per definisi), tampilkan sebagai warning merah — sinyal ada bug data, bukan disembunyikan.

> Asumsi tahun fiskal = tahun kalender (Januari–Desember). Cukup untuk kebutuhan klinik saat ini; tidak ada UI untuk mengatur tahun fiskal custom (lihat §9).

### 4.3 Komponen

`Akuntansi\NeracaReport` — input: tanggal cutoff (default hari ini) + toggle "Bandingkan dengan tanggal lain" (opsional, munculkan date picker kedua). Tombol cetak/PDF mengikuti pola cetak laporan lain di sistem (kalau sudah ada konvensi `@livewire` print view, ikuti; kalau belum, cukup tombol browser print untuk v1).

---

## 5. Fitur C — Laporan Arus Kas (Cash Flow Statement)

### 5.1 Metode: Langsung (Direct Method)

Dipilih **metode langsung**, bukan tidak langsung — alasan di §9. Laporan mengelompokkan **pergerakan kas/bank aktual** (bukan rekonsiliasi dari laba bersih) per periode (rentang tanggal, bukan per-tanggal seperti Neraca) ke tiga aktivitas:

```
LAPORAN ARUS KAS (Metode Langsung) — periode [dari] s/d [sampai]
═══════════════════════════════════════════════════════════════
AKTIVITAS OPERASI
  Penerimaan dari pasien/asuransi (pendapatan jasa medis,
    penunjang, obat, klaim)                              xxx
  Pembayaran ke supplier (pembelian barang)              (xxx)
  Pembayaran biaya operasional & jasa dokter              (xxx)
  ─────────────────────────────────────────────────────────────
  Kas Bersih dari Aktivitas Operasi                       xxx
AKTIVITAS INVESTASI
  (belum ada transaksi aset tetap — section kosong/Rp 0,
   siap dipakai saat modul aset tetap ditambahkan)         xxx
AKTIVITAS PENDANAAN
  Suntik Modal Pemilik                                    xxx
  Prive/Penarikan Modal Pemilik                          (xxx)
  ─────────────────────────────────────────────────────────────
  Kas Bersih dari Aktivitas Pendanaan                      xxx
═══════════════════════════════════════════════════════════════
KENAIKAN (PENURUNAN) KAS BERSIH                            xxx
Saldo Kas & Bank Awal Periode                              xxx
Saldo Kas & Bank Akhir Periode                              xxx
(harus sama dengan saldo aktual akun Kas + Bank di Neraca)  ✓/✗
```

### 5.2 Logika Klasifikasi — Berbasis Akun Lawan

Karena struktur jurnal selalu 1 pasang debit-kredit, **setiap baris `jurnal_umum` yang menyentuh akun kas/bank** (lihat kolom baru `is_kas_setara_kas` di §6.1) punya satu "akun lawan" (sisi yang bukan kas). Aktivitas ditentukan dari `golongan` + `kelompok` akun lawan tersebut:

| Golongan Akun Lawan | Kelompok | Aktivitas |
|---|---|---|
| `pendapatan` atau `biaya` | – | **Operasi** |
| `aset` (selain kas/bank itu sendiri) | `lancar` (persediaan, piutang) | **Operasi** (perubahan modal kerja) |
| `aset` | `tidak_lancar` | **Investasi** |
| `liabilitas` | `jangka_pendek` (hutang dagang, hutang jasa dokter) | **Operasi** |
| `liabilitas` | `jangka_panjang` | **Pendanaan** |
| `ekuitas` (Modal Pemilik) | – | **Pendanaan** |
| `liabilitas` khusus Titipan Deposit Pasien (2-1300) | `jangka_pendek` | **Operasi** (diperlakukan sebagai bagian operasional klinik) |

Kalau **kedua sisi** jurnal adalah akun kas/bank (contoh: transfer dari Kas ke Bank), baris itu **diabaikan** dari laporan arus kas (perpindahan internal, tidak mengubah total kas+bank).

### 5.3 Logika Perhitungan

`AkuntansiLaporanService::arusKas(string $dari, string $sampai)` (method baru):
1. Ambil semua baris `jurnal_umum` dalam rentang tanggal di mana `kode_akun_debit` ATAU `kode_akun_kredit` termasuk akun `is_kas_setara_kas = true`, KECUALI baris di mana **kedua** akun kas/bank (lihat §5.2 poin transfer internal).
2. Untuk tiap baris: tentukan arah (debit ke kas = kas **masuk**, kredit dari kas = kas **keluar**) dan klasifikasi aktivitas dari akun lawan (tabel §5.2).
3. Group & sum per aktivitas, sajikan baris-baris representatif (per kategori akun lawan, bukan per transaksi individual — supaya ringkas, sama seperti pengelompokan di Laba Rugi).
4. Saldo awal = total saldo akun kas/bank per `getSaldoSampai($dari - 1 hari)` (method yang sama dipakai Neraca, §4.2). Saldo akhir = saldo awal + kenaikan/penurunan bersih, **harus** sama dengan `getSaldoSampai($sampai)` — jadi otomatis tervalidasi silang dengan Neraca.

### 5.4 Mengapa Metode Langsung, Bukan Tidak Langsung

Metode langsung **lebih murah dibangun** untuk sistem ini karena setiap transaksi kas sudah tercatat baris-per-baris dengan akun lawannya di `jurnal_umum` — tidak perlu direkonstruksi. Metode tidak langsung (mulai dari laba bersih, lalu koreksi item non-kas seperti depresiasi & perubahan modal kerja) butuh data depresiasi/aset tetap yang **eksplisit di luar scope** (`modul_akuntansi.md` §15). Memaksakan metode tidak langsung sekarang akan menghasilkan rekonsiliasi yang janggal karena tidak ada item non-kas untuk dikoreksi selain perubahan piutang/persediaan/hutang (yang sebenarnya sudah cukup direpresentasikan natural lewat metode langsung). Metode tidak langsung tetap bisa ditambahkan di fase mendatang **tanpa mengubah skema** — cukup service baru yang membaca data yang sama.

### 5.5 Komponen

`Akuntansi\ArusKasReport` — input: rentang tanggal (default bulan berjalan).

---

## 6. Perubahan Skema Database

### 6.1 ALTER `chart_of_accounts`

```php
Schema::table('chart_of_accounts', function (Blueprint $table) {
    $table->enum('kelompok', ['lancar', 'tidak_lancar', 'jangka_pendek', 'jangka_panjang'])
          ->nullable()->after('golongan');
    // null untuk golongan pendapatan/biaya/ekuitas/lainnya (tidak relevan)
    $table->boolean('is_kas_setara_kas')->default(false)->after('kelompok');
});
```

Seeder update (`ChartOfAccountSeeder` atau migration data terpisah):
- `is_kas_setara_kas = true` untuk `1-1100` (Kas) dan `1-1200` (Bank). Semua akun lain `false`.
- `kelompok = 'lancar'` untuk semua akun golongan `aset` yang ada saat ini (1-1100 s/d 1-1500 — belum ada aset tidak lancar).
- `kelompok = 'jangka_pendek'` untuk semua akun golongan `liabilitas` yang ada saat ini (2-1100 s/d 2-1300 — belum ada liabilitas jangka panjang).
- Akun baru yang ditambahkan setelah ini (lewat `ChartOfAccountManager`) wajib mengisi `kelompok` jika golongannya aset/liabilitas — tambahkan validasi di form.

### 6.2 Tabel Baru `jurnal_manual`

```php
Schema::create('jurnal_manual', function (Blueprint $table) {
    $table->id();
    $table->date('tanggal');
    $table->string('kategori', 30)->nullable(); // listrik|air|sewa|gaji_non_dokter|internet_telepon|pajak_retribusi|suntik_modal|lainnya
    $table->string('kode_akun_debit', 10);
    $table->string('kode_akun_kredit', 10);
    $table->decimal('nominal', 16, 2);
    $table->string('keterangan', 255);
    $table->string('dokumen_pendukung')->nullable(); // path storage
    $table->foreignId('dibuat_oleh')->constrained('users');
    $table->timestamps();

    $table->foreign('kode_akun_debit')->references('kode')->on('chart_of_accounts');
    $table->foreign('kode_akun_kredit')->references('kode')->on('chart_of_accounts');
    $table->index(['tanggal', 'kategori']);
});
```

> Kolom `kode_akun_*`/`nominal`/`tanggal` di sini **terduplikasi sengaja** dengan yang akan tercatat di `jurnal_pending` — `jurnal_manual` adalah "kartu identitas" sumber (sama prinsipnya dengan `goods_receipt` atau `billing` yang juga punya datanya sendiri terpisah dari `jurnal_pending`), bukan tabel jurnal itu sendiri. Status jurnal (pending/posted/diabaikan) tetap tunggal sumbernya di `jurnal_pending`/`jurnal_umum`, diakses lewat relasi `sumber_tipe='jurnal_manual'` + `sumber_id`.

### 6.3 Tidak Ada Perubahan di `jurnal_pending` / `jurnal_umum`

Kolom `metadata` (json, sudah ada di `jurnal_pending` sejak Fase 1) cukup untuk menyimpan info tambahan kalau diperlukan; tidak perlu kolom baru. `jurnal_umum` juga tidak perlu kolom tambahan — klasifikasi arus kas (§5.2) dihitung dari `chart_of_accounts.golongan`/`kelompok`, bukan dari `jurnal_umum` itu sendiri.

---

## 7. Model, Service, & Komponen Baru

### 7.1 Model

| Model | Tabel | Catatan |
|---|---|---|
| `App\Models\Akuntansi\JurnalManual` | `jurnal_manual` | `belongsTo(User::class, 'dibuat_oleh')`, `belongsTo(ChartOfAccount, 'kode_akun_debit', 'kode')`, idem kredit. Accessor `getJurnalPendingAttribute()` / `getStatusAttribute()` — cari baris `JurnalPending` terkait via `sumber_tipe='jurnal_manual'` + `sumber_id=$this->id` untuk tahu status pending/posted/diabaikan. |

### 7.2 Service

| Service | Tanggung Jawab |
|---|---|
| `App\Services\Akuntansi\JurnalManualService.php` (baru) | `buat(array $data, int $userId): JurnalManual` — create `JurnalManual` + panggil `JurnalService::catat()` dengan `sumberTipe='jurnal_manual'`. `batalkan(JurnalManual $jm, int $userId): void` — panggil `JurnalService::reversal('jurnal_manual', $jm->id, ['jurnal_manual'], $userId)`. |
| `App\Services\Akuntansi\JurnalService.php` (extend) | Tambah `getSaldoSampai(string $kodeAkun, string $tanggal): float` (atau pindahkan ke `ChartOfAccount` model sebagai method instance) — dipakai Neraca & Arus Kas untuk saldo per-tanggal, bukan kumulatif sampai sekarang seperti `getSaldoAttribute()` existing. |
| `App\Services\Laporan\AkuntansiLaporanService.php` (extend) | Tambah `neraca(string $tanggal, ?string $tanggalPembanding = null): array` (§4) dan `arusKas(string $dari, string $sampai): array` (§5). Method existing (`bukuBesar`, `neracaSaldo`, `labaRugi`) **tidak diubah**, dipanggil ulang (reuse) dari dalam dua method baru ini. |

### 7.3 Livewire Components & Routes

| Route | Komponen | Permission |
|---|---|---|
| `GET /akuntansi/jurnal-manual/create` | `Akuntansi\JurnalManualForm` | `akuntansi.jurnal_manual.create` |
| `GET /akuntansi/jurnal-manual` | `Akuntansi\JurnalManualTable` | `akuntansi.jurnal.view` (sama dengan izin lihat jurnal lain) |
| `GET /akuntansi/neraca` | `Akuntansi\NeracaReport` | `akuntansi.laporan.view` |
| `GET /akuntansi/arus-kas` | `Akuntansi\ArusKasReport` | `akuntansi.laporan.view` |

Sidebar "Akuntansi" (sudah ada sejak Fase 4) tambah 3 sub-menu: **Input Jurnal Manual**, **Neraca**, **Arus Kas**.

---

## 8. Role & Hak Akses Baru

| Permission | Deskripsi | Role Disarankan |
|---|---|---|
| `akuntansi.jurnal_manual.create` | Input jurnal manual baru | Staf Keuangan, Akuntan, Admin |

> **Pemisahan tugas (segregation of duties)** yang disarankan: berikan `akuntansi.jurnal_manual.create` ke role "Keuangan" (yang sehari-hari mencatat biaya) **tanpa** `akuntansi.jurnal.posting` — supaya entri manual wajib direview & diposting oleh role "Akuntan"/Owner yang berbeda orangnya. Ini murni keputusan **penugasan role** saat implementasi, bukan workflow status baru di kode (lihat §2 Non-Tujuan).

Permission lain (`akuntansi.jurnal.view`, `akuntansi.jurnal.posting`, `akuntansi.laporan.view`) **dipakai ulang**, tidak ada permission baru untuk Neraca/Arus Kas (sudah tercakup `akuntansi.laporan.view`).

---

## 9. Keputusan Desain & Batasan yang Disengaja

1. **Tidak ada tutup buku/closing period formal.** Neraca & Arus Kas dihitung dinamis dari `jurnal_umum` setiap kali dibuka (lihat §4.2). Konsekuensi: laporan selalu real-time dan otomatis konsisten dengan Buku Besar/Neraca Saldo yang sudah ada (tidak ada risiko "lupa tutup buku" atau data closing basi), tapi performa query akan melambat secara linear seiring volume data jurnal bertambah dari tahun ke tahun. **Mitigasi**: kalau di masa depan volume data jadi masalah performa, baru bangun mekanisme closing/snapshot tahunan — tidak dibangun sekarang karena prinsip "sederhana, jangan over-engineer" (`modul_akuntansi.md` Prinsip Desain).
2. **Tahun fiskal = tahun kalender**, tidak ada UI untuk kustomisasi (misal tahun fiskal mulai April). Cukup untuk kebutuhan klinik kecil-menengah saat ini.
3. **Metode Arus Kas = Langsung saja**, metode tidak langsung tidak dibangun (alasan teknis di §5.4) — kalau suatu saat dibutuhkan (misal untuk keperluan audit eksternal yang mensyaratkan format tertentu), bisa ditambah sebagai service baru tanpa mengubah skema.
4. **Jurnal manual tetap 1 debit : 1 kredit per entri**, mengikuti batasan struktural yang sudah ada (`modul_akuntansi.md` §5.3). Kasus seperti "gaji 5 karyawan dalam 1 kali bayar" diinput sebagai 1 entri manual dengan nominal total (Debit Biaya Operasional, Kredit Kas) — rincian per karyawan **bukan** tanggung jawab modul akuntansi (kalau dibutuhkan breakdown per karyawan, itu domain modul payroll/HR yang belum ada, di luar scope).
5. **Tidak ada validasi "akun debit harus golongan Biaya" untuk jurnal manual** — sengaja dibuat benar-benar bebas (lihat §3.2) supaya juga bisa menangani suntik modal, koreksi, dll. Risiko salah pilih akun ditahan lewat kombinasi: (a) saran otomatis per kategori, (b) tetap lewat review & posting manusia sebelum jadi permanen, (c) pemisahan tugas role (§8). Tidak ditambah validasi "akun debit harus X" di kode karena akan membatasi fleksibilitas yang justru jadi tujuan fitur ini.

---

## 10. Fase Implementasi

| Fase | Lingkup | Estimasi |
|---|---|---|
| **Fase 6a — Jurnal Manual** | Migration `jurnal_manual`, `JurnalManual` model, `JurnalManualService`, `JurnalManualForm`, `JurnalManualTable`, permission baru, upload dokumen pendukung | 3–4 hari |
| **Fase 6b — Fondasi Neraca & Arus Kas** | ALTER `chart_of_accounts` (`kelompok`, `is_kas_setara_kas`) + seeder update, `getSaldoSampai()` di model/service | 1–2 hari |
| **Fase 6c — Neraca** | `AkuntansiLaporanService::neraca()`, `NeracaReport` component + view, validasi keseimbangan | 3–4 hari |
| **Fase 6d — Arus Kas** | `AkuntansiLaporanService::arusKas()`, `ArusKasReport` component + view, validasi cross-check saldo akhir vs Neraca | 4–5 hari |

---

## 11. Out of Scope

- Tutup buku/closing period formal, snapshot saldo awal tahun (lihat §9 poin 1).
- Tahun fiskal custom (non-Januari–Desember).
- Laporan Arus Kas metode tidak langsung (lihat §9 poin 3).
- Approval workflow berjenjang/multi-level untuk jurnal manual — hanya pending → posting seperti jurnal lain.
- Modul payroll/HR (rincian gaji per karyawan, slip gaji, BPJS ketenagakerjaan, dll) — jurnal manual hanya mencatat **agregat** biaya gaji sebagai satu angka.
- Modul aset tetap & depresiasi (peralatan medis) — section "Aset Tidak Lancar"/"Aktivitas Investasi" disiapkan strukturnya tapi akan tampil kosong sampai modul ini dibangun di fase lain.
- Export Neraca/Arus Kas ke format standar (SAK EMKM/PSAK) atau integrasi e-Faktur/pajak — tetap mengikuti batasan `modul_akuntansi.md` §15.
- Multi-mata uang, multi-cabang/multi-entitas — tetap mengikuti batasan `modul_akuntansi.md` §15.

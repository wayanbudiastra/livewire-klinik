# Product Requirements Document (PRD)
# Modul Akuntansi — Pencatatan Jurnal Otomatis Lintas Modul

| Info | Detail |
|:-----|:-------|
| **Versi** | 1.0.0 |
| **Tanggal** | Juni 2026 |
| **Status** | Draft |
| **Depends On** | `modul_kasir.md` · `modul_kasir_update.md` · `manajemen_inventory.md` · `setup_asuransi.md` · `obat_ritel.md` |
| **Tech Stack** | Laravel 12 · Livewire 3 · MySQL · Tailwind CSS |
| **Scope** | Chart of Accounts · Jurnal Umum (double-entry) · Posting otomatis dari semua transaksi · Buku Besar · Neraca Saldo · Laba Rugi sederhana |
| **Prinsip Desain** | Sederhana, tidak menduplikasi data — akuntansi **membaca** transaksi yang sudah ada, bukan input ulang manual |

---

## Daftar Isi

1. [Ringkasan Eksekutif](#1-ringkasan-eksekutif)
2. [Tujuan & Non-Tujuan](#2-tujuan--non-tujuan)
3. [Kondisi Existing — Apa yang Sudah Ada](#3-kondisi-existing--apa-yang-sudah-ada)
4. [Chart of Accounts (COA) Sederhana](#4-chart-of-accounts-coa-sederhana)
5. [Skema Database](#5-skema-database)
6. [Pemetaan Transaksi → Jurnal](#6-pemetaan-transaksi--jurnal)
7. [Alur Posting: Pending → Review → Posted](#7-alur-posting-pending--review--posted)
8. [Model Eloquent](#8-model-eloquent)
9. [Service Layer](#9-service-layer)
10. [Livewire Components & Routes](#10-livewire-components--routes)
11. [Laporan Keuangan](#11-laporan-keuangan)
12. [Role & Hak Akses](#12-role--hak-akses)
13. [Gap yang Harus Ditutup](#13-gap-yang-harus-ditutup)
14. [Fase Implementasi](#14-fase-implementasi)
15. [Out of Scope](#15-out-of-scope)

---

## 1. Ringkasan Eksekutif

Aplikasi klinik ini sudah memiliki banyak modul yang menghasilkan transaksi finansial — kasir, ritel farmasi, pembelian inventory, piutang asuransi — tetapi **belum ada satu modul akuntansi terpusat** yang mencatat semuanya sebagai jurnal berpasangan (debit/kredit) dan menghasilkan laporan keuangan standar (Neraca Saldo, Laba Rugi).

Sebagian infrastruktur sudah dirintis: tabel `jurnal_inventori_pending` dan `InventoriJurnalService` sudah mencatat jurnal untuk pembelian barang (GR), pemakaian BHP, dan selisih stok opname — namun sifatnya *pending* (belum pernah diposting ke buku besar) dan **hanya mencakup sebagian transaksi inventory**. Transaksi pendapatan (kasir/billing), penjualan ritel, piutang asuransi, dan sharing fee dokter **belum punya jurnal sama sekali**.

Modul ini akan:
- Membangun **Chart of Accounts (COA)** sederhana yang melanjutkan konvensi kode akun yang sudah dipakai (`1-xxxx` Aset, `2-xxxx` Liabilitas, `3-xxxx` Ekuitas, `4-xxxx` Pendapatan, `5-xxxx` Biaya, `8-xxxx` Lainnya).
- Mengganti `jurnal_inventori_pending` menjadi **jurnal pending generik** (`jurnal_pending`) yang dipakai oleh *semua* modul, bukan hanya inventory.
- Menambahkan generator jurnal untuk modul yang belum tercakup: kasir/billing (pendapatan jasa medis), ritel farmasi (pendapatan + HPP), piutang asuransi (pengakuan pendapatan saat lunas), dan sharing fee dokter (biaya jasa).
- Menyediakan layar **review & posting** sebelum jurnal pending menjadi permanen di **Jurnal Umum** (`jurnal_umum`).
- Menyediakan laporan dasar: Buku Besar per akun, Neraca Saldo, dan Laba Rugi sederhana per periode.

```
┌──────────────────────────────────────────────────────────────────────────┐
│                     ALUR DATA MODUL AKUNTANSI                            │
│                                                                           │
│  Kasir/Billing ──┐                                                       │
│  Ritel Farmasi ──┤                                                       │
│  Pembelian (GR) ─┼──► Generator Jurnal per Modul ──► jurnal_pending     │
│  BHP / Opname ───┤        (Service Layer)              (staging)        │
│  Piutang Asuransi┤                                          │           │
│  Sharing Fee ────┘                                          ▼           │
│                                              Review oleh Akuntan/Admin   │
│                                                          │               │
│                                                          ▼               │
│                                            jurnal_umum (POSTED, final)   │
│                                                          │               │
│                                                          ▼               │
│                              Buku Besar · Neraca Saldo · Laba Rugi       │
└──────────────────────────────────────────────────────────────────────────┘
```

---

## 2. Tujuan & Non-Tujuan

### Tujuan
- Setiap transaksi finansial yang sudah terjadi di aplikasi (kasir, ritel, pembelian, BHP, opname, piutang asuransi) otomatis menghasilkan **draft jurnal** tanpa input ulang manual.
- Akuntan/Admin dapat **meninjau** draft jurnal sebelum menjadi permanen (mencegah kesalahan sistem ter-posting tanpa kontrol).
- Tersedia laporan dasar yang cukup untuk kebutuhan klinik kecil-menengah: Buku Besar, Neraca Saldo, Laba Rugi.
- Saldo setiap akun dapat ditelusuri balik ke transaksi asal (`sumber_tipe` + `sumber_id`) untuk audit.

### Non-Tujuan (lihat juga [§15 Out of Scope](#15-out-of-scope))
- Modul ini **bukan** software akuntansi umum (tidak menggantikan Accurate/Jurnal.id untuk kebutuhan pajak kompleks, multi-cabang, atau multi-mata uang).
- Tidak membangun ulang modul kasir/inventory/asuransi — hanya **membaca** data yang sudah ada.
- Tidak ada fitur input jurnal manual bebas di fase awal (transaksi non-sistem seperti biaya listrik/sewa bisa ditambahkan di fase lanjutan — lihat §14).

---

## 3. Kondisi Existing — Apa yang Sudah Ada

Hasil audit codebase (Juni 2026):

| Area | Status | Keterangan |
|---|---|---|
| `app/Services/Akuntansi/InventoriJurnalService.php` | ✅ Ada, parsial | Hanya generate jurnal untuk: pembelian (GR terverifikasi), pemakaian BHP, selisih stok opname |
| `jurnal_inventori_pending` (tabel) | ✅ Ada | Staging table, status `pending/posted/diabaikan` — **belum pernah ada proses posting ke buku besar**, karena buku besar belum ada |
| Kode akun (`AKUN` const di service) | ✅ Sebagian | `persediaan_barang` (1-1300), `hutang_dagang` (2-1100), `biaya_bhp` (5-2100), `selisih_opname` (8-1100) sudah dipakai; `hpp_farmasi` (5-1100) & `hpp_tindakan` (5-1200) **didefinisikan tapi tidak pernah dipanggil** |
| Jurnal Umum / Buku Besar / COA master | ❌ Belum ada | Tidak ada tabel `chart_of_accounts` atau `jurnal_umum` |
| Pendapatan kasir (billing lunas) | ❌ Belum ada jurnal | `sesi_kas` & `pembayaran_split` sudah merekap cash/non-cash harian, tapi tidak ada jurnal pendapatan jasa medis |
| Pendapatan ritel + HPP ritel | ❌ Belum ada jurnal | `keluar_ritel` di `mutasi_stok` memotong stok tanpa jurnal HPP |
| Piutang asuransi → pendapatan | ⚠️ Parsial | `PenagihanService::akuiPendapatan()` hanya tulis ke `audit_kasir` (log), bukan jurnal akuntansi |
| Hutang dagang ke supplier (pelunasan) | ❌ Belum ada tracking | Hutang tercatat saat GR, tapi tidak ada modul pembayaran/pelunasan hutang ke supplier |
| Sharing fee dokter | ❌ Belum ada transaksi | `sharing_fee` hanya master persentase, tidak ada perhitungan & pencatatan biaya jasa per kunjungan |

**Keputusan desain:** modul ini **memperluas**, bukan mengganti, `InventoriJurnalService`. Tabel `jurnal_inventori_pending` di-generalisasi menjadi `jurnal_pending` agar bisa dipakai semua modul (lihat migration di §5).

---

## 4. Chart of Accounts (COA) Sederhana

Melanjutkan konvensi kode akun yang sudah dipakai di `InventoriJurnalService`. Format: `[golongan]-[nomor]`.

| Kode | Nama Akun | Golongan | Tipe Normal |
|---|---|---|---|
| 1-1100 | Kas | Aset Lancar | Debit |
| 1-1200 | Bank | Aset Lancar | Debit |
| 1-1300 | Persediaan Barang (Obat & Alkes) | Aset Lancar | Debit |
| 1-1400 | Piutang Asuransi/BPJS | Aset Lancar | Debit |
| 1-1500 | Deposit Pasien (Liabilitas — lihat catatan) | Aset Lancar | Debit |
| 2-1100 | Hutang Dagang (Supplier) | Liabilitas | Kredit |
| 2-1200 | Hutang Jasa Dokter (Sharing Fee) | Liabilitas | Kredit |
| 2-1300 | Titipan Deposit Pasien | Liabilitas | Kredit |
| 3-1100 | Modal Pemilik | Ekuitas | Kredit |
| 3-1200 | Laba Ditahan | Ekuitas | Kredit |
| 4-1100 | Pendapatan Jasa Medis (Tindakan/Konsultasi) | Pendapatan | Kredit |
| 4-1200 | Pendapatan Penunjang (Lab/Radiologi) | Pendapatan | Kredit |
| 4-1300 | Pendapatan Penjualan Obat (Resep + Ritel) | Pendapatan | Kredit |
| 4-1400 | Pendapatan Klaim Asuransi/BPJS | Pendapatan | Kredit |
| 5-1100 | HPP Farmasi (Obat & Alkes Terjual) | Biaya | Debit |
| 5-1200 | Biaya Jasa Dokter (Sharing Fee) | Biaya | Debit |
| 5-2100 | Biaya BHP (Bahan Habis Pakai) | Biaya | Debit |
| 5-3100 | Biaya Operasional Lainnya | Biaya | Debit |
| 8-1100 | Selisih Stok Opname | Lainnya | Debit/Kredit |
| 8-1200 | Piutang Tak Tertagih (Write-off) | Lainnya | Debit |

> **Catatan Deposit Pasien**: `1-1500` dan `2-1300` sengaja dipisah — saldo deposit pasien secara akuntansi adalah **liabilitas klinik** (uang titipan, bukan pendapatan), bukan aset. Saat topup: Debit Kas (1-1100), Kredit Titipan Deposit (2-1300). Saat dipakai bayar tagihan: Debit Titipan Deposit (2-1300), Kredit Pendapatan terkait.

COA disimpan di tabel `chart_of_accounts` agar bisa diubah/ditambah lewat UI (lihat §5), tidak hardcode di kode PHP seperti sekarang.

---

## 5. Skema Database

### 5.1 `chart_of_accounts` (baru)
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| kode | string(10) unique | format `1-1100` |
| nama | string(100) | |
| golongan | enum | aset, liabilitas, ekuitas, pendapatan, biaya, lainnya |
| tipe_normal | enum | debit, kredit |
| is_aktif | boolean default true | |
| timestamps | | |

### 5.2 `jurnal_pending` (generalisasi dari `jurnal_inventori_pending`)
Migration **alter** tabel lama: rename `jurnal_inventori_pending` → `jurnal_pending`, generalisasi nilai enum `tipe_transaksi`.

| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| sumber_tipe | string | `billing`, `transaksi_ritel`, `goods_receipt`, `pemakaian_bhp`, `stok_opname`, `piutang_asuransi`, `sharing_fee` |
| sumber_id | bigint | FK polimorfik ke tabel asal |
| tipe_transaksi | string | kode internal, contoh: `penjualan_ritel`, `pendapatan_billing`, `pembelian_gr`, dst — lihat §6 |
| tanggal_transaksi | date | tanggal transaksi asal (bukan tanggal posting) |
| kode_akun_debit | string(10) | FK ke `chart_of_accounts.kode` |
| kode_akun_kredit | string(10) | FK ke `chart_of_accounts.kode` |
| nominal | decimal(15,2) | |
| keterangan | string(255) | otomatis dari service, contoh: "Penjualan ritel RIT-20260601-0001" |
| status | enum | `pending`, `posted`, `diabaikan` |
| jurnal_umum_id | bigint nullable | FK terisi setelah diposting |
| timestamps | | |

### 5.3 `jurnal_umum` (baru — buku besar/general ledger header)
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| nomor_jurnal | string unique | format `JU-YYYYMM-XXXX` |
| tanggal | date | |
| kode_akun_debit | string(10) | |
| kode_akun_kredit | string(10) | |
| nominal | decimal(15,2) | |
| keterangan | string(255) | |
| sumber_tipe | string nullable | ditarik dari `jurnal_pending` saat posting, untuk audit trail |
| sumber_id | bigint nullable | |
| diposting_oleh | bigint FK users | |
| diposting_pada | timestamp | |
| timestamps | | |

> **Catatan desain**: satu baris `jurnal_pending`/`jurnal_umum` = satu pasangan debit-kredit sederhana (bukan multi-baris seperti jurnal akuntansi formal yang bisa >2 baris). Ini pilihan **kesederhanaan** sesuai scope PRD — cukup untuk klinik kecil-menengah. Jika di masa depan dibutuhkan jurnal multi-baris (>1 debit / >1 kredit dalam 1 nomor jurnal), perlu migrasi ke struktur header-detail (`jurnal_umum` + `jurnal_umum_detail`), namun ini **di luar scope v1.0**.

### 5.4 Migration yang dibutuhkan
1. `rename_jurnal_inventori_pending_to_jurnal_pending` — rename tabel + tambah kolom `sumber_tipe` jadi lebih generik (sebelumnya mungkin sudah ada kolom serupa, sesuaikan dengan skema asli).
2. `create_chart_of_accounts_table` — seed otomatis dengan COA di §4 lewat seeder.
3. `create_jurnal_umum_table`.

---

## 6. Pemetaan Transaksi → Jurnal

Tabel ini adalah **inti PRD** — setiap baris berikut harus diimplementasikan sebagai 1 method di service (lihat §9).

| # | Modul / Trigger | Kapan Jurnal Dibuat | Debit | Kredit | Nominal |
|---|---|---|---|---|---|
| 1 | Billing lunas (tunai/non-tunai) | `BillingService::lunasiBilling()` atau saat `pembayaran_split` tercatat dengan metode non-asuransi | Kas (1-1100) / Bank (1-1200) sesuai metode | Pendapatan Jasa Medis (4-1100) + Pendapatan Penunjang (4-1200) + Pendapatan Obat (4-1300) — **dipecah per kategori item di `invoice_item`** | sesuai `invoice_item.subtotal` per kategori |
| 2 | Billing dibayar via Deposit | Saat `pembayaran_split.metode = deposit` | Titipan Deposit Pasien (2-1300) | Pendapatan terkait (sama seperti #1) | `jumlah_pasien` |
| 3 | Topup Deposit Pasien | `DepositService::topup()` | Kas (1-1100) | Titipan Deposit Pasien (2-1300) | `transaksi_deposit.jumlah` |
| 4 | Refund Deposit Pasien | `DepositService::refund()` | Titipan Deposit Pasien (2-1300) | Kas (1-1100) | `transaksi_deposit.jumlah` |
| 5 | Penjualan Ritel — Pendapatan | `ObatRitelService::prosesBayar()` saat status → `dibayar` | Kas/Bank sesuai `metode_bayar` | Pendapatan Penjualan Obat (4-1300) | `transaksi_ritel.total_bayar` |
| 6 | Penjualan Ritel — HPP | `ObatRitelService::serahkanObat()` saat stok dipotong (status → `selesai`) | HPP Farmasi (5-1100) | Persediaan Barang (1-1300) | Σ(`jumlah` × `hpr` saat itu) per item |
| 7 | Pembelian via GR (sudah ada, **dipindah** ke service baru) | `PenerimaanService::verifikasiGr()` | Persediaan Barang (1-1300) | Hutang Dagang (2-1100) | `goods_receipt.total_nilai` |
| 8 | Pemakaian BHP (sudah ada) | `BhpService` saat verifikasi | Biaya BHP (5-2100) | Persediaan Barang (1-1300) | `pemakaian_bhp_item.nilai_total` |
| 9 | Selisih Stok Opname (sudah ada) | `StokOpnameService` saat verifikasi | Selisih Opname (8-1100) jika kurang | Persediaan Barang (1-1300) jika kurang (dibalik jika lebih) | `stok_opname_item.nilai_selisih` |
| 10 | Piutang Asuransi terbentuk | `PembayaranAsuransiService::prosesPembayaranAsuransi()` | Piutang Asuransi (1-1400) | Pendapatan Klaim Asuransi (4-1400) | `piutang_asuransi.jumlah_piutang` |
| 11 | Asuransi membayar klaim | `PenagihanService::catatPembayaran()` | Kas/Bank (1-1100/1-1200) | Piutang Asuransi (1-1400) | `pembayaran_asuransi.jumlah_bayar` |
| 12 | Klaim ditolak / write-off | `PenagihanService` saat status piutang → `ditolak` (manual approve) | Piutang Tak Tertagih (8-1200) | Piutang Asuransi (1-1400) | sisa `piutang_asuransi.sisa_piutang` |
| 13 | Sharing Fee Dokter terhitung | **Baru** — saat billing lunas, hitung `persentase` × nilai item per kategori dokter | Biaya Jasa Dokter (5-1200) | Hutang Jasa Dokter (2-1200) | Σ(nilai item kategori × `sharing_fee.persentase`) |
| 14 | Pembayaran Sharing Fee ke dokter | **Baru** — modul pembayaran sharing fee (belum ada, lihat §13) | Hutang Jasa Dokter (2-1200) | Kas/Bank | sesuai pembayaran |
| 15 | Pembatalan Billing (setelah lunas) | `BillingService::batalkanBilling()` | Pendapatan terkait (reversal, debit) | Kas/Bank (reversal, kredit) | nilai invoice yang dibatalkan |
| 16 | Pembatalan Ritel (setelah dibayar, sebelum diserahkan) | `ObatRitelService::batalkan()` | Pendapatan Penjualan Obat (reversal) | Kas/Bank (reversal) | `total_bayar` |

> Baris #1, #5, #6, #10–#16 adalah **jurnal baru** yang harus dibangun di PRD ini. Baris #7–#9 sudah ada di `InventoriJurnalService`, cukup dipindah ke tabel `jurnal_pending` yang baru (lihat §5.2).

---

## 7. Alur Posting: Pending → Review → Posted

1. **Generate otomatis** — setiap kali event transaksi terjadi (lihat trigger di §6), service akuntansi terkait membuat baris baru di `jurnal_pending` dengan status `pending`. Ini terjadi **otomatis di background** (dipanggil dari service modul asal, mengikuti pola `InventoriJurnalService` yang sudah ada), tidak butuh aksi user.
2. **Review harian/berkala** — Admin/Akuntan membuka layar **"Jurnal Pending"**, melihat daftar baris pending dikelompokkan per tanggal & jenis transaksi.
3. **Posting** — Admin/Akuntan memilih baris (atau "Posting Semua Hari Ini") → sistem:
   - Membuat baris baru di `jurnal_umum` dengan `nomor_jurnal` otomatis.
   - Update `jurnal_pending.status = posted`, isi `jurnal_umum_id`.
4. **Tolak/Abaikan** — jika baris pending dianggap salah (misal data sumber sudah dibatalkan), Admin bisa set status `diabaikan` dengan alasan (disimpan di `keterangan` atau kolom tambahan `alasan_abaikan`).
5. **Tidak bisa edit nominal manual** — untuk integritas, baris `jurnal_pending` hanya bisa di-posting atau diabaikan, **tidak bisa diedit nilainya**. Jika nilai salah, perbaikan harus dilakukan di transaksi sumber (misal billing dibatalkan & dibuat ulang), yang otomatis menghasilkan jurnal pending baru.

---

## 8. Model Eloquent

| Model | Tabel | Catatan |
|---|---|---|
| `App\Models\Akuntansi\ChartOfAccount` | `chart_of_accounts` | `scopeAktif()`, `getSaldoAttribute()` (computed dari jurnal_umum) |
| `App\Models\Akuntansi\JurnalPending` | `jurnal_pending` | relasi `morphTo('sumber')` jika memungkinkan, atau accessor manual berdasar `sumber_tipe` |
| `App\Models\Akuntansi\JurnalUmum` | `jurnal_umum` | `belongsTo(User::class, 'diposting_oleh')` |

> `JurnalInventoriPending` model lama **dipertahankan sebagai alias** (`protected $table = 'jurnal_pending';`) untuk backward compatibility kode existing, atau di-refactor langsung — keputusan teknis saat implementasi.

---

## 9. Service Layer

| Service | Tanggung Jawab |
|---|---|
| `App\Services\Akuntansi\JurnalService.php` | Fungsi generik: `catat(string $sumberTipe, int $sumberId, string $tipeTransaksi, string $tanggal, string $akunDebit, string $akunKredit, float $nominal, string $keterangan)` — dipanggil oleh semua generator di bawah. Juga `posting(array $jurnalPendingIds, int $userId)` dan `abaikan(int $jurnalPendingId, string $alasan)`. |
| `App\Services\Akuntansi\InventoriJurnalService.php` | **Existing, disesuaikan** — pakai `JurnalService::catat()` untuk pembelian GR, BHP, opname (baris #7–9). |
| `App\Services\Akuntansi\BillingJurnalService.php` | **Baru** — generate jurnal #1, #2, #15 dipanggil dari `BillingService` setiap kali status billing berubah ke `lunas` atau `dibatalkan`. |
| `App\Services\Akuntansi\DepositJurnalService.php` | **Baru** — generate jurnal #3, #4 dipanggil dari `DepositService`. |
| `App\Services\Akuntansi\RitelJurnalService.php` | **Baru** — generate jurnal #5, #6, #16 dipanggil dari `ObatRitelService`. |
| `App\Services\Akuntansi\AsuransiJurnalService.php` | **Baru** — generate jurnal #10, #11, #12 dipanggil dari `PembayaranAsuransiService` & `PenagihanService`. Mengganti `akuiPendapatan()` yang sekarang hanya log audit. |
| `App\Services\Akuntansi\SharingFeeService.php` | **Baru** — hitung & catat jurnal #13 saat billing lunas, berdasarkan `sharing_fee.persentase` dikalikan nilai item per kategori & dokter penanggung jawab kunjungan. |
| `App\Services\Laporan\AkuntansiLaporanService.php` | **Baru** — `bukuBesar(string $kodeAkun, $periode)`, `neracaSaldo($periode)`, `labaRugi($periode)`. |

---

## 10. Livewire Components & Routes

| Route | Komponen | Permission |
|---|---|---|
| `GET /akuntansi/coa` | `Akuntansi\ChartOfAccountManager` — CRUD COA | `akuntansi.coa.manage` |
| `GET /akuntansi/jurnal-pending` | `Akuntansi\JurnalPendingTable` — review & posting | `akuntansi.jurnal.posting` |
| `GET /akuntansi/jurnal-umum` | `Akuntansi\JurnalUmumTable` — riwayat jurnal posted, read-only | `akuntansi.jurnal.view` |
| `GET /akuntansi/buku-besar` | `Akuntansi\BukuBesarReport` — filter per akun + periode | `akuntansi.laporan.view` |
| `GET /akuntansi/neraca-saldo` | `Akuntansi\NeracaSaldoReport` | `akuntansi.laporan.view` |
| `GET /akuntansi/laba-rugi` | `Akuntansi\LabaRugiReport` | `akuntansi.laporan.view` |

Sidebar baru: menu **"Akuntansi"** sejajar dengan "Keuangan" (piutang/penagihan), berisi sub-menu: Chart of Accounts, Jurnal Pending, Jurnal Umum, Buku Besar, Neraca Saldo, Laba Rugi.

---

## 11. Laporan Keuangan

### 11.1 Buku Besar (General Ledger)
Filter: akun + rentang tanggal. Tampilkan: tanggal, keterangan, debit, kredit, saldo berjalan (running balance). Sumber: `jurnal_umum` saja (yang sudah posted).

### 11.2 Neraca Saldo (Trial Balance)
Per periode (akhir bulan), list semua akun di COA dengan total debit & total kredit kumulatif sampai tanggal tersebut. Total debit harus = total kredit (validasi keseimbangan).

### 11.3 Laba Rugi Sederhana (Income Statement)
Per periode:
```
PENDAPATAN
  Jasa Medis (4-1100)              xxx
  Penunjang (4-1200)               xxx
  Penjualan Obat (4-1300)          xxx
  Klaim Asuransi (4-1400)          xxx
  ─────────────────────────────────────
  Total Pendapatan                 xxx

BIAYA
  HPP Farmasi (5-1100)             xxx
  Biaya Jasa Dokter (5-1200)       xxx
  Biaya BHP (5-2100)               xxx
  Biaya Operasional (5-3100)       xxx
  ─────────────────────────────────────
  Total Biaya                      xxx

LABA/RUGI BERSIH                   xxx
```

> Neraca (Balance Sheet) penuh dan Laporan Arus Kas formal **tidak masuk v1.0** — lihat §15.

---

## 12. Role & Hak Akses

| Permission | Deskripsi | Role Default |
|---|---|---|
| `akuntansi.coa.manage` | Kelola Chart of Accounts | Super Admin |
| `akuntansi.jurnal.posting` | Posting/abaikan jurnal pending | Admin, Akuntan |
| `akuntansi.jurnal.view` | Lihat jurnal umum (read-only) | Admin, Akuntan, Owner |
| `akuntansi.laporan.view` | Lihat laporan keuangan | Admin, Akuntan, Owner |

> Tidak ada role baru "Akuntan" secara default di sistem saat ini — perlu ditambahkan ke seeder role/permission (`database/seeders/RolePermissionSeeder.php` atau setara) sebagai bagian implementasi.

---

## 13. Gap yang Harus Ditutup

Beberapa hal di luar jurnal akuntansi murni, tapi **wajib ada** agar pemetaan di §6 bisa berjalan:

1. **Kategorisasi `invoice_item` per akun pendapatan** — saat ini `invoice_item.jenis` sudah membedakan tindakan/penunjang/obat/racikan/manual, cukup untuk mapping ke akun 4-1100/4-1200/4-1300. Tidak perlu kolom baru.
2. **Penentuan dokter penanggung jawab per kategori sharing fee** — `sharing_fee.dokter_id` sudah ada, tapi perhitungan butuh tahu dokter mana yang melakukan tindakan/lab/radiologi per kunjungan. Perlu cek apakah `tindakan`/`permintaan_penunjang` punya kolom `dokter_id` atau `petugas_id` — **jika belum ada, perlu ditambahkan** sebagai prasyarat fitur #13.
3. **Modul pembayaran sharing fee ke dokter** — saat ini tidak ada UI/tabel untuk mencatat kapan klinik benar-benar membayar hutang jasa dokter (baris #14 di §6). Perlu tabel kecil baru `pembayaran_sharing_fee` (dokter_id, periode, jumlah, tanggal_bayar, metode) di fase lanjutan.
4. **Modul pelunasan hutang dagang ke supplier** — saat ini hutang tercatat di jurnal (#7) tapi tidak ada tracking pelunasan. Di luar scope v1.0 kecuali ditambahkan sebagai fase lanjutan (lihat §14 Fase 3).

---

## 14. Fase Implementasi

| Fase | Lingkup | Estimasi |
|---|---|---|
| **Fase 1 — Fondasi** | Migration COA + `jurnal_pending` (generalisasi) + `jurnal_umum`, seeder COA, `JurnalService` generik, migrasi `InventoriJurnalService` existing ke service baru tanpa ubah perilaku | 1 minggu |
| **Fase 2 — Generator Jurnal Pendapatan** | `BillingJurnalService`, `DepositJurnalService`, `RitelJurnalService` (baris #1–6, #15–16) | 1 minggu |
| **Fase 3 — Generator Jurnal Piutang & Sharing Fee** | `AsuransiJurnalService` (#10–12), `SharingFeeService` (#13) — termasuk audit kolom `dokter_id`/`petugas_id` di tindakan/penunjang bila perlu ditambahkan | 1–2 minggu |
| **Fase 4 — UI Review & Posting** | `JurnalPendingTable`, `JurnalUmumTable`, sidebar menu Akuntansi | 3–4 hari |
| **Fase 5 — Laporan** | Buku Besar, Neraca Saldo, Laba Rugi sederhana | 1 minggu |
| **Fase 6 (opsional, lanjutan)** | Modul pembayaran sharing fee ke dokter, pelunasan hutang dagang, Neraca penuh, Arus Kas | Backlog terpisah |

---

## 15. Out of Scope

- Input jurnal manual bebas (non-sistem) — biaya operasional seperti listrik, sewa, gaji karyawan non-dokter. **Bisa jadi Fase 6** dengan layar input jurnal manual sederhana, tapi tidak di v1.0.
- Neraca (Balance Sheet) lengkap dan Laporan Arus Kas formal (cash flow statement metode langsung/tidak langsung).
- Multi-mata uang, multi-cabang/multi-entitas.
- Perhitungan pajak (PPh 21 jasa dokter, PPN, dll).
- Penyusutan aset tetap (depresiasi peralatan medis).
- Export ke format akuntansi standar (SAK EMKM/PSAK penuh, e-Faktur, dll).
- Integrasi dengan software akuntansi pihak ketiga (Accurate, Jurnal.id, Zahir).
- Jurnal multi-baris (>1 debit atau >1 kredit dalam satu nomor jurnal) — struktur v1.0 dibatasi 1 debit : 1 kredit per baris untuk kesederhanaan (lihat catatan §5.3).

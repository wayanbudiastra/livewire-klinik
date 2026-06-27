# Product Requirements Document (PRD)
# Modul Retur Obat/Alkes — Retur ke Supplier & Retur Resep Sehari

| Info | Detail |
|:-----|:-------|
| **Versi** | 1.0.0 |
| **Tanggal** | Juni 2026 |
| **Status** | Draft |
| **Depends On** | `manajemen_inventory.md` · `inventory_update.md` (PO, GR, HPR) · `resep_obat.md` (resep & farmasi) · `modul_kasir.md` · `modul_kasir_update.md` (invoice, sesi kas, deposit) · `modul_akuntansi.md` (jurnal otomatis) |
| **Tech Stack** | Laravel 12 · Livewire 3 · MySQL · Tailwind CSS |
| **Scope** | (1) Retur barang dari Goods Receipt yang sudah diverifikasi, balik ke supplier; (2) Retur resep pasien yang sudah lunas, **selama masih di hari yang sama** dengan pelunasannya |
| **Prinsip Desain** | Retur **tidak mengubah riwayat medis/transaksi asli** (resep yang diresepkan dokter, GR yang sudah diterima) — ia adalah **transaksi baru yang mereferensikan & membalik sebagian efek** (stok, invoice, jurnal) dari transaksi asal, mengikuti pola reversal yang sudah ada di `JurnalService::reversal()` dan draft→verifikasi yang sudah ada di GR/PO/BHP/Opname |

---

## Daftar Isi

1. [Ringkasan & Kondisi Existing](#1-ringkasan--kondisi-existing)
2. [Tujuan & Non-Tujuan](#2-tujuan--non-tujuan)
3. [Fitur A — Retur Barang ke Supplier (dari GR)](#3-fitur-a--retur-barang-ke-supplier-dari-gr)
4. [Fitur B — Retur Resep Sehari](#4-fitur-b--retur-resep-sehari)
5. [Gap yang Harus Ditutup Lebih Dulu](#5-gap-yang-harus-ditutup-lebih-dulu)
6. [Skema Database](#6-skema-database)
7. [Model, Service, & Komponen](#7-model-service--komponen)
8. [Role & Hak Akses](#8-role--hak-akses)
9. [Keputusan Desain & Batasan yang Disengaja](#9-keputusan-desain--batasan-yang-disengaja)
10. [Fase Implementasi](#10-fase-implementasi)
11. [Out of Scope](#11-out-of-scope)

---

## 1. Ringkasan & Kondisi Existing

### 1.1 Kondisi Existing (audit Juni 2026)

| Area | Status | Keterangan |
|---|---|---|
| `goods_receipt` / `gr_item` | ✅ Ada | Status `draft → diverifikasi → dibatalkan`. `batalkanGr()` **hanya untuk status draft** — belum ada cara menangani barang yang sudah `diverifikasi` (sudah masuk stok) tapi perlu dikembalikan. |
| HPR (Moving Average) | ✅ Ada | `PenerimaanService::prosesItemGr()` menghitung HPR baru saat barang masuk. Tidak ada mekanisme "membalik" HPR (lihat §9). |
| `MutasiStok` tipe `retur_ke_supplier` | ⚠️ Ada di enum, **belum dipakai** | Enum migration sudah punya nilai ini sejak awal, tapi tidak ada satu pun kode yang men-create baris dengan tipe ini. |
| Jurnal pembelian (GR diverifikasi) | ✅ Ada | `InventoriJurnalService::catatPembelian()` — Debit Persediaan (1-1300) / Kredit Hutang Dagang (2-1100). |
| Modul pelunasan hutang dagang ke supplier | ❌ Belum ada | Dikonfirmasi juga di `modul_akuntansi.md` §13 — akun `2-1100` murni saldo COA, tidak ada ledger per-faktur supplier. **Konsekuensi**: retur ke supplier cukup reversal saldo COA, tidak perlu rekonsiliasi ke "faktur mana". |
| `po_item.jumlah_diterima` | ✅ Ada | Dipakai hitung sisa pesanan (`jumlah_pesan - jumlah_diterima`). Retur barang ke supplier logisnya **mengembalikan** angka ini (barang yang diretur berarti belum benar-benar "diterima final"). |
| Resep — potong stok | ⚠️ Ada, tapi tanpa jejak | `ResepFarmasi::konfirmasi()` (`app/Livewire/Farmasi/ResepFarmasi.php`) memotong stok langsung saat resep `is_locked=true`, **tanpa membuat baris `MutasiStok`** (tipe `keluar_resep` sudah ada di enum, tapi tidak pernah dipakai!) — lihat §5 Gap #1. |
| Resep — batal sebelum lunas | ✅ Ada | `batalkanKonfirmasi()` mengembalikan stok & unlock resep, tapi **diblokir kalau invoice sudah `lunas`** — pesan error "Resep tidak dapat dibatalkan karena billing sudah lunas." Inilah persis celah yang diisi PRD ini (Fitur B). |
| Invoice ← Resep | ✅ Ada | `InvoiceService::buildItems()` — resep masuk invoice sebagai `invoice_item` (jenis `obat`/`racikan`) hanya kalau `resep.is_locked = true`, dengan `ref_id` menunjuk ke `item_resep.id` atau `racikan.id`. |
| Pembatalan invoice lunas (`BillingService::batalkanBilling()`) | ✅ Ada | Password SuperAdmin + alasan wajib, refund deposit (kalau dipakai), reversal jurnal generik (`JurnalService::reversal()`). **Tapi ini membatalkan SELURUH invoice**, bukan sebagian item — tidak cocok untuk retur 1-2 obat dari resep berisi banyak item. |
| Ritel — batalkan setelah dibayar | ❌ Belum ada | `TransaksiRitel::bisaDibatalkan()` hanya `true` untuk status `draft`/`menunggu_kasir`. Di luar scope PRD ini (resep ≠ ritel), disebutkan sebagai referensi pola saja. |
| Deposit pasien (`DepositService`) | ✅ Ada | `topup()`/`refund()` — tiap perubahan saldo tercatat di `transaksi_deposit` + jurnal otomatis (Debit Kas / Kredit Titipan Deposit saat topup). **Catatan penting**: `topup()` selalu menjurnal Debit **Kas** — tidak cocok dipakai mentah-mentah untuk "retur dikonversi ke deposit" karena di kasus itu tidak ada uang tunai yang benar-benar masuk (lihat §4.4). |

### 1.2 Alur Data

```
┌────────────────────────────────────────────────────────────────────────────┐
│ FITUR A — Retur ke Supplier                                                │
│                                                                              │
│  GR (status: diverifikasi, barang sudah di stok)                          │
│        │                                                                   │
│        ▼                                                                   │
│  Buat Retur GR (draft) → pilih GR Item & jumlah ──► retur_gr/retur_gr_item │
│        │                                                                   │
│        ▼                                                                   │
│  Verifikasi Retur ──► stok berkurang (MutasiStok: retur_ke_supplier)      │
│        │              po_item.jumlah_diterima berkurang                   │
│        │              Jurnal: Debit Hutang Dagang / Kredit Persediaan     │
│        ▼                                                                   │
│  Status: diverifikasi (final) — atau "dibatalkan" kalau draft batal       │
└────────────────────────────────────────────────────────────────────────────┘

┌────────────────────────────────────────────────────────────────────────────┐
│ FITUR B — Retur Resep Sehari                                               │
│                                                                              │
│  Resep is_locked=true, Invoice status=lunas, dilunasi HARI INI            │
│        │                                                                   │
│        ▼                                                                   │
│  Buat Retur Resep → pilih item resep & jumlah ──► retur_resep/...item     │
│        │  (validasi: masih hari yang sama, kas aktif jika refund tunai)   │
│        ▼                                                                   │
│  Proses langsung (one-shot, tidak ada draft — lihat §9):                  │
│    • Stok bertambah (MutasiStok: retur_resep, HPR tidak berubah)         │
│    • invoice_item terkait dikurangi/dihapus, Invoice direkalkulasi        │
│    • Kelebihan bayar dikembalikan: tunai/bank (pembayaran_split negatif) │
│      atau dikonversi ke deposit pasien (saldo deposit bertambah)         │
│    • Jurnal: Debit Pendapatan Obat/Racikan / Kredit Kas-Bank-atau-Deposit│
│        │                                                                   │
│        ▼                                                                   │
│  ItemResep ASLI tidak diubah (riwayat medis tetap utuh)                   │
└────────────────────────────────────────────────────────────────────────────┘
```

---

## 2. Tujuan & Non-Tujuan

### Tujuan
- Apoteker/Admin Inventory dapat meretur barang yang ternyata rusak/salah kirim/kualitas buruk dari GR yang **sudah diverifikasi** (sudah menambah stok & hutang dagang), tanpa mengedit data GR/PO asli.
- Stok, hutang dagang (saldo COA), dan jurnal otomatis ikut terkoreksi secara konsisten saat retur ke supplier diverifikasi.
- Kasir/Apoteker dapat memproses retur sebagian atau seluruh obat dari resep yang **sudah dibayar lunas**, **selama masih di hari yang sama** dengan pelunasannya — dengan pengembalian dana (tunai/bank) atau dikonversi ke saldo deposit pasien.
- Riwayat resep yang **diresepkan dokter** (rekam medis) tidak pernah berubah akibat retur — yang berubah hanya sisi stok & finansial.

### Non-Tujuan
- Tidak menangani retur untuk **Ritel** (`TransaksiRitel`) — itu sudah punya jalurnya sendiri (`bisaDibatalkan()`), dan di luar 2 skenario yang diminta.
- Tidak menangani retur lintas-hari untuk resep (misal pasien balik 3 hari kemudian minta retur) — itu butuh kebijakan bisnis berbeda (kemungkinan harus lewat jalur komplain/CS, bukan kasir langsung), lihat §9 & §11.
- Tidak membangun modul pelunasan/rekonsiliasi hutang dagang per-faktur supplier (tetap di luar scope sesuai `modul_akuntansi.md` §13).
- Tidak menambahkan jurnal HPP untuk resep (baik dispensing maupun retur) — karena dispensing resep saat ini **juga tidak punya** jurnal HPP (beda dengan Ritel yang punya), lihat §9.

---

## 3. Fitur A — Retur Barang ke Supplier (dari GR)

### 3.1 Kapan Bisa Diretur

- GR harus berstatus **`diverifikasi`** (barang sudah resmi di stok — kalau GR masih `draft`, batalkan saja pakai `PenerimaanService::batalkanGr()` yang sudah ada, tidak butuh fitur retur).
- Retur dilakukan **per item GR** (`gr_item`), bisa sebagian dari `jumlah_terima` (misal terima 100, ternyata 10 rusak, retur 10 saja), dan **bisa bertahap** (retur lagi di kemudian hari kalau ditemukan kerusakan baru) — sistem menjaga akumulasi supaya total retur tidak pernah melebihi `jumlah_terima` per `gr_item`.
- Stok fisik barang yang mau diretur **harus masih cukup** di gudang (kalau sudah terjual/terpakai duluan, tidak bisa diretur lagi — divalidasi pakai `Barang::pastikanCukup()` yang sudah ada).

### 3.2 Alur Draft → Verifikasi (konsisten dengan GR/BHP/Opname)

1. **Buat Draft** — pilih GR (yang `diverifikasi`), pilih item & jumlah retur, isi alasan (rusak/salah kirim/kualitas/lainnya). Belum mengubah stok/jurnal apa pun.
2. **Verifikasi** — efek baru terjadi di sini:
   - Validasi stok cukup per item (`Barang::pastikanCukup()`).
   - Stok berkurang, `MutasiStok` tipe `retur_ke_supplier` tercatat (HPR **tidak berubah** — lihat §9.1).
   - `po_item.jumlah_diterima` dikurangi sejumlah retur (kalau GR berasal dari PO) — supaya sisa pesanan PO kembali akurat.
   - Jurnal otomatis dicatat (lihat §3.3).
   - Status retur → `diverifikasi` (final, tidak bisa diedit lagi — selaras keputusan desain existing "tidak bisa edit nominal, kalau salah buat baru").
3. **Batalkan draft** — kalau retur masih draft dan ternyata tidak jadi, cukup ubah status jadi `dibatalkan`, tidak ada efek yang perlu dibalik (belum pernah menyentuh stok/jurnal).

### 3.3 Jurnal Retur ke Supplier

Reversal langsung dari pemetaan pembelian (`InventoriJurnalService::catatPembelian()`), dengan sisi dibalik:

| Akun Debit | Akun Kredit | Nominal |
|---|---|---|
| Hutang Dagang (2-1100) | Persediaan Barang (1-1300) | `jumlah_retur × harga_efektif` (harga & diskon sama seperti tercatat di `gr_item` asal) |

> Dicatat via `JurnalService::catat()` langsung (sumber baru `retur_gr`), **bukan** lewat `JurnalService::reversal()` — karena retur GR adalah transaksi **baru dan independen** (bisa sebagian, bisa berkali-kali), bukan pembalikan utuh satu transaksi seperti pembatalan billing.

### 3.4 Komponen

- `Inventory\ReturGr\ReturGrForm` — pilih GR diverifikasi → tampil daftar `gr_item` beserta "sisa bisa diretur" (`jumlah_terima - total sudah diretur sebelumnya`) → input jumlah & alasan per item.
- `Inventory\ReturGr\ReturGrTable` — daftar retur (nomor, GR asal, supplier, tanggal, status, total nilai), filter tanggal/status/supplier, paging 10/halaman (konsisten dengan pola tabel lain di app).

---

## 4. Fitur B — Retur Resep Sehari

### 4.1 Kapan Bisa Diretur

Semua kondisi berikut harus terpenuhi:
1. Resep berstatus `is_locked = true` (sudah dikonfirmasi apoteker, stok sudah terpotong).
2. Invoice terkait kunjungan berstatus **`lunas`**.
3. **Masih di hari yang sama** dengan pelunasan invoice — dicek dari tanggal pembayaran terakhir (`PembayaranSplit.tanggal_bayar` paling baru untuk invoice itu) dibandingkan `today()`. Lewat tengah malam → tidak bisa lagi lewat fitur ini (lihat §9.4 untuk alasan & alternatifnya).
4. Item yang diretur masih ada **sisa kuantitas** yang belum pernah diretur sebelumnya (jumlah retur kumulatif per `item_resep` ≤ `item_resep.jumlah`).

### 4.2 Alur — One-Shot (Bukan Draft→Verifikasi)

Berbeda dari Fitur A, retur resep diproses **langsung sekali jalan** saat disubmit (lihat alasan di §9.3):

1. Kasir/Apoteker cari kunjungan/resep yang memenuhi §4.1, pilih item & jumlah yang diretur, pilih **metode pengembalian** (Tunai/Bank atau Konversi ke Deposit Pasien), isi alasan.
2. Kalau metode Tunai/Bank: validasi ada sesi kas aktif milik kasir yang login (pola sama dengan `ObatRitelService::prosesBayar()`).
3. Sistem memproses dalam satu transaksi database:
   - Stok barang bertambah sejumlah retur (HPR **tidak berubah**), `MutasiStok` tipe `retur_resep` tercatat.
   - `invoice_item` yang berkaitan (`ref_id` = `item_resep.id` atau `racikan.id`) dikurangi `qty`/`subtotal`-nya secara proporsional, atau dihapus seluruhnya kalau retur penuh. Invoice direkalkulasi total via `InvoiceService::recalcTotal()` yang sudah ada.
   - Hitung **kelebihan bayar** = `total_bayar` invoice (sebelum retur) − `total_tagihan` baru (sesudah retur).
   - Proses pengembalian dana sesuai metode (lihat §4.4).
   - Jurnal otomatis dicatat (lihat §4.4).
4. `ItemResep` (rekam medis resep asli dari dokter) **tidak diubah sama sekali** — yang dicatat hanya di `retur_resep_item` sebagai entri baru yang mereferensikannya. Resep tetap `is_locked = true` (tidak di-unlock — beda dari `batalkanKonfirmasi()` yang unlock total).

### 4.3 Validasi Tambahan

- Resep yang sudah pernah diretur penuh untuk SEMUA itemnya tidak bisa diretur lagi (tidak ada sisa).
- Kalau invoice memmakai deposit sebagian (`total_deposit_dipakai > 0`), kelebihan bayar akibat retur **diprioritaskan dikembalikan ke deposit dulu** sebelum tunai (opsional — default tetap ikut pilihan kasir, ini hanya saran UX, bukan validasi keras).

### 4.4 Pengembalian Dana & Jurnal

| Metode | Efek Non-Jurnal | Jurnal (Debit → Kredit) |
|---|---|---|
| **Tunai/Bank** | Baris baru di `pembayaran_split` dengan `jumlah` **negatif** (`-total_nilai_retur`), `metode` sama seperti pembayaran asal, `referensi` = `retur_resep:{id}` — supaya otomatis ikut terhitung di rekap Sesi Kas (`SesiKasPanel::rekapSesi()`) tanpa perlu ubah query rekap yang sudah ada (`SUM(jumlah)` otomatis mengurangi). | Pendapatan Obat/Racikan (4-1300) → Kas (1-1100) atau Bank (1-1200) sesuai metode |
| **Konversi ke Deposit** | `DepositPasien.saldo` bertambah, `TransaksiDeposit` baru (`tipe: topup`, `referensi_tipe: retur_resep`) — **dibuat langsung**, bukan lewat `DepositService::topup()` (lihat alasan di bawah) | Pendapatan Obat/Racikan (4-1300) → Titipan Deposit Pasien (2-1300) |

> **Kenapa tidak langsung pakai `DepositService::topup()` untuk opsi konversi deposit?** `topup()` selalu menjurnal `Debit Kas / Kredit Titipan Deposit` — itu benar untuk topup *sungguhan* (ada uang fisik masuk), tapi pada retur-ke-deposit **tidak ada uang fisik yang berpindah**, hanya pendapatan yang dikoreksi jadi saldo titipan. `ReturResepService` akan membuat baris `DepositPasien`/`TransaksiDeposit` secara langsung (logika incrementnya identik dengan `topup()`, disalin bukan dipanggil), lalu mencatat jurnal yang BENAR sendiri (Debit Pendapatan, bukan Debit Kas).

> **Tidak ada jurnal HPP/Persediaan untuk retur resep** — karena resep dispensing saat ini juga tidak mencatat jurnal HPP (gap terpisah, lihat §5 & §9.5). Menambahkan jurnal HPP hanya di sisi retur tanpa ada di sisi keluarnya akan membuat Persediaan Barang di Neraca jadi tidak konsisten (nilai persediaan akan naik dari retur padahal tidak pernah turun saat resep keluar). Ditutup bersamaan kalau gap itu nanti dikerjakan (lihat §11).

### 4.5 Komponen

- `Farmasi\ReturResep\ReturResepForm` — cari kunjungan/resep (mirip pencarian di `TagihanPasien`), tampil item resep beserta "sisa bisa diretur", pilih metode pengembalian, ringkasan kelebihan bayar real-time.
- `Farmasi\ReturResep\ReturResepTable` — riwayat retur resep (nomor, pasien, tanggal, item, nilai, metode pengembalian, diproses oleh), filter tanggal default hari ini, paging 10/halaman.

---

## 5. Gap yang Harus Ditutup Lebih Dulu

Dua hal ini **bukan** fitur retur itu sendiri, tapi prasyarat supaya Fitur B punya jejak data yang lengkap & konsisten:

1. **`MutasiStok` untuk pemotongan stok resep** — `ResepFarmasi::konfirmasi()` saat ini memotong stok (`$barang->decrement('stok', ...)`) **tanpa** mencatat `MutasiStok` (tipe `keluar_resep` sudah ada di enum sejak awal tapi tidak terpakai). Harus ditambahkan sebagai bagian dari PRD ini (walau bukan "fitur retur" secara langsung) — tanpa ini, riwayat kartu stok barang yang resepnya diretur akan tampak aneh: stok bertambah lewat `retur_resep` tanpa pernah terlihat berkurang sebelumnya lewat `keluar_resep`.
2. **`batalkanKonfirmasi()` di `ResepFarmasi.php`** — perlu disesuaikan pesannya/dicek ulang supaya jelas membedakan dua jalur: "batalkan sebelum lunas" (existing, unlock total resep) vs "retur sesudah lunas" (PRD ini, partial, resep tetap locked). Tidak mengubah logika `batalkanKonfirmasi()` itu sendiri, hanya pastikan UI mengarahkan ke jalur yang benar sesuai status invoice.

---

## 6. Skema Database

### 6.1 ALTER `mutasi_stok` — tambah 1 nilai enum

```php
Schema::table('mutasi_stok', function (Blueprint $table) {
    $table->enum('tipe', [
        'masuk_pembelian', 'keluar_resep', 'keluar_tindakan', 'keluar_bhp', 'keluar_ritel',
        'penyesuaian_masuk', 'penyesuaian_keluar', 'retur_ke_supplier', 'retur_resep', 'expired',
    ])->change();
});
```

### 6.2 Tabel Baru `retur_gr` (Fitur A — header)

| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| nomor_retur | string(30) unique | format `RGR-YYYYMM-XXXX` |
| goods_receipt_id | FK `goods_receipt` | |
| supplier_id | FK `supplier` | salin dari GR untuk query cepat |
| tanggal_retur | date | |
| alasan | string(100) | rusak / salah_kirim / kualitas_buruk / lainnya |
| catatan | text nullable | |
| status | enum | `draft`, `diverifikasi`, `dibatalkan` |
| total_nilai | decimal(16,2) | sum subtotal item |
| dibuat_oleh | FK `users` | |
| diverifikasi_oleh | FK `users` nullable | |
| diverifikasi_pada | timestamp nullable | |
| timestamps | | |

### 6.3 Tabel Baru `retur_gr_item` (Fitur A — detail)

| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| retur_gr_id | FK `retur_gr` cascade | |
| gr_item_id | FK `gr_item` | |
| barang_id | FK `barang` | |
| jumlah_retur | unsigned int | |
| harga_satuan | decimal(14,2) | disalin dari `gr_item.harga_satuan` saat draft dibuat |
| diskon_persen | decimal(5,2) | disalin dari `gr_item` |
| subtotal | decimal(14,2) | `jumlah_retur × harga_efektif` |

### 6.4 Tabel Baru `retur_resep` (Fitur B — header)

| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| nomor_retur | string(30) unique | format `RRX-YYYYMMDD-XXXX` |
| resep_id | FK `resep` | |
| kunjungan_id | FK `kunjungan` | salin untuk query cepat |
| billing_id | FK `billing` (Invoice) | |
| tanggal_retur | date | selalu hari ini (one-shot) |
| alasan | string(100) | salah_resep / pasien_menolak / reaksi_alergi / lainnya |
| catatan | text nullable | |
| metode_pengembalian | enum | `tunai`, `bank`, `deposit` |
| total_nilai_retur | decimal(16,2) | sum subtotal item retur (= kelebihan bayar yang dikembalikan) |
| sesi_kas_id | FK `sesi_kas` nullable | wajib diisi kalau metode tunai/bank |
| diproses_oleh | FK `users` | |
| timestamps | | |

> Tidak ada kolom `status` (selalu "selesai" begitu tercatat — proses one-shot, lihat §9.3). Pembatalan/kesalahan input ditangani manual oleh Admin/SuperAdmin lewat akses database/koreksi manual jika sangat jarang terjadi (sama prinsipnya dengan "tidak bisa edit jurnal pending, kalau salah buat penyesuaian baru").

### 6.5 Tabel Baru `retur_resep_item` (Fitur B — detail)

| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| retur_resep_id | FK `retur_resep` cascade | |
| item_resep_id | FK `item_resep` nullable | nullable karena retur bisa juga dari racikan |
| racikan_id | FK `racikan` nullable | salah satu dari `item_resep_id`/`racikan_id` wajib terisi |
| barang_id | FK `barang` | untuk item racikan, ini bahan racikan yang diretur (lihat §9.6 — racikan diretur sebagai satu kesatuan, tidak per-bahan) |
| jumlah_retur | decimal(8,2) | |
| harga_satuan | decimal(14,2) | |
| subtotal | decimal(14,2) | |

---

## 7. Model, Service, & Komponen

### 7.1 Model

| Model | Tabel | Catatan |
|---|---|---|
| `App\Models\ReturGr` | `retur_gr` | `belongsTo(GoodsReceipt)`, `belongsTo(Supplier)`, `hasMany(ReturGrItem)`, `belongsTo(User, 'dibuat_oleh')` |
| `App\Models\ReturGrItem` | `retur_gr_item` | `belongsTo(GrItem)`, `belongsTo(Barang)` |
| `App\Models\ReturResep` | `retur_resep` | `belongsTo(Resep)`, `belongsTo(Kunjungan)`, `belongsTo(Invoice, 'billing_id')`, `hasMany(ReturResepItem)` |
| `App\Models\ReturResepItem` | `retur_resep_item` | `belongsTo(ItemResep)`, `belongsTo(Racikan)`, `belongsTo(Barang)` |

### 7.2 Service

| Service | Tanggung Jawab |
|---|---|
| `App\Services\Inventory\ReturGrService.php` | `buatDraft()`, `tambahItem()`/`hapusItem()`, `hitungSisaBisaDiretur(GrItem $item): int`, `verifikasi()` (stok, MutasiStok, po_item, jurnal — semua dalam 1 `DB::transaction`), `batalkanDraft()`. |
| `App\Services\Akuntansi\InventoriJurnalService.php` (extend) | Tambah `catatReturSupplier(ReturGr $retur)` — Debit Hutang Dagang / Kredit Persediaan (§3.3). |
| `App\Services\Farmasi\ReturResepService.php` | `hitungSisaBisaDiretur(ItemResep $item): float`, `cekBolehRetur(Resep $resep): bool` (validasi §4.1), `proses(array $data, int $userId): ReturResep` — satu method one-shot yang menjalankan seluruh langkah §4.2 dalam 1 `DB::transaction`. |
| `App\Services\Akuntansi\ReturResepJurnalService.php` (baru) | `catatRetur(ReturResep $retur)` — hitung proporsi kategori (obat/racikan) mirip `BillingJurnalService::hitungProporsiKategori()`, lalu Debit Pendapatan / Kredit Kas-Bank-atau-Deposit Titipan sesuai `metode_pengembalian` (§4.4). |
| `App\Services\Inventory\PenerimaanService.php` / `App\Livewire\Farmasi\ResepFarmasi.php` (extend, lihat §5) | Tambah pencatatan `MutasiStok` tipe `keluar_resep` di `konfirmasi()` — prasyarat Fitur B. |

### 7.3 Livewire Components & Routes

| Route | Komponen | Permission |
|---|---|---|
| `GET /inventory/retur-gr` | `Inventory\ReturGr\ReturGrTable` | `retur_gr.view` |
| `GET /inventory/retur-gr/create` | `Inventory\ReturGr\ReturGrForm` | `retur_gr.create` |
| `GET /inventory/retur-gr/{id}/edit` | `Inventory\ReturGr\ReturGrForm` (mode edit draft + verifikasi) | `retur_gr.create` (edit draft), `retur_gr.verifikasi` (tombol verifikasi) |
| `GET /farmasi/retur-resep` | `Farmasi\ReturResep\ReturResepTable` | `resep.retur.view` |
| `GET /farmasi/retur-resep/create` | `Farmasi\ReturResep\ReturResepForm` | `resep.retur.create` |

Sidebar: sub-menu **"Retur ke Supplier"** di grup Inventory (sejajar Purchase Order/GR), dan **"Retur Resep"** di grup Farmasi (sejajar menu Resep/Apotek).

---

## 8. Role & Hak Akses

| Permission | Deskripsi | Role Disarankan |
|---|---|---|
| `retur_gr.view` | Lihat daftar retur ke supplier | Admin, Apoteker, Inventory |
| `retur_gr.create` | Buat & edit draft retur ke supplier | Admin, Apoteker, Inventory |
| `retur_gr.verifikasi` | Verifikasi retur (efek ke stok/jurnal) | Admin, Apoteker Senior |
| `resep.retur.view` | Lihat riwayat retur resep | Admin, Apoteker, Kasir |
| `resep.retur.create` | Proses retur resep (termasuk refund) | Admin, Apoteker, Kasir |

> Sengaja **tidak** memisahkan `create`/`verifikasi` untuk retur resep seperti retur GR — karena prosesnya one-shot dan butuh selesai saat pasien masih di tempat (lihat §9.3), pemisahan maker-checker akan menghambat operasional. Kontrolnya cukup lewat: wajib sesi kas aktif (untuk refund tunai/bank) + audit trail `diproses_oleh` + log activity.

---

## 9. Keputusan Desain & Batasan yang Disengaja

1. **HPR tidak dihitung ulang (reversed) saat retur** — baik retur ke supplier maupun retur resep. HPR adalah rata-rata tertimbang yang sudah "bercampur" dengan pergerakan stok lain sejak barang itu masuk; membalikkannya secara presisi tidak feasible (dan tidak dilakukan juga oleh modul lain saat stok keluar — Ritel & BHP juga tidak mengubah HPR saat stok keluar). Retur hanya menambah/mengurangi **kuantitas** stok di HPR yang berlaku **saat ini**.
2. **Retur ke supplier dicatat sebagai transaksi baru (`JurnalService::catat()`), bukan reversal dari jurnal pembelian asli (`JurnalService::reversal()`)** — karena retur bisa parsial dan bisa terjadi berkali-kali dari satu GR, sementara `reversal()` didesain untuk membalik **utuh** satu transaksi sumber. Konsekuensinya: retur GR **tidak tunduk pada gate Tutup Periode** dengan cara yang sama seperti reversal billing/jurnal manual (lihat `modul_akuntansi_update.md` §3) — ia tunduk sebagai jurnal baru biasa, diposting lewat Jurnal Pending seperti jurnal otomatis lainnya.
3. **Retur GR: draft→verifikasi (2 langkah). Retur Resep: one-shot (1 langkah).** Alasan perbedaan: retur ke supplier biasanya butuh waktu (cek fisik barang, hubungi supplier, dokumentasi) sehingga cocok dengan pola draft yang sudah dipakai PO/GR/BHP/Opname. Retur resep terjadi spontan saat pasien masih di depan kasir/apoteker hari itu juga — mewajibkan draft→verifikasi terpisah hanya menambah friksi tanpa manfaat (tidak ada "pengecekan fisik" yang perlu ditunda, obat fisik sudah ada di tangan pasien/apoteker saat itu).
4. **Retur resep dibatasi ketat ke "hari yang sama"** — sesuai permintaan eksplisit. Kalau pasien baru menyadari masalah di hari berikutnya, fitur ini **tidak** bisa dipakai (lihat §11) — alasan: (a) mencegah penyalahgunaan retur sebagai jalur "refund kapan saja" yang sulit dikontrol; (b) sesi kas & rekap kasir harian jadi rumit kalau refund tunai bisa menembus sesi kas yang sudah ditutup; (c) selaras dengan prinsip "hari yang sama" yang sudah dipakai kasir untuk sesi kas (`SesiKasService::getSesiAktif()` di-scope `whereDate('tanggal', today())`).
5. **Tidak ada jurnal HPP untuk retur resep** — karena dispensing resep saat ini juga tidak mencatat jurnal HPP (lihat §5 dan §11; berbeda dengan Ritel yang punya `RitelJurnalService::catatHpp()`). Menutup gap ini sekaligus bukan scope PRD ini agar tidak melebar — dicatat sebagai item untuk PRD/fase mendatang.
6. **Racikan diretur sebagai satu kesatuan, bukan per-bahan** — kalau pasien menolak racikan, seluruh racikan itu yang diretur (qty bahan-bahannya dikembalikan ke stok masing-masing sesuai resep racikan asal), bukan partial bahan tertentu saja. Ini karena racikan adalah satu produk jadi (puyer/kapsul/dst) yang sudah dicampur — secara fisik tidak bisa "diretur sebagian bahan".

---

## 10. Fase Implementasi

| Fase | Lingkup | Estimasi |
|---|---|---|
| **Fase 1 — Gap Prasyarat** | Tambah `MutasiStok` tipe `keluar_resep` di `ResepFarmasi::konfirmasi()` (§5) | 1 hari |
| **Fase 2 — Retur ke Supplier (Fondasi)** | Migration `retur_gr`/`retur_gr_item`, ALTER `mutasi_stok` enum, model, `ReturGrService` (draft + tambah/hapus item) | 2–3 hari |
| **Fase 3 — Retur ke Supplier (Verifikasi & UI)** | `ReturGrService::verifikasi()` (stok, po_item, jurnal), `InventoriJurnalService::catatReturSupplier()`, `ReturGrForm` + `ReturGrTable`, permission & sidebar | 3–4 hari |
| **Fase 4 — Retur Resep (Fondasi)** | Migration `retur_resep`/`retur_resep_item`, model, `ReturResepService` (validasi §4.1, hitung sisa bisa diretur) | 2 hari |
| **Fase 5 — Retur Resep (Proses & Jurnal)** | `ReturResepService::proses()` one-shot, `ReturResepJurnalService`, refund tunai/bank via `pembayaran_split` negatif, refund deposit, `ReturResepForm` + `ReturResepTable`, permission & sidebar | 4–5 hari |

---

## 11. Out of Scope

- Retur resep lintas-hari (di luar hari pelunasan) — kalau dibutuhkan di masa depan, kemungkinan butuh alur berbeda (approval Admin/Owner, tanpa refund tunai langsung dari sesi kas harian, mungkin lewat kredit/voucher).
- Retur untuk transaksi Ritel (`TransaksiRitel`) — sudah punya jalur sendiri, tidak disentuh PRD ini.
- Modul pelunasan/rekonsiliasi hutang dagang per-faktur supplier — tetap di luar scope (`modul_akuntansi.md` §13).
- Jurnal HPP untuk dispensing/retur resep — menutup gap ini butuh PRD/keputusan desain tersendiri (apakah HPP resep mau disamakan dengan pola Ritel), di luar scope PRD ini.
- Retur barang ke supplier untuk meminta **penggantian barang** (replacement) secara otomatis membuat PO baru — retur ini murni mengurangi stok & hutang; kalau butuh barang pengganti, PO baru dibuat manual lewat modul PO yang sudah ada.
- Approval/notifikasi ke supplier (email/WA) saat retur diverifikasi — murni pencatatan internal.
- Cetak surat retur/bukti retur formal (PDF) — bisa ditambahkan sebagai fase lanjutan kecil kalau dibutuhkan untuk dokumentasi fisik ke supplier.

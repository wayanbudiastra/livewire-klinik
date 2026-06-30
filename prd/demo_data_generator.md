# Product Requirements Document (PRD)
# Modul Demo Data Generator — Super Admin

| Info | Detail |
|:-----|:-------|
| **Versi** | 1.0.0 |
| **Tanggal** | Juni 2026 |
| **Status** | Draft |
| **Depends On** | `manajemen_inventory.md` · `obat_ritel.md` · `modul_akuntansi.md` |
| **Tech Stack** | Laravel 12 · Livewire 3 · MySQL · Tailwind CSS |
| **Scope** | Generate transaksi PO+GRN dan Penjualan Ritel beserta jurnal akuntansinya untuk keperluan demo ke klien |
| **Lokasi Menu** | Pengaturan → Demo Data Generator (hanya terlihat oleh `super_admin`) |

---

## Daftar Isi

1. [Latar Belakang & Tujuan](#1-latar-belakang--tujuan)
2. [Alur Kerja](#2-alur-kerja)
3. [Form Input Generator](#3-form-input-generator)
4. [Logika Generate PO + GRN](#4-logika-generate-po--grn)
5. [Logika Generate Penjualan Ritel](#5-logika-generate-penjualan-ritel)
6. [Logika Generate Jurnal Akuntansi](#6-logika-generate-jurnal-akuntansi)
7. [Fitur Reset / Hapus Data Demo](#7-fitur-reset--hapus-data-demo)
8. [Skema Data & Dampak](#8-skema-data--dampak)
9. [Service & Komponen](#9-service--komponen)
10. [Role & Hak Akses](#10-role--hak-akses)
11. [Business Rules & Validasi](#11-business-rules--validasi)
12. [UI / UX](#12-ui--ux)
13. [Fase Implementasi](#13-fase-implementasi)
14. [Out of Scope](#14-out-of-scope)

---

## 1. Latar Belakang & Tujuan

Saat presentasi demo ke calon klien, sistem harus memperlihatkan data transaksi yang realistis — grafik yang bergerak, laporan yang terisi, neraca yang seimbang. Mengisi data manual atau mengandalkan seeder CLI tidak praktis saat di lapangan.

**Modul ini menyediakan UI generate data transaksi on-demand**, khusus untuk:
- Demo di depan klien tanpa menyentuh terminal/Artisan
- Menghasilkan data yang konsisten & realistis (rata-rata harian sesuai target)
- Dapat direset dengan sekali klik setelah demo selesai

**Non-tujuan**: Modul ini bukan untuk mengisi data produksi nyata. Tidak ada tabel atau kolom khusus "demo" — data yang dihasilkan identik dengan data asli dari sisi struktur, sehingga semua laporan, grafik, dan modul lain merespons seolah data tersebut nyata.

---

## 2. Alur Kerja

```
┌─────────────────────────────────────────────────────────────────┐
│                  DEMO DATA GENERATOR                             │
│                                                                  │
│  1. Super Admin buka halaman Generator                           │
│  2. Isi form: tanggal mulai-selesai, target rata-rata/hari       │
│  3. Centang jenis data: ☑ PO+GRN  ☑ Penjualan Ritel             │
│  4. Klik "Generate" → progress bar realtime                      │
│  5. Setelah selesai: ringkasan hasil ditampilkan                  │
│  6. Semua laporan (Laba Rugi, Neraca, Jurnal) langsung terisi    │
│                                                                  │
│  Reset: Klik "Hapus Data" → isi tanggal range → konfirmasi       │
│         → semua data dalam range dihapus bersih                  │
└─────────────────────────────────────────────────────────────────┘
```

---

## 3. Form Input Generator

### 3.1 Field Input

| Field | Tipe | Validasi | Default | Keterangan |
|-------|------|----------|---------|------------|
| Tanggal Mulai | date | required, tidak boleh masa depan | - | Tanggal awal generate |
| Tanggal Selesai | date | required, ≥ tanggal mulai, max selisih 10 hari | - | Tanggal akhir generate |
| Target Rata-rata PO+GRN / hari | integer (Rp) | required jika generate PO, min 1.000.000 | 10.000.000 | Nilai pembelian total per hari |
| Target Rata-rata Ritel / hari | integer (Rp) | required jika generate Ritel, min 500.000 | 5.000.000 | Omzet penjualan ritel per hari |
| Generate PO + GRN | checkbox | min 1 harus dicentang | ✓ | |
| Generate Penjualan Ritel | checkbox | min 1 harus dicentang | ✓ | |

### 3.2 Peringatan Konflik Tanggal

Sebelum generate, sistem memeriksa apakah sudah ada data di rentang tanggal tersebut:

```
⚠️  Ditemukan data yang sudah ada pada rentang tanggal ini:
    - 24 PO/GRN (total Rp 240.000.000)
    - 180 transaksi ritel (total Rp 90.000.000)

    Generate ulang akan MENGHAPUS data tersebut terlebih dahulu.
    [Lanjutkan & Ganti]  [Batal]
```

> Ini bukan error — user bisa memilih untuk mengganti data lama dengan yang baru (idempoten).

---

## 4. Logika Generate PO + GRN

### 4.1 Pola Kelompok Barang (4 Kelompok Berputar)

Setiap hari generate **2 PO** dari 2 kelompok yang berbeda secara bergantian:

| Kelompok | Isi | Supplier |
|----------|-----|----------|
| K0 — Obat Rutin | Paracetamol, Amoxicillin, Ibuprofen, Antasida, Cetirizine, Omeprazole, Metformin, Amlodipine | supplier_id=1 |
| K1 — Alkes Consumable | Spuit 3ml, Spuit 5ml, Jarum 23G, Kassa Steril, Kapas Alkohol, Lancet, Alkohol 70%, SurgBlade11, SurgBlade15 | supplier_id=2 |
| K2 — Alkes Premium | Sarung Tangan Nitrile, Masker Bedah, Strip GDS, Elektroda EKG, Benang Jahit Silk, Benang Jahit Catgut | supplier_id=4 |
| K3 — Cairan & Infus | Cairan RL, Cairan NaCl, Infus Set Dewasa, Infus Set Anak, Abocath 18, Abocath 20, Urine Bag, IV Set | supplier_id=3 |

**Rotasi harian**: Hari ke-1 → K0+K1, Hari ke-2 → K2+K3, Hari ke-3 → K0+K1, dst.

### 4.2 Kalibrasi Kuantitas ke Target

Untuk setiap PO, kuantitas masing-masing item dihitung agar total PO mendekati `target_harian / 2`:

```
target_per_PO = target_rata2_harian / 2
qty_base[item] = round(target_per_PO / jumlah_item / harga_pokok[item])
qty_final[item] = qty_base[item] × random_factor(0.85 – 1.15)   # variasi ±15%
```

> Variasi ±15% membuat data tidak terlihat "terlalu sempurna" dan memberikan pola grafik yang natural.

### 4.3 Markup Harga Beli

```
harga_beli_per_item = harga_pokok × 1.04   # markup 4% (biaya distribusi)
```

### 4.4 Alur Per Hari

Untuk setiap hari dalam rentang:
1. Pilih 2 kelompok sesuai rotasi
2. Buat 1 `purchase_order` per kelompok:
   - `nomor_po`: format `PO-YYYY-MM-{4 digit urut baru per bulan}`
   - `status`: `selesai`
   - `tanggal_po`: tanggal hari ini
3. Buat 1 `goods_receipt` per PO:
   - `nomor_gr`: format `GR-YYYY-MM-{4 digit urut baru per bulan}`
   - `status`: `diverifikasi`
   - `tanggal_terima`: sama dengan `tanggal_po`
   - `jumlah_diterima` = `jumlah_pesan` (fully received)
4. Buat `gr_item` untuk setiap barang
5. Buat `mutasi_stok` tipe `masuk_pembelian` untuk setiap item
6. Buat `po_item` untuk setiap barang
7. Update `harga_pokok` barang (moving average) + update `stok`

---

## 5. Logika Generate Penjualan Ritel

### 5.1 Pola Pembelian (8 Template)

Setiap hari generate **26 transaksi** dari 8 pola yang diacak:

| Template | Isi | Estimasi per Transaksi |
|----------|-----|------------------------|
| kronik_diabetes | Metformin×30, Amlodipine×30, Omeprazole×30, Cetirizine×15 | ~360.000 |
| kronik_hipertensi | Amlodipine×30, Omeprazole×30, Cetirizine×30, Paracetamol×30 | ~405.000 |
| kronik_campuran | Metformin×30, Amlodipine×20, Cetirizine×20, Paracetamol×30 | ~245.000 |
| infeksi | Amoxicillin×15, Paracetamol×20, Ibuprofen×15, Antasida×15, Cetirizine×5 | ~155.000 |
| batuk_pilek | Paracetamol×20, Cetirizine×15, Ibuprofen×10, Antasida×10 | ~125.000 |
| alkes_luka | Betadine×2, Kassa×10, Plester Luka×2, Alkohol×2 | ~126.000 |
| alkes_harian | Masker 3-Ply×3, Hand Sanitizer×2, Kapas Alkohol×1 | ~170.000 |
| obat_umum | Paracetamol×20, Ibuprofen×10, Omeprazole×10, Antasida×20, Cetirizine×10 | ~165.000 |

**Distribusi 26 transaksi/hari**:
2×kronik_diabetes + 2×kronik_hipertensi + 2×kronik_campuran + 3×infeksi + 4×batuk_pilek + 3×alkes_luka + 3×alkes_harian + 7×obat_umum = ~5M/hari (base)

### 5.2 Kalibrasi ke Target Rata-rata

Jumlah transaksi dan kuantitas disesuaikan dengan `target_rata2_ritel`:

```
faktor_skala = target_rata2_ritel / 5_000_000      # 5M adalah base kalibrasi
qty_final = round(qty_base × faktor_skala × random(0.80–1.20))
```

> Contoh: target 3 juta → faktor = 0.6, semua qty dikalikan 0.6 → ~3M/hari.
> Target 10 juta → faktor = 2.0, qty dikalikan 2.0 → ~10M/hari.

### 5.3 Alur Per Hari

Untuk setiap hari dalam rentang:
1. Acak urutan 26 template
2. Untuk setiap transaksi:
   - `nomor_ritel`: `RIT-YYYYMMDD-{4 digit urut per tanggal}`
   - `status`: `selesai`
   - `metode_bayar`: random (tunai 50%, transfer 30%, kartu 20%)
   - `dibayar_at`: antara 08:00–21:00 tersebar merata + jitter ±15 menit
   - `nama_pembeli`: random dari 50 nama Indonesia
   - Buat `transaksi_ritel_item` per item
   - Buat `mutasi_stok` tipe `keluar_ritel`
3. Update `stok` barang (kurangi)

---

## 6. Logika Generate Jurnal Akuntansi

Setelah semua transaksi dibuat, jurnal dibuat **dalam satu batch**:

### 6.1 Jurnal GRN (per goods_receipt)

| Debit | Kredit | Nominal |
|-------|--------|---------|
| 1-1300 Persediaan Barang | 2-1100 Hutang Dagang Supplier | `total_nilai` GRN |

### 6.2 Jurnal Ritel — Penjualan (per transaksi_ritel)

| Debit | Kredit | Nominal |
|-------|--------|---------|
| 1-1100 Kas (tunai) / 1-1200 Bank (transfer/kartu) | 4-1300 Pendapatan Penjualan Obat | `total_harga` |

### 6.3 Jurnal Ritel — HPP (per transaksi_ritel)

| Debit | Kredit | Nominal |
|-------|--------|---------|
| 5-1100 HPP Farmasi | 1-1300 Persediaan Barang | `sum(jumlah × harga_pokok)` per transaksi |

### 6.4 Nomor Jurnal

Format: `JU-YYYYMM-{4 digit urut baru per bulan}` — dilanjutkan dari nomor urut terakhir yang sudah ada di bulan tersebut, tidak meng-overwrite.

---

## 7. Fitur Reset / Hapus Data Demo

### 7.1 Form Reset

Terpisah dari form Generate, di panel "Bahaya" (merah):

| Field | Tipe | Keterangan |
|-------|------|------------|
| Tanggal Mulai | date | Awal rentang yang ingin dihapus |
| Tanggal Selesai | date | Akhir rentang yang ingin dihapus |
| Konfirmasi teks | text | User harus ketik **"HAPUS"** untuk mencegah klik tidak sengaja |

### 7.2 Urutan Penghapusan (transaction-safe)

```sql
-- dalam satu DB transaction
DELETE dari jurnal_umum   WHERE tanggal BETWEEN :dari AND :sampai
                           AND sumber_tipe IN ('goods_receipt','transaksi_ritel')

DELETE dari mutasi_stok   WHERE tipe IN ('masuk_pembelian','keluar_ritel')
                           AND DATE(created_at) BETWEEN :dari AND :sampai

DELETE dari gr_item       WHERE goods_receipt_id IN (
                                SELECT id FROM goods_receipt WHERE tanggal_terima BETWEEN :dari AND :sampai)
DELETE dari goods_receipt WHERE tanggal_terima BETWEEN :dari AND :sampai

DELETE dari po_item       WHERE purchase_order_id IN (
                                SELECT id FROM purchase_order WHERE tanggal_po BETWEEN :dari AND :sampai)
DELETE dari purchase_order WHERE tanggal_po BETWEEN :dari AND :sampai

DELETE dari transaksi_ritel_item WHERE transaksi_ritel_id IN (
                                SELECT id FROM transaksi_ritel WHERE DATE(dibayar_at) BETWEEN :dari AND :sampai)
DELETE dari transaksi_ritel WHERE DATE(dibayar_at) BETWEEN :dari AND :sampai

-- Recalculate stok dari mutasi yang tersisa
UPDATE barang SET stok = (subquery dari mutasi_stok masuk - keluar yang tersisa)
```

> **Harga pokok TIDAK di-reset** oleh proses hapus — harga pokok adalah data master, bukan bagian dari data demo.

---

## 8. Skema Data & Dampak

Tidak ada tabel baru. Generator menggunakan tabel yang sudah ada:

| Tabel | Dampak Generate | Dampak Reset |
|-------|----------------|--------------|
| `purchase_order` | INSERT baru | DELETE by tanggal_po |
| `po_item` | INSERT baru | DELETE cascade |
| `goods_receipt` | INSERT baru | DELETE by tanggal_terima |
| `gr_item` | INSERT baru | DELETE cascade |
| `transaksi_ritel` | INSERT baru | DELETE by dibayar_at |
| `transaksi_ritel_item` | INSERT baru | DELETE cascade |
| `mutasi_stok` | INSERT baru | DELETE by tipe+created_at |
| `jurnal_umum` | INSERT baru | DELETE by tanggal+sumber_tipe |
| `barang.stok` | UPDATE (bertambah/berkurang) | UPDATE (recalculate) |

---

## 9. Service & Komponen

### 9.1 Service

| Class | Namespace | Tanggung Jawab |
|-------|-----------|----------------|
| `DemoDataGeneratorService` | `App\Services\Demo` | Orkestrasi: validasi konflik, panggil sub-generator, laporan hasil |
| `DemoPoGrnGenerator` | `App\Services\Demo` | Generate PO+GRN untuk rentang tanggal & target tertentu |
| `DemoRitelGenerator` | `App\Services\Demo` | Generate transaksi ritel untuk rentang tanggal & target tertentu |
| `DemoJurnalGenerator` | `App\Services\Demo` | Generate jurnal dari PO/GRN+ritel yang baru saja dibuat |
| `DemoDataResetService` | `App\Services\Demo` | Hapus semua data dalam rentang tanggal secara berurutan |

### 9.2 Livewire Component

| Component | View | Keterangan |
|-----------|------|------------|
| `Pengaturan\DemoDataGenerator` | `livewire/pengaturan/demo-data-generator` | Form generate + riwayat + form reset |

### 9.3 Route

```php
Route::middleware(['auth', 'role:super_admin'])->prefix('pengaturan')->name('pengaturan.')->group(function () {
    Route::get('/demo-generator', fn () => view('pengaturan.demo-generator'))
        ->name('demo.generator');
});
```

---

## 10. Role & Hak Akses

| Aksi | Role |
|------|------|
| Akses halaman generator | `super_admin` saja |
| Generate data | `super_admin` saja |
| Reset/hapus data | `super_admin` saja |

> Tidak menambah permission Spatie — cukup middleware `role:super_admin` dari Laravel Permission. Super admin sudah punya semua permission secara implisit.

---

## 11. Business Rules & Validasi

| # | Aturan |
|---|--------|
| BR-01 | Rentang tanggal maksimal 10 hari (Selesai - Mulai ≤ 9 hari). |
| BR-02 | Tanggal tidak boleh di masa depan (tidak bisa generate transaksi untuk besok). |
| BR-03 | Target PO minimum Rp 1.000.000/hari, maksimum Rp 100.000.000/hari. |
| BR-04 | Target Ritel minimum Rp 500.000/hari, maksimum Rp 50.000.000/hari. |
| BR-05 | Minimal satu jenis data harus dicentang (PO atau Ritel, atau keduanya). |
| BR-06 | Jika tanggal range overlap dengan data yang ada, tampilkan konfirmasi sebelum generate (tidak auto-hapus diam-diam). |
| BR-07 | Reset memerlukan konfirmasi teks **"HAPUS"** untuk mencegah klik tidak sengaja. |
| BR-08 | Generate dijalankan dalam satu DB transaction per hari — jika satu hari gagal, hari tersebut di-rollback tanpa mempengaruhi hari sebelumnya yang sudah berhasil. |
| BR-09 | Nomor PO, GRN, Ritel, dan Jurnal tidak boleh duplikat — generator memeriksa sequence terbaru sebelum membuat nomor baru. |
| BR-10 | Stok barang tidak boleh negatif akibat generate ritel — jika stok tidak cukup, qty item dikurangi sampai stok = 0 (tidak error, tidak skip). |

---

## 12. UI / UX

### 12.1 Layout Halaman

```
┌──────────────────────────────────────────────────────────────────┐
│  ⚙️  Demo Data Generator                                          │
│  Hanya untuk keperluan presentasi & demo ke klien                │
├──────────────────────────────────────────────────────────────────┤
│                                                                  │
│  📅 RENTANG TANGGAL                                               │
│  [Mulai ________] s/d [Selesai ________]   Max 10 hari           │
│                                                                  │
│  📦 JENIS DATA                                                    │
│  ☑ Generate PO + GRN          Target rata-rata: [Rp 10.000.000]  │
│  ☑ Generate Penjualan Ritel   Target rata-rata: [Rp  5.000.000]  │
│                                                                  │
│  ☑ Generate Jurnal Akuntansi otomatis                            │
│                                                                  │
│  ┌─── RINGKASAN ESTIMASI ──────────────────────────────────┐     │
│  │ 10 hari × (2 PO + 2 GRN) = 20 PO + 20 GRN              │     │
│  │ Estimasi total pembelian  : Rp 100.000.000               │     │
│  │ 10 hari × 26 transaksi    = 260 transaksi ritel          │     │
│  │ Estimasi total penjualan  : Rp  50.000.000               │     │
│  └──────────────────────────────────────────────────────────┘     │
│                                                                  │
│            [  Generate Data  ]                                   │
│                                                                  │
├──────────────────────────────────────────────────────────────────┤
│  📊 RIWAYAT GENERATE TERAKHIR                                     │
│  ┌─────────────┬──────────┬──────────────┬──────────────────┐    │
│  │ Rentang     │ Jenis    │ Hasil        │ Waktu            │    │
│  ├─────────────┼──────────┼──────────────┼──────────────────┤    │
│  │ 01–10 Jun   │ PO+Ritel │ 20PO, 260trx │ 30 Jun 10:23     │    │
│  └─────────────┴──────────┴──────────────┴──────────────────┘    │
├──────────────────────────────────────────────────────────────────┤
│  ⛔ ZONA BERBAHAYA — HAPUS DATA DEMO                               │
│  Rentang: [Mulai ________] s/d [Selesai ________]               │
│  Ketik "HAPUS" untuk konfirmasi: [__________]                    │
│                          [  Hapus Data Range Ini  ]              │
└──────────────────────────────────────────────────────────────────┘
```

### 12.2 Progress Real-time

Saat generate berjalan, tampilkan progress langsung di halaman (Livewire polling setiap 1 detik):

```
⏳ Generating...
  ✅ 1 Jun 2025 — PO+GRN (Rp 10.2jt) + 26 transaksi ritel (Rp 5.1jt)
  ✅ 2 Jun 2025 — PO+GRN (Rp 9.8jt) + 26 transaksi ritel (Rp 4.9jt)
  ⏳ 3 Jun 2025 — Sedang diproses...
  ○  4–10 Jun 2025 — Menunggu
```

> Implementasi: setiap hari yang selesai di-emit ke Livewire via `$this->dispatch('log', ...)`, di-append ke array `$logs` di component.

### 12.3 Ringkasan Setelah Selesai

```
✅ Generate selesai dalam 8.3 detik

  📦 PO + GRN
     20 Purchase Order  · Total: Rp 102.450.000
     20 Goods Receipt   · Avg: Rp 10.245.000/hari

  🛒 Penjualan Ritel
     260 Transaksi      · Total: Rp 51.230.000
     Avg: Rp 5.123.000/hari

  📒 Jurnal Akuntansi
     620 entri dibuat   (20 GRN + 260 penjualan + 260 HPP + 80 lainnya)

  [Lihat Laporan Laba Rugi]   [Lihat Neraca]   [Lihat Buku Besar]
```

---

## 13. Fase Implementasi

| Fase | Lingkup | Estimasi |
|------|---------|----------|
| **Fase 1 — Service Layer** | `DemoPoGrnGenerator`, `DemoRitelGenerator`, `DemoJurnalGenerator`, `DemoDataResetService`, orkestrasi `DemoDataGeneratorService` | 2–3 hari |
| **Fase 2 — Livewire UI** | Component `DemoDataGenerator`, form input, estimasi preview, log streaming, ringkasan hasil, form reset | 2 hari |
| **Fase 3 — Route & Integrasi** | Route super_admin, link di menu Pengaturan, riwayat generate (simpan ke session/cache) | 1 hari |

---

## 14. Out of Scope

- Generate data modul lain (kunjungan pasien, pemeriksaan SOAP, billing, resep) — fokus hanya pada transaksi yang paling "terlihat" di laporan keuangan (PO+GRN+Ritel+Jurnal).
- Scheduling otomatis generate data (cron) — ini adalah aksi manual on-demand.
- Export/import template konfigurasi generator.
- Multi-user concurrent generate — jika dua super admin generate bersamaan, hasilnya tidak terdefinisi. Tidak perlu lock/mutex untuk MVP ini.
- Tampilan watermark "DEMO" di laporan PDF — data bersifat identik dengan data nyata, tidak perlu label khusus.

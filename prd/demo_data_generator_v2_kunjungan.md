# Product Requirements Document (PRD)
# Demo Data Generator v2 — Kunjungan, SOAP, Billing & Resep

| Info | Detail |
|:-----|:-------|
| **Versi** | 2.0.0 |
| **Tanggal** | Juli 2026 |
| **Status** | Draft |
| **Depends On** | `demo_data_generator.md` (Phase 1 — PO+GRN+Ritel sudah live) |
| **Tech Stack** | Laravel 12 · Livewire 3 · MySQL · Tailwind CSS |
| **Scope** | Generate alur kunjungan pasien end-to-end: Registrasi → Asesmen → SOAP → Resep → Tindakan → Billing → Pembayaran → Jurnal |
| **Lokasi** | Pengaturan → Demo Data Generator (tab/section baru di halaman yang sama) |

---

## Daftar Isi

1. [Latar Belakang & Tujuan](#1-latar-belakang--tujuan)
2. [Pola Kunjungan (Skenario Template)](#2-pola-kunjungan-skenario-template)
3. [Alur Data per Kunjungan](#3-alur-data-per-kunjungan)
4. [Logika Generate Kunjungan](#4-logika-generate-kunjungan)
5. [Logika Generate Asesmen Perawat](#5-logika-generate-asesmen-perawat)
6. [Logika Generate SOAP + Diagnosa ICD](#6-logika-generate-soap--diagnosa-icd)
7. [Logika Generate Resep + Item Resep](#7-logika-generate-resep--item-resep)
8. [Logika Generate Tindakan](#8-logika-generate-tindakan)
9. [Logika Generate Billing + InvoiceItem + Pembayaran](#9-logika-generate-billing--invoiceitem--pembayaran)
10. [Logika Generate Jurnal Akuntansi (Billing)](#10-logika-generate-jurnal-akuntansi-billing)
11. [Fitur Reset / Hapus Data Kunjungan](#11-fitur-reset--hapus-data-kunjungan)
12. [Form Input UI (Tambahan ke Halaman Generator)](#12-form-input-ui-tambahan-ke-halaman-generator)
13. [Service Architecture](#13-service-architecture)
14. [Business Rules & Validasi](#14-business-rules--validasi)
15. [Skema Data & Dampak](#15-skema-data--dampak)
16. [Fase Implementasi](#16-fase-implementasi)
17. [Out of Scope](#17-out-of-scope)

---

## 1. Latar Belakang & Tujuan

Phase 1 menghasilkan data inventory & penjualan ritel yang membuat laporan keuangan terisi.
Phase 2 melengkapi sisi **klinis** — kunjungan pasien, pemeriksaan, resep, dan billing — sehingga saat demo:

- Modul **Pendaftaran & Antrean** menampilkan riwayat kunjungan
- Modul **Pemeriksaan** menampilkan catatan SOAP & diagnosa
- Modul **Billing & Kasir** menampilkan tagihan yang sudah lunas
- **Laporan Laba Rugi** mencerminkan pendapatan tindakan (4-1100) dan pendapatan obat (4-1300) dari billing, bukan hanya dari penjualan ritel
- **Dashboard** menampilkan grafik kunjungan harian dan omzet yang bervariasi secara natural

**Non-tujuan**: Bukan untuk mengisi data produksi. Data yang dihasilkan identik secara struktur dengan data asli — semua laporan merespons seolah kunjungan tersebut nyata.

---

## 2. Pola Kunjungan (Skenario Template)

5 template yang merepresentasikan mayoritas kunjungan di klinik umum:

### Pola 0 — Pemeriksaan Umum Ringan (ISPA/Flu)
| Aspek | Detail |
|-------|--------|
| **Poli** | Poli Umum (id=1) |
| **Keluhan** | "Batuk dan pilek sejak 3 hari, demam ringan, tenggorokan gatal" |
| **Diagnosa ICD** | J06.9 (ISPA akut tidak spesifik) |
| **Asesmen** | TD 120/80, Nadi 82, Suhu 37.4°C, SpO2 98%, BB 62kg |
| **Tindakan** | Pemeriksaan Fisik Umum (id=1, tarif Rp 50.000) |
| **Resep** | Paracetamol 500mg ×15 (3×1), Cetirizine 10mg ×5 (1×1 malam), Amoxicillin 500mg ×15 (3×1) |
| **Target Billing** | Rp 120.000 – 180.000 |
| **Metode Bayar** | 60% tunai, 30% transfer, 10% BPJS |
| **Bobot** | 35% dari total kunjungan |

### Pola 1 — Kontrol Penyakit Kronis (DM / Hipertensi)
| Aspek | Detail |
|-------|--------|
| **Poli** | Poli Dalam (id=5) |
| **Keluhan** | "Kontrol rutin, minta surat rujukan dan perpanjangan obat" |
| **Diagnosa ICD** | E11.9 (DM Tipe 2 tanpa komplikasi) + I10 (Hipertensi esensial) |
| **Asesmen** | TD 140/90, Nadi 78, Suhu 36.7°C, SpO2 99%, BB 70kg, GDS 210 |
| **Tindakan** | Pemeriksaan Fisik Umum (id=1), FOC dr. Umum (id=11, tarif Rp 0) |
| **Resep** | Metformin 500mg ×30 (2×1), Amlodipine 5mg ×30 (1×1), Omeprazole 20mg ×30 (1×1), Cetirizine 10mg ×15 |
| **Target Billing** | Rp 250.000 – 450.000 |
| **Metode Bayar** | 40% tunai, 30% transfer, 30% BPJS |
| **Bobot** | 25% dari total kunjungan |

### Pola 2 — Tindakan Minor (Luka / Injeksi)
| Aspek | Detail |
|-------|--------|
| **Poli** | Poli Umum (id=1) |
| **Keluhan** | "Luka robek di lengan kiri ±3 cm, perdarahan sudah berhenti" |
| **Diagnosa ICD** | S51.8 (Luka terbuka lengan bawah) |
| **Asesmen** | TD 118/76, Nadi 88, Suhu 36.9°C, SpO2 99%, BB 65kg |
| **Tindakan** | Pemeriksaan Fisik Umum (id=1) + Jahit Luka (id=7, tarif Rp 120.000) |
| **Resep** | Amoxicillin 500mg ×15, Ibuprofen 400mg ×10, Betadine 60ml ×1, Kassa Steril ×5 |
| **Target Billing** | Rp 300.000 – 500.000 |
| **Metode Bayar** | 70% tunai, 20% transfer, 10% BPJS |
| **Bobot** | 15% dari total kunjungan |

### Pola 3 — Pemeriksaan Anak (Poli Anak)
| Aspek | Detail |
|-------|--------|
| **Poli** | Poli Anak (id=3) |
| **Keluhan** | "Anak demam 3 hari, tidak mau makan, rewel" |
| **Diagnosa ICD** | A90 (Demam berdarah / dengue) atau R50.9 (Demam tidak spesifik) |
| **Asesmen** | TD 100/70, Nadi 95, Suhu 38.2°C, SpO2 97%, BB 22kg |
| **Tindakan** | Pemeriksaan Fisik Umum (id=1, tarif Rp 50.000) |
| **Resep** | Paracetamol 500mg ×10 (3×1 jika perlu), Antasida Doen ×10 (3×1), Cetirizine 10mg ×5 |
| **Target Billing** | Rp 80.000 – 150.000 |
| **Metode Bayar** | 50% tunai, 30% transfer, 20% BPJS |
| **Bobot** | 15% dari total kunjungan |

### Pola 4 — Nebulisasi / Sesak Napas
| Aspek | Detail |
|-------|--------|
| **Poli** | Poli Umum (id=1) |
| **Keluhan** | "Sesak napas, napas berbunyi ngik-ngik, batuk produktif" |
| **Diagnosa ICD** | J45.9 (Asma tidak spesifik) |
| **Asesmen** | TD 125/85, Nadi 98, Suhu 37.1°C, SpO2 94%, BB 58kg |
| **Tindakan** | Pemeriksaan Fisik Umum (id=1) + Nebulisasi (id=10, tarif Rp 60.000) |
| **Resep** | Salbutamol (pakai Cetirizine sebagai proxy obat asma) ×10, Masker Nebulizer ×1 |
| **Target Billing** | Rp 150.000 – 280.000 |
| **Metode Bayar** | 60% tunai, 40% transfer |
| **Bobot** | 10% dari total kunjungan |

---

## 3. Alur Data per Kunjungan

Setiap 1 kunjungan yang di-generate menghasilkan rekaman di:

```
kunjungan
    └── asesmen_perawat (1 per kunjungan)
    └── soap_note       (1 per kunjungan, is_final=true)
    └── resep           (1 per kunjungan, jika ada resep)
    │       └── item_resep (N item obat)
    └── tindakan        (1–2 per kunjungan)
    └── billing         (1 per kunjungan, status='lunas')
    │       └── invoice_item (N item: tindakan + obat resep)
    │       └── pembayaran   (1 record per kunjungan)
    └── jurnal_umum     (N entri: per jenis item × proporsi)
```

---

## 4. Logika Generate Kunjungan

### 4.1 Input Pengguna

| Field | Default | Range |
|-------|---------|-------|
| Tanggal mulai-selesai | (reuse dari Phase 1) | max 10 hari |
| Jumlah kunjungan / hari | 20 | 5 – 100 |
| Mix pembayaran | Tunai 50% · Transfer 30% · BPJS 20% | (slider %) |

### 4.2 Distribusi Pola Harian

Dari `$jumlahPerHari` kunjungan, distribuikan berdasarkan bobot:

| Pola | Bobot | Contoh 20 kunjungan |
|------|-------|---------------------|
| umum_ringan | 35% | 7 |
| kronik | 25% | 5 |
| tindakan_minor | 15% | 3 |
| anak | 15% | 3 |
| nebulisasi | 10% | 2 |

Acak urutan dalam hari (shuffle) agar antrean tidak terlihat pola yang persis sama.

### 4.3 Pasien yang Digunakan

Gunakan **pasien yang sudah ada** di tabel `pasien` (tidak membuat pasien baru) — pilih secara siklus:

```
$pasienPool = Pasien::aktif()->pluck('id')->toArray();
$pasienId   = $pasienPool[($idx) % count($pasienPool)];
```

Jika pasien tidak ada sama sekali, generator melempar exception yang bermakna.

### 4.4 Dokter yang Digunakan

Pilih dokter berdasarkan poli yang relevan:

```
$dokterId = Dokter::aktifDanSipValid()
               ->whereHas('dokterPoli', fn ($q) => $q->where('poli_id', $poliId))
               ->inRandomOrder()->value('id');
```

Jika tidak ada dokter untuk poli tersebut, fallback ke dokter pertama yang aktif.

### 4.5 Nomor Antrean

Format: `A-{3 digit urut per hari}` (A-001, A-002, …)

Cek sequence terakhir dari DB untuk tanggal yang dimaksud sebelum generate.

### 4.6 Field Kunjungan

| Field | Nilai |
|-------|-------|
| `nomor_antrean` | A-001 ... A-NNN |
| `tanggal` | Tanggal hari ini (jam 08:00–14:00 tersebar merata) |
| `keluhan` | Sesuai pola |
| `status` | `selesai` |
| `tipe_pembayaran` | `umum` / `bpjs` (sesuai mix pembayaran) |
| `waktu_panggil` | `tanggal` + 5–30 menit jitter |
| `asal_kedatangan` | `datang_sendiri` |

---

## 5. Logika Generate Asesmen Perawat

Satu record per kunjungan, variasi ±10% dari nilai baseline setiap pola:

| Pola | TD | Nadi | Suhu | SpO2 | BB | GDS |
|------|----|------|------|------|----|-----|
| umum_ringan | 120/80 | 82 | 37.4 | 98 | 62 | null |
| kronik | 140/90 | 78 | 36.7 | 99 | 70 | 210 |
| tindakan_minor | 118/76 | 88 | 36.9 | 99 | 65 | null |
| anak | 100/70 | 95 | 38.2 | 97 | 22 | null |
| nebulisasi | 125/85 | 98 | 37.1 | 94 | 58 | null |

Variasi tekanan darah: sistol ±10, diastol ±5. Nadi ±8. Suhu ±0.3°C. SpO2 ±1.

`perawat_id` = User dengan role perawat (fallback: user dengan id terkecil yang aktif).

---

## 6. Logika Generate SOAP + Diagnosa ICD

### 6.1 Template SOAP per Pola

Setiap pola memiliki teks template SOAP yang realistis:

**Pola umum_ringan**:
```
Subjektif  : "Pasien datang dengan keluhan batuk dan pilek sejak 3 hari lalu disertai demam tidak terlalu tinggi, tenggorokan terasa gatal dan perih. Tidak ada sesak napas."
Objektif   : "Keadaan umum: baik. TD {TD}, Nadi {Nadi}x/mnt, Suhu {Suhu}°C, SpO2 {SpO2}%. Pemeriksaan fisik: faring hiperemis, tonsil tidak membesar."
Asesmen    : "ISPA — J06.9"
Plan       : "Istirahat cukup, minum air putih 2L/hari, obat sesuai resep, kontrol bila tidak membaik dalam 3 hari."
```

**Pola kronik**:
```
Subjektif  : "Pasien datang untuk kontrol rutin DM dan hipertensi. Keluhan saat ini: kepala terasa berat, sering haus dan BAK, energi menurun."
Objektif   : "Keadaan umum: cukup. TD {TD}, Nadi {Nadi}x/mnt, Suhu {Suhu}°C, GDS {GDS} mg/dL. Pemeriksaan fisik: dalam batas normal."
Asesmen    : "DM Tipe 2 tidak terkontrol (E11.9), Hipertensi esensial (I10)"
Plan       : "Lanjut terapi, perketat diet rendah gula dan garam, kontrol 1 bulan lagi."
```

*(Template untuk pola lain analog)*

### 6.2 Substitusi Nilai Vital

Ganti placeholder `{TD}`, `{Nadi}`, `{Suhu}`, `{SpO2}`, `{GDS}` dengan nilai aktual dari asesmen yang baru saja di-generate.

### 6.3 ICD Codes (Format JSON)

```json
[
  { "kode": "J06.9", "nama": "ISPA akut tidak spesifik", "is_primary": true }
]
```

Ambil dari tabel `icd_diagnosis` via `where('kode', 'J06.9')->first()`. Jika ICD tidak ada di DB, gunakan data hardcoded (kode + nama saja, tidak perlu FK constraint).

### 6.4 Status SOAP

- `is_final` = `true`
- `finalized_at` = waktu kunjungan + 20–60 menit
- `finalized_by` = dokter_id dari kunjungan

---

## 7. Logika Generate Resep + Item Resep

### 7.1 Item Resep per Pola

Ambil barang dari tabel `barang` berdasarkan `barang_id` hardcoded sesuai pola. Jika stok cukup, buat item resep.

| Pola | Barang | Jumlah | Aturan Pakai |
|------|--------|--------|--------------|
| umum_ringan | Paracetamol 500mg (id=1) | 15 | 3×1 tab setelah makan |
| | Cetirizine 10mg (id=5) | 5 | 1×1 tab malam |
| | Amoxicillin 500mg (id=2) | 15 | 3×1 tab setelah makan |
| kronik | Metformin 500mg (id=7) | 30 | 2×1 tab pagi-malam |
| | Amlodipine 5mg (id=8) | 30 | 1×1 tab pagi |
| | Omeprazole 20mg (id=6) | 30 | 1×1 tab sebelum makan pagi |
| | Cetirizine 10mg (id=5) | 15 | 1×1 tab malam |
| tindakan_minor | Amoxicillin 500mg (id=2) | 15 | 3×1 tab setelah makan |
| | Ibuprofen 400mg (id=3) | 10 | 3×1 tab setelah makan |
| | Betadine 60ml (id=32) | 1 | Oleskan 2× sehari |
| | Kassa Steril (id=24) | 5 | Ganti balut 1× sehari |
| anak | Paracetamol 500mg (id=1) | 10 | 3×1 tab jika demam |
| | Antasida Doen (id=4) | 10 | 3×1 tab setelah makan |
| | Cetirizine 10mg (id=5) | 5 | 1×1 tab malam |
| nebulisasi | Cetirizine 10mg (id=5) | 10 | 2×1 tab |
| | Masker Nebulizer Dewasa (id=56) | 1 | Nebulisasi 2× sehari |

### 7.2 Status Resep

```
status     = 'selesai'
is_locked  = true
locked_by  = apoteker_id (user dengan role apoteker)
locked_at  = finalized_at + 15–30 menit
```

### 7.3 Mutasi Stok Resep

Untuk setiap item resep yang dispensed, kurangi `barang.stok` dan buat record `mutasi_stok`:
- `tipe` = `keluar_resep`
- `referensi_tipe` = `resep`
- `referensi_id` = resep.id
- `keterangan` = "Resep {nomor_kunjungan}"

> **Catatan**: Mutasi stok dari resep hanya dibuat jika generate_po_grn=true (ada stok masuk dari Phase 1) atau jika stok barang tersedia. Jika stok = 0, item resep tetap dibuat tapi tanpa mutasi stok (BR-Kunjungan-06).

---

## 8. Logika Generate Tindakan

Buat `Tindakan` record berdasarkan pola:

| Field | Nilai |
|-------|-------|
| `kunjungan_id` | ID kunjungan saat ini |
| `master_tindakan_id` | Sesuai pola |
| `pelaksana_id` | dokter_id kunjungan |
| `jumlah` | 1 |
| `waktu_tindakan` | `waktu_panggil` + 10–15 menit |

---

## 9. Logika Generate Billing + InvoiceItem + Pembayaran

### 9.1 Nomor Invoice

Format: `INV-YYYYMMDD-{4 digit urut per hari}`

Cek sequence terakhir dari tabel `billing` untuk tanggal tersebut.

### 9.2 InvoiceItem — Tindakan

Untuk setiap `Tindakan` yang dibuat:
```
billing_id    = billing.id
jenis         = 'tindakan'
ref_id        = tindakan.id
nama_item     = master_tindakan.nama
qty           = 1
satuan        = 'tindakan'
harga_satuan  = master_tindakan.tarif
diskon_item   = 0
subtotal      = tarif
```

### 9.3 InvoiceItem — Obat (dari Resep)

Untuk setiap `ItemResep`:
```
billing_id    = billing.id
jenis         = 'obat'
ref_id        = item_resep.id
nama_item     = barang.nama
qty           = item_resep.jumlah
satuan        = barang.satuan
harga_satuan  = barang.harga_jual
diskon_item   = 0
subtotal      = qty × harga_jual
```

### 9.4 Total Billing

```
total_tagihan = SUM(invoice_item.subtotal)
total_bayar   = total_tagihan  (lunas penuh, sisa = 0)
sisa          = 0
diskon_global = 0
status        = 'lunas'
tipe_pembayaran dipilih sesuai mix: 'umum' atau 'bpjs'
asuransi_id   = null (untuk tipe umum); bisa null untuk BPJS (simplified)
```

### 9.5 Pembayaran

```
billing_id       = billing.id
metode           = 'tunai' / 'transfer' / 'bpjs' (sesuai mix)
jumlah           = billing.total_tagihan
bank_nama        = null
nomor_referensi  = null
created_at       = billing.updated_at = waktu pembayaran
```

> Untuk simplifikasi demo: satu metode per pembayaran (tidak split payment).

---

## 10. Logika Generate Jurnal Akuntansi (Billing)

Menggunakan pola dari `BillingJurnalService` yang sudah ada:

**Untuk setiap kunjungan (1 billing)**:

Proporsi pendapatan per kategori item:
```
total_invoice        = SUM(invoice_item.subtotal)
proporsi_tindakan    = SUM(item.subtotal where jenis='tindakan') / total_invoice
proporsi_obat        = SUM(item.subtotal where jenis='obat')     / total_invoice
```

Akun debit berdasarkan metode bayar:
```
tunai/transfer → 1-1100 Kas / 1-1200 Bank
bpjs/asuransi  → 1-1400 Piutang Asuransi
```

Entry jurnal (1 entry per kategori yang ada):
```
DR [akun_debit]          | KR 4-1100 Pendapatan Tindakan | nominal = total × proporsi_tindakan
DR [akun_debit]          | KR 4-1300 Pendapatan Obat     | nominal = total × proporsi_obat
```

**Nomor Jurnal**: `JU-YYYYMM-{urut lanjut dari existing}`

**Sumber**: `sumber_tipe = 'billing'`, `sumber_id = billing.id`

---

## 11. Fitur Reset / Hapus Data Kunjungan

Urutan penghapusan (dalam 1 DB transaction):

```sql
-- 1. Hapus jurnal (sumber_tipe='billing' dalam rentang tanggal billing)
DELETE jurnal_umum WHERE sumber_tipe='billing'
  AND sumber_id IN (SELECT id FROM billing WHERE DATE(created_at) BETWEEN :dari AND :sampai)

-- 2. Hapus mutasi stok (tipe='keluar_resep' dalam rentang tanggal)
DELETE mutasi_stok WHERE tipe='keluar_resep'
  AND referensi_id IN (SELECT id FROM resep WHERE kunjungan_id IN (...))

-- 3. Hapus pembayaran
DELETE pembayaran WHERE billing_id IN (SELECT id FROM billing ...)

-- 4. Hapus invoice_item
DELETE invoice_item WHERE billing_id IN (...)

-- 5. Hapus billing
DELETE billing WHERE DATE(created_at) BETWEEN :dari AND :sampai

-- 6. Hapus tindakan
DELETE tindakan WHERE kunjungan_id IN (...)

-- 7. Hapus item_resep → resep
DELETE item_resep WHERE resep_id IN (...)
DELETE resep WHERE kunjungan_id IN (...)

-- 8. Hapus soap_note
DELETE soap_note WHERE kunjungan_id IN (...)

-- 9. Hapus asesmen_perawat
DELETE asesmen_perawat WHERE kunjungan_id IN (...)

-- 10. Hapus kunjungan
DELETE kunjungan WHERE DATE(tanggal) BETWEEN :dari AND :sampai

-- 11. Recalculate barang.stok
UPDATE barang SET stok = (masuk - keluar dari mutasi yang tersisa)
```

> Identifikasi kunjungan demo: **berdasarkan rentang tanggal** (`tanggal BETWEEN`). Ini konsisten dengan pendekatan Phase 1. Pastikan UI memberikan peringatan bahwa semua kunjungan dalam rentang tersebut akan dihapus, termasuk data nyata jika ada.

---

## 12. Form Input UI (Tambahan ke Halaman Generator)

Tambahkan section baru "Kunjungan & Billing" ke halaman Generator yang sudah ada:

```
┌──────────────────────────────────────────────────────────────────┐
│  [existing: Rentang Tanggal]                                     │
│  [existing: PO+GRN checkbox]                                     │
│  [existing: Penjualan Ritel checkbox]                            │
├──────────────────────────────────────────────────────────────────┤
│  ☑ Generate Kunjungan & Billing      [baru - v2]                 │
│                                                                  │
│  Jumlah kunjungan per hari: [_20_]  (5–100)                     │
│                                                                  │
│  Mix Pembayaran:                                                 │
│  Tunai [__50__]% · Transfer [__30__]% · BPJS [__20__]%          │
│  (total harus = 100%)                                            │
│                                                                  │
│  ☑ Include resep & mutasi stok obat                             │
└──────────────────────────────────────────────────────────────────┘
```

### Ringkasan Estimasi (tambahan):

```
🏥 Kunjungan & Billing
   ≈ 200 kunjungan (20/hari × 10 hari)
   ≈ 200 Invoice Lunas
   Estimasi pendapatan: Rp 30.000.000 – Rp 60.000.000
   (tergantung mix pola dan obat yang di-resepkan)
```

### Ringkasan Hasil (setelah generate):

```
🏥 Kunjungan & Billing
   200 kunjungan · 200 invoice lunas
   Total pendapatan: Rp 42.350.000
   - Tindakan : Rp 12.150.000 (4-1100)
   - Obat     : Rp 30.200.000 (4-1300)
   Jurnal     : 380 entri
```

---

## 13. Service Architecture

### 13.1 Service Baru

| Class | Namespace | Tanggung Jawab |
|-------|-----------|----------------|
| `DemoKunjunganGenerator` | `App\Services\Demo` | Generate 1 hari kunjungan: kunjungan → asesmen → SOAP → resep → tindakan |
| `DemoBillingGenerator` | `App\Services\Demo` | Generate billing + invoice_item + pembayaran untuk kunjungan yang baru dibuat |
| `DemoBillingJurnalGenerator` | `App\Services\Demo` | Generate jurnal akuntansi untuk billing (ikuti pola BillingJurnalService) |

### 13.2 Integrasi ke Service yang Ada

**`DemoDataGeneratorService`** diperluas:
- Tambah `options['generate_kunjungan']` (bool)
- Tambah `options['kunjungan_per_hari']` (int)
- Tambah `options['mix_bayar']` (array: `['tunai'=>50, 'transfer'=>30, 'bpjs'=>20]`)
- Tambah `options['include_resep_stok']` (bool)
- `generate()` memanggil `DemoKunjunganGenerator` + `DemoBillingGenerator` + `DemoBillingJurnalGenerator` setelah Phase 1

**`DemoDataResetService::hapus()`** diperluas:
- Tambah penghapusan kunjungan + semua turunannya (11 langkah di section 11)

### 13.3 Interface Callback Progress

Callback `$onProgress` diperluas dengan tipe baru:

```php
$onProgress([
    'tipe'    => 'kunjungan',
    'tanggal' => '2025-07-01',
    'jumlah'  => 20,
    'billing' => 19,           // yang berhasil lunas
    'pendapatan' => 4250000.0,
]);
```

---

## 14. Business Rules & Validasi

| # | Aturan |
|---|--------|
| BR-K01 | Jumlah kunjungan per hari minimal 5, maksimal 100. |
| BR-K02 | Mix pembayaran tunai + transfer + bpjs harus = 100%. |
| BR-K03 | Minimal ada 1 pasien aktif di tabel `pasien` sebelum generate. |
| BR-K04 | Minimal ada 1 dokter aktif + poli aktif sebelum generate. |
| BR-K05 | Kunjungan tidak bisa di-generate untuk tanggal masa depan. |
| BR-K06 | Jika stok barang = 0, item resep tetap dibuat (untuk catatan klinis) tapi tanpa `mutasi_stok` keluar dan tanpa menambah `invoice_item` obat yang bersangkutan. |
| BR-K07 | Billing dibuat hanya jika ada minimal 1 `invoice_item` (tindakan). Jika total tagihan = 0 (semua tarif 0), billing tetap dibuat dengan total = 0 dan status lunas. |
| BR-K08 | Nomor antrean harus unik per tanggal. Generator cek sequence terakhir sebelum mulai. |
| BR-K09 | Reset kunjungan menghapus **semua** kunjungan dalam rentang tanggal, termasuk yang mungkin adalah data nyata. Tampilkan peringatan eksplisit di UI. |
| BR-K10 | Kunjungan demo menggunakan pasien yang sudah ada (tidak membuat pasien baru) untuk menghindari polusi master data pasien. |

---

## 15. Skema Data & Dampak

Tidak ada tabel baru. Semua tabel sudah ada:

| Tabel | Dampak Generate | Dampak Reset |
|-------|----------------|--------------|
| `kunjungan` | INSERT | DELETE by tanggal |
| `asesmen_perawat` | INSERT | DELETE cascade via kunjungan |
| `soap_note` | INSERT | DELETE cascade via kunjungan |
| `resep` | INSERT | DELETE cascade |
| `item_resep` | INSERT | DELETE cascade |
| `tindakan` | INSERT | DELETE cascade |
| `billing` | INSERT | DELETE by tanggal |
| `invoice_item` | INSERT | DELETE cascade via billing |
| `pembayaran` | INSERT | DELETE cascade via billing |
| `mutasi_stok` (keluar_resep) | INSERT | DELETE by referensi |
| `jurnal_umum` (billing) | INSERT | DELETE by sumber_id |
| `barang.stok` | UPDATE (berkurang jika include_resep_stok) | UPDATE (recalculate) |

---

## 16. Fase Implementasi

| Fase | Lingkup | Estimasi |
|------|---------|----------|
| **Fase A — Generator Core** | `DemoKunjunganGenerator` (kunjungan → asesmen → SOAP → resep + item → tindakan + mutasi stok) | 2 hari |
| **Fase B — Billing Generator** | `DemoBillingGenerator` (billing → invoice_item → pembayaran) + `DemoBillingJurnalGenerator` | 1 hari |
| **Fase C — Reset Extension** | Perluasan `DemoDataResetService::hapus()` + 11 langkah hapus kunjungan | 1 hari |
| **Fase D — UI Extension** | Perluasan `DemoDataGenerator` Livewire component + view (tambah section Kunjungan) + estimasi + ringkasan | 1 hari |
| **Fase E — Integration & Test** | Integrasi `DemoDataGeneratorService` + test end-to-end: generate 2 hari → cek laporan → reset | 1 hari |

---

## 17. Out of Scope

- **Rawat Inap** — tidak di-generate (modul belum final)
- **Penunjang Laboratorium / Radiologi** — tidak di-generate (data lab tidak cukup signifikan secara visual untuk demo keuangan)
- **Deposit Pasien** — tidak di-generate; pembayaran selalu tunai/transfer/BPJS langsung
- **Asuransi swasta / piutang asuransi** — BPJS diset tapi tidak membuat record piutang asuransi (`PiutangAsuransi`)
- **Resep Racikan** — hanya resep biasa (ItemResep), tidak racikan (Racikan model)
- **Pasien baru demo** — tidak dibuat; gunakan pasien yang sudah ada agar master data tidak kotor
- **Surat Keterangan Dokter** — tidak di-generate; modul terpisah
- **Sharing Fee Dokter** — tidak di-generate; perlu analisis lebih lanjut terkait perhitungan fee

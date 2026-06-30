# Product Requirements Document (PRD)
# Modul Update Harga — Proposal Penyesuaian Harga Tahunan

| Info | Detail |
|:-----|:-------|
| **Versi** | 1.0.0 |
| **Tanggal** | Juni 2026 |
| **Status** | Draft |
| **Depends On** | `masterdata_v1.md` (MasterTindakan · Barang) · `modul_akuntansi_update.md` (periode akuntansi) |
| **Tech Stack** | Laravel 12 · Livewire 3 · Alpine.js · MySQL · Tailwind CSS |
| **Scope** | Proposal markup harga jasa pelayanan (`master_tindakan`) dan obat/alkes/bhp (`barang`), dengan alur review + persetujuan + penerapan bertanggal |
| **Out of Scope** | Harga rawat inap (PRD terpisah) · Penyesuaian harga per-pasien / diskon khusus |

---

## Daftar Isi

1. [Ringkasan & Kondisi Existing](#1-ringkasan--kondisi-existing)
2. [Tujuan & Non-Tujuan](#2-tujuan--non-tujuan)
3. [Alur Kerja (End-to-End)](#3-alur-kerja-end-to-end)
4. [State Machine Proposal](#4-state-machine-proposal)
5. [Fitur Detail — Buat Proposal](#5-fitur-detail--buat-proposal)
6. [Fitur Detail — Review & Koreksi Per Item](#6-fitur-detail--review--koreksi-per-item)
7. [Fitur Detail — Persetujuan](#7-fitur-detail--persetujuan)
8. [Fitur Detail — Penerapan Harga](#8-fitur-detail--penerapan-harga)
9. [Skema Database](#9-skema-database)
10. [Model, Service, & Komponen Livewire](#10-model-service--komponen-livewire)
11. [Role & Hak Akses](#11-role--hak-akses)
12. [Keputusan Desain & Batasan yang Disengaja](#12-keputusan-desain--batasan-yang-disengaja)
13. [Fase Implementasi](#13-fase-implementasi)
14. [Out of Scope](#14-out-of-scope)

---

## 1. Ringkasan & Kondisi Existing

### 1.1 Kondisi Existing (audit Juni 2026)

| Area | Status | Keterangan |
|---|---|---|
| Harga jasa pelayanan | ✅ Ada | `master_tindakan.tarif` + `tarif_bpjs` — diinput manual satu per satu lewat halaman master tindakan |
| Harga obat/alkes/bhp | ✅ Ada | `barang.harga_jual` + `harga_bpjs` — diinput manual satu per satu lewat halaman master barang |
| Harga pokok (HPP) | ✅ Ada | `barang.harga_pokok` — moving average, dikelola otomatis oleh sistem GRN, **tidak disentuh** modul ini |
| Riwayat perubahan harga | ❌ Belum ada | Tidak ada audit trail kapan harga berubah dan siapa yang mengubah |
| Proses kenaikan massal | ❌ Belum ada | Tidak ada; pengguna harus edit satu per satu secara manual |
| Penerapan bertanggal | ❌ Belum ada | Tidak ada mekanisme "harga berlaku mulai tanggal X" |

### 1.2 Masalah yang Diselesaikan

Setiap tahun klinik melakukan penyesuaian tarif layanan dan harga jual obat. Saat ini prosesnya:
- Edit manual item per item → rawan human error (terlewat / salah ketik)
- Tidak ada approval — satu orang bisa langsung ubah tanpa validasi
- Tidak ada preview "harga lama vs harga baru" sebelum diterapkan
- Tidak bisa dijadwalkan — efektif langsung saat disimpan, bukan per tanggal

---

## 2. Tujuan & Non-Tujuan

### Tujuan
- Buat **Proposal Penyesuaian Harga** dengan persentase kenaikan yang dapat dikonfigurasi per kategori
- Sistem kalkulasi otomatis harga baru dari persentase yang diinput
- Reviewer dapat **mengoreksi harga per item** sebelum disetujui
- Approval oleh role yang berwenang sebelum harga diubah
- **Penerapan bertanggal**: harga baru hanya aktif mulai `tanggal_efektif` yang ditentukan
- Riwayat audit: siapa yang buat, review, setujui, dan kapan diterapkan
- Tidak menyentuh `harga_pokok` / `tarif_bpjs` kecuali dicentang secara eksplisit

### Non-Tujuan
- Bukan sistem diskon / harga khusus per pasien
- Bukan penyesuaian harga BPJS (diatur pemerintah) — tersedia toggle opsional
- Bukan pengganti form edit manual satu per satu (form lama tetap ada)

---

## 3. Alur Kerja (End-to-End)

```
[Admin/Operator]
     │
     ▼
Buat Proposal → pilih cakupan (Semua / Tindakan / Barang) +
               isi % kenaikan per kategori + tanggal_efektif
     │
     ▼ (sistem generate ProposalHargaItem untuk setiap item aktif)
     │
     ▼
[Admin/Manager]
Review Per Item — lihat harga lama vs harga baru,
                  koreksi manual jika perlu,
                  centang "tidak naik" untuk item tertentu
     │
     ▼ submit ke persetujuan
[Manager/SuperAdmin]
Setujui Proposal → status: disetujui
     │
     ▼
Pada tanggal_efektif (atau manual trigger setelah tanggal tersebut):
Terapkan → sistem update harga_jual/tarif setiap item yang disetujui
           catat riwayat di proposal_harga_item (harga_lama, harga_baru)
     │
     ▼
Status: efektif (final, tidak bisa diubah)
```

---

## 4. State Machine Proposal

```
draft ──── [submit review] ──▶ menunggu_persetujuan ──── [setujui] ──▶ disetujui
  │                                     │                                  │
  │                               [tolak/revisi]                    [terapkan]
  │                                     │                                  │
  └──── [batalkan] ──▶ dibatalkan ◀─────┘                           efektif (final)
```

| Status | Siapa Bisa Aksi | Aksi yang Tersedia |
|--------|----------------|-------------------|
| `draft` | Pembuat | Edit %, koreksi item, submit review, batalkan |
| `menunggu_persetujuan` | Manager/SuperAdmin | Setujui, Tolak (kembalikan ke draft), Batalkan |
| `disetujui` | Manager/SuperAdmin | Terapkan (hanya jika ≥ tanggal_efektif), Batalkan |
| `efektif` | — | Lihat saja (final, immutable) |
| `dibatalkan` | — | Lihat saja |

---

## 5. Fitur Detail — Buat Proposal

### 5.1 Header Proposal

| Field | Tipe | Keterangan |
|-------|------|------------|
| `judul` | text | Contoh: "Kenaikan Harga Tahun 2027" |
| `tahun` | integer | Default: tahun depan dari today |
| `tanggal_efektif` | date | Harus ≥ hari ini + 1 hari (tidak boleh hari ini) |
| `cakupan` | enum | `semua` / `tindakan` / `barang` |
| `catatan` | textarea | Opsional — alasan kenaikan, referensi SK, dsb |

### 5.2 Konfigurasi Persentase Kenaikan

Persentase dikonfigurasi **per kategori**, bukan flat semua item sama rata.

**Untuk cakupan tindakan:**

| Kategori Master Tindakan | % Kenaikan | Default |
|--------------------------|------------|---------|
| (semua kategori yang ada di `master_tindakan.kategori`) | input % | 0 |

**Untuk cakupan barang:**

| Jenis | % Kenaikan | Default |
|-------|------------|---------|
| `obat` | input % | 0 |
| `alkes` | input % | 0 |
| `bahan_habis_pakai` | input % | 0 |

> **Catatan**: Jika `% = 0` untuk suatu kategori, item dalam kategori tersebut dimasukkan ke proposal dengan harga baru = harga lama (tidak naik), reviewer tetap bisa koreksi manual.

### 5.3 Toggle BPJS

Checkbox terpisah: **"Ikutkan penyesuaian tarif BPJS"**
- Default: `false` (unchecked) — BPJS price tidak ikut diubah
- Jika dicentang: `tarif_bpjs` / `harga_bpjs` juga dikalkulasi dengan % yang sama

### 5.4 Generate Item

Setelah form header disimpan, sistem akan:
1. Query seluruh item aktif sesuai `cakupan`
2. Hitung `harga_baru = ROUND(harga_lama * (1 + persen/100), -2)` — dibulatkan ke ratusan terdekat
3. Buat satu baris `ProposalHargaItem` per item
4. Tampilkan ringkasan: "XX item tindakan · YY item obat · ZZ item alkes dimasukkan ke proposal"

> **Pembulatan ke ratusan terdekat** adalah default umum klinik Indonesia. Ditampilkan eksplisit di UI ("dibulatkan ke Rp 100 terdekat") dan dapat diubah di konfigurasi.

---

## 6. Fitur Detail — Review & Koreksi Per Item

### 6.1 Tampilan Tabel Review

Kolom tabel:

| Kolom | Keterangan |
|-------|------------|
| Nama Item | Nama tindakan / barang |
| Kategori/Jenis | Kategori master tindakan atau jenis barang |
| Harga Lama | Snapshot harga saat proposal dibuat |
| % Naik | Persentase yang dikalkulasi (dari konfigurasi header) |
| Harga Baru (usulan) | Hasil kalkulasi, bisa diedit inline |
| Koreksi Manual | Input angka — jika diisi, override harga usulan kalkulasi |
| Tidak Naik | Checkbox — jika dicentang, harga_baru = harga_lama |
| Harga BPJS Lama | Hanya muncul jika toggle BPJS aktif |
| Harga BPJS Baru | Hanya muncul jika toggle BPJS aktif, editable |

### 6.2 Filter & Navigasi

- Filter by: Kategori/Jenis, "Sudah dikoreksi", "Tidak naik", "Naik > X%"
- Search by nama item
- Paging 20 item/halaman
- Counter: "X item belum direview · Y item sudah dikoreksi"

### 6.3 Bulk Action

- **Terapkan % custom ke kategori ini** — tombol per header-kategori di tabel yang digroup
- **Tandai semua "tidak naik" di halaman ini** — bulk checkbox
- **Reset ke kalkulasi awal** — kembalikan semua koreksi manual ke nilai kalkulasi

### 6.4 Submit ke Persetujuan

Tombol "Submit untuk Persetujuan" muncul ketika status `draft`. Validasi sebelum submit:
- `tanggal_efektif` masih di masa depan
- Minimal 1 item dengan harga_baru ≠ harga_lama (proposal tidak semua "tidak naik")

---

## 7. Fitur Detail — Persetujuan

Halaman detail proposal (baca saja untuk approver, tidak bisa edit) menampilkan:
- Ringkasan: berapa item naik, berapa "tidak naik", range kenaikan min-max
- Preview tabel lengkap (harga lama vs baru) — sama seperti review, tapi read-only
- Tombol **Setujui** (konfirmasi SweetAlert2 dengan catatan opsional)
- Tombol **Tolak / Kembalikan ke Draft** (wajib isi alasan)
- Tombol **Batalkan Proposal**

---

## 8. Fitur Detail — Penerapan Harga

### 8.1 Kondisi Terapkan

Tombol **"Terapkan Sekarang"** hanya aktif jika:
- Status = `disetujui`
- `today() >= tanggal_efektif`

Jika belum sampai tanggal efektif, tampil badge info: _"Akan otomatis berlaku pada DD/MM/YYYY atau bisa diterapkan manual setelah tanggal tersebut."_

### 8.2 Mekanisme Penerapan

Pilihan mekanisme: **Manual Trigger** (lebih aman, tidak ada scheduler tersembunyi)

- Pengguna berwenang klik "Terapkan Sekarang" → konfirmasi SweetAlert2
- Sistem jalankan dalam satu `DB::transaction()`:
  1. Loop semua `ProposalHargaItem` where `is_skip = false`
  2. Update `master_tindakan.tarif` / `barang.harga_jual` ke `harga_baru`
  3. Jika BPJS aktif: update `tarif_bpjs` / `harga_bpjs` ke `harga_bpjs_baru`
  4. Set `ProposalHarga.status = 'efektif'`, `diterapkan_pada = now()`, `diterapkan_oleh = Auth::id()`
- Jika ada error di tengah, transaction rollback — tidak ada partial update

### 8.3 Catatan Penting

- Proposal yang sudah `efektif` **tidak bisa dibalik** (tidak ada rollback harga otomatis)
- Jika perlu koreksi setelah efektif → buat proposal baru
- Harga lama tersimpan di `proposal_harga_item.harga_lama` — dapat dilihat di riwayat

---

## 9. Skema Database

### 9.1 Tabel `proposal_harga`

```sql
CREATE TABLE proposal_harga (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Header
    judul                   VARCHAR(200) NOT NULL,
    tahun                   SMALLINT UNSIGNED NOT NULL,
    tanggal_efektif         DATE NOT NULL,
    cakupan                 ENUM('semua','tindakan','barang') NOT NULL DEFAULT 'semua',
    catatan                 TEXT NULL,

    -- Konfigurasi kenaikan (JSON per kategori)
    -- Format: {"obat": 8, "alkes": 5, "bahan_habis_pakai": 5, "tindakan_umum": 10, ...}
    konfigurasi_kenaikan    JSON NOT NULL DEFAULT ('{}'),

    -- Toggle BPJS
    ikut_bpjs               BOOLEAN NOT NULL DEFAULT FALSE,

    -- Status
    status                  ENUM('draft','menunggu_persetujuan','disetujui','efektif','dibatalkan')
                            NOT NULL DEFAULT 'draft',

    -- Workflow actors
    dibuat_oleh             BIGINT UNSIGNED NOT NULL,
    disetujui_oleh          BIGINT UNSIGNED NULL,
    diterapkan_oleh         BIGINT UNSIGNED NULL,
    ditolak_oleh            BIGINT UNSIGNED NULL,

    -- Workflow timestamps
    disetujui_pada          TIMESTAMP NULL,
    diterapkan_pada         TIMESTAMP NULL,
    ditolak_pada            TIMESTAMP NULL,
    alasan_tolak            TEXT NULL,

    created_at              TIMESTAMP NULL,
    updated_at              TIMESTAMP NULL,

    FOREIGN KEY (dibuat_oleh)    REFERENCES users(id),
    FOREIGN KEY (disetujui_oleh) REFERENCES users(id),
    FOREIGN KEY (diterapkan_oleh) REFERENCES users(id),
    FOREIGN KEY (ditolak_oleh)   REFERENCES users(id)
);
```

### 9.2 Tabel `proposal_harga_item`

```sql
CREATE TABLE proposal_harga_item (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    proposal_harga_id       BIGINT UNSIGNED NOT NULL,

    -- Polymorphic-like: item_type + item_id
    item_type               ENUM('tindakan','barang') NOT NULL,
    item_id                 BIGINT UNSIGNED NOT NULL,  -- master_tindakan.id atau barang.id
    item_nama               VARCHAR(200) NOT NULL,     -- snapshot nama saat proposal dibuat
    item_kategori           VARCHAR(100) NULL,         -- snapshot kategori/jenis

    -- Harga
    harga_lama              DECIMAL(14,2) NOT NULL,    -- snapshot harga saat proposal dibuat
    persen_kenaikan         DECIMAL(5,2) NOT NULL DEFAULT 0,  -- dari konfigurasi header
    harga_kalkulasi         DECIMAL(14,2) NOT NULL,    -- hasil otomatis = harga_lama * (1 + persen/100)
    harga_baru              DECIMAL(14,2) NOT NULL,    -- final setelah koreksi manual

    -- BPJS (NULL jika ikut_bpjs = false)
    harga_bpjs_lama         DECIMAL(14,2) NULL,
    harga_bpjs_baru         DECIMAL(14,2) NULL,

    -- Flag reviewer
    is_dikoreksi_manual     BOOLEAN NOT NULL DEFAULT FALSE,  -- harga_baru berbeda dari harga_kalkulasi
    is_skip                 BOOLEAN NOT NULL DEFAULT FALSE,  -- "tidak naik" dicentang

    -- Audit koreksi
    dikoreksi_oleh          BIGINT UNSIGNED NULL,
    dikoreksi_pada          TIMESTAMP NULL,

    created_at              TIMESTAMP NULL,
    updated_at              TIMESTAMP NULL,

    FOREIGN KEY (proposal_harga_id) REFERENCES proposal_harga(id) ON DELETE CASCADE,
    INDEX idx_proposal_item_type (proposal_harga_id, item_type),
    INDEX idx_item_lookup (item_type, item_id)
);
```

### 9.3 Tidak Ada Tabel Tambahan

- Tidak ada tabel `riwayat_harga` terpisah — snapshot `harga_lama` sudah ada di `proposal_harga_item`
- Audit trail siapa yang koreksi sudah di `dikoreksi_oleh` + `dikoreksi_pada`

---

## 10. Model, Service, & Komponen Livewire

### 10.1 Models

```
app/Models/
  ProposalHarga.php
    - fillable: judul, tahun, tanggal_efektif, cakupan, catatan,
                konfigurasi_kenaikan, ikut_bpjs, status, dibuat_oleh,
                disetujui_oleh, diterapkan_oleh, ditolak_oleh, ...
    - casts: konfigurasi_kenaikan => 'array', tanggal_efektif => 'date',
             ikut_bpjs => 'boolean', disetujui_pada/diterapkan_pada/ditolak_pada => 'datetime'
    - relations: items() hasMany ProposalHargaItem
                 dibuatOleh() belongsTo User
                 disetujuiOleh() belongsTo User
    - scopes: scopeDraft(), scopeMenunggu(), scopeDisetujui(), scopeEfektif()
    - computed: getBisaDiterapkanAttribute() → status=disetujui AND today >= tanggal_efektif
    - computed: getRingkasanAttribute() → count naik, count skip, range %

app/Models/ProposalHargaItem.php
    - fillable: semua kolom di atas
    - casts: harga_lama/kalkulasi/baru/bpjs => 'decimal:2', is_dikoreksi_manual/is_skip => 'boolean'
    - relations: proposal() belongsTo ProposalHarga
    - accessor: getSelisihAttribute() → harga_baru - harga_lama
    - accessor: getPersenAktualAttribute() → (selisih / harga_lama) * 100
```

### 10.2 Services

```
app/Services/Harga/
  ProposalHargaService.php
    + buat(array $data, User $user): ProposalHarga
        - Validasi tanggal_efektif > today
        - Create ProposalHarga (status=draft)
        - generateItems() → query MasterTindakan/Barang aktif sesuai cakupan,
          hitung harga_kalkulasi per item, bulk insert ProposalHargaItem
        - Return ProposalHarga

    + koreksiItem(ProposalHargaItem $item, float $hargaBaru, ?float $hargaBpjsBaru, User $user): void
        - Validasi status proposal = draft
        - Update harga_baru, harga_bpjs_baru
        - Set is_dikoreksi_manual = true, dikoreksi_oleh, dikoreksi_pada

    + toggleSkip(ProposalHargaItem $item, bool $skip, User $user): void
        - Validasi status proposal = draft atau menunggu_persetujuan
        - Set is_skip, jika skip → harga_baru = harga_lama

    + submitReview(ProposalHarga $proposal): void
        - Validasi status = draft
        - Validasi ada minimal 1 item not skipped dengan harga_baru ≠ harga_lama
        - Validasi tanggal_efektif masih di masa depan
        - Update status = menunggu_persetujuan

    + setujui(ProposalHarga $proposal, User $user): void
        - Validasi status = menunggu_persetujuan
        - Update status = disetujui, disetujui_oleh, disetujui_pada

    + tolak(ProposalHarga $proposal, string $alasan, User $user): void
        - Validasi status = menunggu_persetujuan
        - Update status = draft, catatan alasan_tolak

    + batalkan(ProposalHarga $proposal, User $user): void
        - Validasi status IN (draft, menunggu_persetujuan, disetujui)
        - Update status = dibatalkan

    + terapkan(ProposalHarga $proposal, User $user): void
        - Validasi status = disetujui
        - Validasi today() >= tanggal_efektif
        - DB::transaction():
            Loop ProposalHargaItem where is_skip = false:
              if item_type = 'tindakan': MasterTindakan::find(item_id)->update([tarif => harga_baru, ...])
              if item_type = 'barang': Barang::find(item_id)->update([harga_jual => harga_baru, ...])
              if ikut_bpjs: update tarif_bpjs / harga_bpjs
            Update ProposalHarga: status = efektif, diterapkan_oleh, diterapkan_pada
```

### 10.3 Komponen Livewire

```
app/Livewire/Harga/
  ProposalHargaTable.php        ← daftar semua proposal, filter by status/tahun
  ProposalHargaForm.php         ← buat proposal baru (header + konfigurasi %)
  ProposalHargaDetail.php       ← detail proposal: tabel item dengan inline edit
                                   (dipakai untuk role review + approver)
```

### 10.4 Views

```
resources/views/
  harga/
    proposal-index.blade.php    ← wrapper ProposalHargaTable
    proposal-create.blade.php   ← wrapper ProposalHargaForm
    proposal-detail.blade.php   ← wrapper ProposalHargaDetail
  livewire/harga/
    proposal-harga-table.blade.php
    proposal-harga-form.blade.php
    proposal-harga-detail.blade.php
```

---

## 11. Role & Hak Akses

| Permission | Role Default | Keterangan |
|-----------|--------------|-----------|
| `harga.proposal` | Admin, Operator | Buat & edit proposal (hanya status draft) |
| `harga.review` | Admin, Manager | Koreksi per item, submit ke review |
| `harga.setujui` | Manager, SuperAdmin | Setujui / tolak proposal |
| `harga.terapkan` | Manager, SuperAdmin | Terapkan harga ke sistem (final) |
| `harga.lihat` | Semua role di atas | Lihat daftar & detail proposal |

> **Catatan**: Satu orang tidak seharusnya menjadi pembuat dan penyetuju proposal yang sama — ini enforced di UI (tombol setujui tidak muncul jika `dibuat_oleh = Auth::id()`), bukan di database constraint.

---

## 12. Keputusan Desain & Batasan yang Disengaja

### 12.1 Manual Trigger, Bukan Scheduler

Penerapan harga tidak otomatis lewat Laravel scheduler karena:
- Klinik kecil tidak selalu punya server cron terkonfigurasi dengan benar
- Penerapan harga adalah aksi serius — lebih aman ada konfirmasi manusia
- Tanggal efektif di masa depan tetap ada nilainya sebagai "tidak boleh diterapkan sebelum tanggal ini"

### 12.2 Tidak Ada Rollback Otomatis

Setelah status `efektif`, tidak ada undo. Alasannya:
- Harga lama sudah tersimpan di `proposal_harga_item.harga_lama` — dapat dijadikan referensi
- Jika ingin kembali, buat proposal baru dengan harga lama sebagai harga baru
- Rollback otomatis akan rumit karena ada kemungkinan harga sudah terpakai di transaksi baru

### 12.3 Pembulatan ke Rp 100 Terdekat (ROUND ke -2)

- Default praktik klinik Indonesia: harga tidak pernah Rp 17.433 — biasanya Rp 17.400 atau Rp 17.500
- Implementasi: `round($hargaLama * (1 + $persen/100) / 100) * 100`
- Ditampilkan eksplisit di UI sehingga pengguna tahu

### 12.4 Snapshot Nama & Kategori Item

`item_nama` dan `item_kategori` di-snapshot saat proposal dibuat:
- Menghindari confusion jika nama tindakan/barang berganti setelah proposal selesai
- Riwayat proposal tahun lalu tetap terbaca meski data master sudah berubah

### 12.5 `konfigurasi_kenaikan` JSON, Bukan Tabel Terpisah

- Kategori tindakan dan jenis barang tidak banyak dan tidak sering berubah
- JSON lebih fleksibel jika di masa depan ada kategori baru tanpa perlu migrasi
- Disimpan di header proposal sehingga riwayat konfigurasi % per tahun terekam

### 12.6 Harga BPJS Opsional (Toggle)

- Harga BPJS sebagian besar diatur oleh peraturan pemerintah (Permenkes)
- Default `false` agar tidak ada kenaikan BPJS yang tidak disengaja
- Jika klinik memiliki tarif BPJS swasta / Non-Kapitasi sendiri, toggle bisa diaktifkan

### 12.7 `harga_pokok` Tidak Disentuh

- `barang.harga_pokok` adalah Moving Average HPR yang dikelola otomatis oleh proses GRN
- Mengubah harga pokok lewat modul ini akan merusak konsistensi HPP dan laporan laba rugi
- Jika harga beli dari supplier naik, prosesnya tetap lewat GRN (bukan proposal harga)

---

## 13. Fase Implementasi

### Fase 1 — Database & Model
- [ ] Migration: buat tabel `proposal_harga` dan `proposal_harga_item`
- [ ] Model `ProposalHarga` dengan relasi, casts, scopes, computed attributes
- [ ] Model `ProposalHargaItem` dengan relasi, casts, accessors
- [ ] Seeder permission: `harga.proposal`, `harga.review`, `harga.setujui`, `harga.terapkan`, `harga.lihat`

### Fase 2 — Service Layer
- [ ] `ProposalHargaService::buat()` + `generateItems()`
- [ ] `ProposalHargaService::koreksiItem()` + `toggleSkip()`
- [ ] `ProposalHargaService::submitReview()` + `setujui()` + `tolak()` + `batalkan()`
- [ ] `ProposalHargaService::terapkan()` dengan DB::transaction

### Fase 3 — UI Livewire
- [ ] `ProposalHargaTable` — daftar proposal, filter status/tahun, tombol buat baru
- [ ] `ProposalHargaForm` — form buat proposal (header + konfigurasi % per kategori + toggle BPJS)
- [ ] `ProposalHargaDetail` — tabel item dengan inline edit harga, filter, bulk action, tombol workflow
- [ ] SweetAlert2 untuk: submit review, setujui, tolak, batalkan, terapkan
- [ ] Routes: `harga.proposal.index`, `harga.proposal.create`, `harga.proposal.detail`
- [ ] Sidebar entry di menu Pengaturan / Master Data

### Fase 4 — Polish & Validasi Edge Case
- [ ] Validasi proposal duplikat: warning jika sudah ada proposal aktif (draft/menunggu/disetujui) untuk tahun yang sama
- [ ] Handling item yang di-`deactivate` setelah proposal dibuat (tampilkan badge "item nonaktif" di tabel)
- [ ] Export preview tabel ke PDF (opsional, gunakan dompdf yang sudah ada)
- [ ] Unit test `ProposalHargaService::terapkan()` untuk verifikasi harga benar-benar terupdate

---

## 14. Out of Scope

| Hal | Alasan Dikeluarkan |
|-----|-------------------|
| Harga kamar rawat inap | PRD terpisah (rawat inap) |
| Harga kontrak dengan perusahaan/asuransi swasta | Kebutuhan modul kontrak tersendiri |
| Scheduling otomatis (cron) | Sengaja manual trigger — lihat §12.1 |
| Rollback harga ke versi sebelumnya | Buat proposal baru — lihat §12.2 |
| Penyesuaian harga satuan kemasan (per-box, per-strip) | Di luar scope; `harga_jual` per satuan terkecil |
| Approval multi-level (lebih dari 2 tanda tangan) | Klinik skala kecil-menengah; 1 approver cukup |
| Notifikasi email/push ketika proposal masuk review | Infrastruktur notifikasi belum ada di sistem ini |

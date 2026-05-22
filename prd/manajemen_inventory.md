# Product Requirements Document (PRD)
# Manajemen Inventory — Obat & Alat Kesehatan (Alkes)

| Info | Detail |
|:-----|:-------|
| **Versi** | 1.0.0 |
| **Tanggal** | Mei 2026 |
| **Status** | Draft |
| **Depends On** | `PRD_EMR_Laravel.md` · `setup_pasien.md` |
| **Tech Stack** | Laravel 12 · Livewire 3 · MySQL · Tailwind CSS |
| **Scope** | Supplier, Pembelian Obat/Alkes, Penerimaan Barang, Harga Pokok Rata-rata, Alert Stok Minimum |

---

## Daftar Isi

1. [Ringkasan Eksekutif](#1-ringkasan-eksekutif)
2. [Tujuan & Sasaran](#2-tujuan--sasaran)
3. [Role & Hak Akses](#3-role--hak-akses)
4. [Arsitektur Modul](#4-arsitektur-modul)
5. [Skema Database & Migration](#5-skema-database--migration)
6. [Relasi Supplier — Obat/Alkes (One-to-Many)](#6-relasi-supplier--obatalkes-one-to-many)
7. [Algoritma Harga Pokok Rata-rata (HPR)](#7-algoritma-harga-pokok-rata-rata-hpr)
8. [Modul Supplier](#8-modul-supplier)
9. [Modul Master Barang (Obat & Alkes)](#9-modul-master-barang-obat--alkes)
10. [Modul Pembelian Barang](#10-modul-pembelian-barang)
11. [Modul Penerimaan Barang](#11-modul-penerimaan-barang)
12. [Modul Alert Stok Minimum & Prioritas Pembelian](#12-modul-alert-stok-minimum--prioritas-pembelian)
13. [Model Eloquent](#13-model-eloquent)
14. [Repository Layer](#14-repository-layer)
15. [Service Layer](#15-service-layer)
16. [Form Request Validation](#16-form-request-validation)
17. [Livewire Components](#17-livewire-components)
18. [Route & Controller](#18-route--controller)
19. [Struktur Folder](#19-struktur-folder)
20. [User Stories & Business Rules](#20-user-stories--business-rules)
21. [Seeder Data Awal](#21-seeder-data-awal)
22. [Roadmap](#22-roadmap)

---

## 1. Ringkasan Eksekutif

Modul Manajemen Inventory adalah komponen inti sistem EMR yang mengelola siklus pengadaan barang — dari pemesanan ke supplier, penerimaan fisik, hingga update stok dan harga pokok rata-rata (HPR). Modul ini terdiri dari lima sub-modul yang saling terintegrasi:

```
Supplier ──────────────────────────────────────────────────────
  │  one-to-many                                               │
  │                                                            │
  ▼                                                            │
Master Barang (Obat/Alkes)                                     │
  │  stok_minimum trigger                                      │
  │                                                            │
  ▼                                                            │
Alert Stok Minimum ──► Prioritas Pembelian                     │
                              │                                │
                              ▼                                │
                       Purchase Order (PO) ◄────────────────────
                              │  approved
                              ▼
                       Penerimaan Barang (GR)
                              │  update HPR + stok
                              ▼
                       Stok Terkini + Harga Pokok Rata-rata
```

---

## 2. Tujuan & Sasaran

### Tujuan Utama
- Mendigitalisasi proses pembelian dan penerimaan obat/alkes
- Memastikan stok selalu akurat dan harga pokok selalu ter-update
- Mencegah kehabisan stok kritis via alert stok minimum
- Mempercepat proses re-order dengan modul prioritas pembelian

### Sasaran Teknis
- Relasi Supplier ↔ Obat/Alkes: **one-to-many** via tabel `supplier_barang`
- Update **Harga Pokok Rata-rata** otomatis saat penerimaan barang (metode Moving Average)
- **Audit trail** lengkap setiap mutasi stok (Spatie Activity Log)
- Livewire 3 untuk UI reaktif tanpa page reload
- Semua operasi kritis dalam **database transaction**

### KPI
- Akurasi stok ≥ 99.5%
- Harga pokok selalu reflect penerimaan terbaru
- Zero pembelian tanpa PO (nomor PO wajib)
- Alert stok minimum terkirim < 1 jam setelah stok mencapai batas

---

## 3. Role & Hak Akses

| Modul | super_admin | admin | apoteker | petugas_gudang | kasir | dokter | perawat |
|-------|:-----------:|:-----:|:--------:|:--------------:|:-----:|:------:|:-------:|
| Master Supplier | ✅ CRUD | ✅ CRUD | 👁 Read | 👁 Read | ❌ | ❌ | ❌ |
| Master Barang | ✅ CRUD | ✅ CRUD | ✅ CRUD | 👁 Read | ❌ | 👁 Read | 👁 Read |
| Pembelian (PO) | ✅ CRUD | ✅ CRUD | ✅ Create/Edit | ✅ Read | ❌ | ❌ | ❌ |
| Approve PO | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Penerimaan (GR) | ✅ CRUD | ✅ CRUD | ✅ Create | ✅ CRUD | ❌ | ❌ | ❌ |
| Alert Stok Min | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| Laporan Inventory | ✅ | ✅ | ✅ | ✅ | 👁 Read | ❌ | ❌ |

> **petugas_gudang** adalah role tambahan untuk staf gudang/logistik. Ditambahkan ke `RolePermissionSeeder`.

---

## 4. Arsitektur Modul

```
┌─────────────────────────────────────────────────────────────────┐
│                    LIVEWIRE COMPONENTS                          │
│  SupplierTable  │  BarangTable  │  PembelianForm  │  GRForm    │
│  AlertStokList  │  PrioritasPembelian              │  LaporanGR │
└────────────────────────────┬────────────────────────────────────┘
                             │ wire:
┌────────────────────────────▼────────────────────────────────────┐
│                      SERVICE LAYER                              │
│  SupplierService │ BarangService │ PembelianService             │
│  PenerimaanService (update HPR)  │ AlertStokService             │
└────────────────────────────┬────────────────────────────────────┘
                             │
┌────────────────────────────▼────────────────────────────────────┐
│                    REPOSITORY LAYER                             │
│  SupplierRepository │ BarangRepository │ PembelianRepository    │
│  PenerimaanRepository │ MutasiStokRepository                    │
└────────────────────────────┬────────────────────────────────────┘
                             │ Eloquent ORM
┌────────────────────────────▼────────────────────────────────────┐
│                        MySQL 8.0+                               │
│  supplier │ barang │ supplier_barang │ purchase_order           │
│  po_item  │ goods_receipt │ gr_item │ mutasi_stok               │
└─────────────────────────────────────────────────────────────────┘
```

---

## 5. Skema Database & Migration

### 5.1 Urutan Migration

```
2026_01_01_000100_create_supplier_table.php
2026_01_01_000101_create_barang_table.php
2026_01_01_000102_create_supplier_barang_table.php      ← mapping relasi
2026_01_01_000103_create_purchase_order_table.php
2026_01_01_000104_create_po_item_table.php
2026_01_01_000105_create_goods_receipt_table.php
2026_01_01_000106_create_gr_item_table.php
2026_01_01_000107_create_mutasi_stok_table.php
```

---

### 5.2 Tabel `supplier`

```php
// database/migrations/2026_01_01_000100_create_supplier_table.php

Schema::create('supplier', function (Blueprint $table) {
    $table->id();
    $table->string('kode', 20)->unique();             // SUP-001
    $table->string('nama', 150);
    $table->enum('tipe', ['distributor', 'prinsipal', 'apotek', 'lainnya'])
          ->default('distributor');
    $table->string('pic', 100)->nullable();           // Person in charge
    $table->string('telepon', 20)->nullable();
    $table->string('email')->nullable();
    $table->text('alamat')->nullable();
    $table->string('npwp', 30)->nullable();
    $table->unsignedSmallInteger('lead_time_hari')->default(3);  // estimasi hari kirim
    $table->unsignedSmallInteger('top_hari')->default(30);       // terms of payment (hari)
    $table->boolean('is_active')->default(true);
    $table->timestamps();

    $table->index(['nama', 'is_active']);
});
```

---

### 5.3 Tabel `barang`

```php
// database/migrations/2026_01_01_000101_create_barang_table.php

Schema::create('barang', function (Blueprint $table) {
    $table->id();
    $table->string('kode', 20)->unique();               // BRG-000001
    $table->string('nama', 150);
    $table->string('nama_generik', 150)->nullable();
    $table->enum('jenis', ['obat', 'alkes', 'bahan_habis_pakai', 'lainnya']);
    $table->string('kategori', 50)->nullable();         // antibiotik, analgesik, dll
    $table->string('satuan', 20);                       // tablet, kapsul, ml, pcs
    $table->string('satuan_besar', 20)->nullable();     // box, karton
    $table->unsignedSmallInteger('isi_satuan_besar')->nullable(); // 100 tablet/box
    $table->string('kemasan', 50)->nullable();          // strip, botol, dll

    // Stok & harga
    $table->unsignedInteger('stok')->default(0);
    $table->unsignedInteger('stok_minimum')->default(10);  // threshold alert
    $table->unsignedInteger('stok_maksimum')->nullable();  // batas order maksimum
    $table->decimal('harga_pokok', 14, 2)->default(0);    // HPR (moving average)
    $table->decimal('harga_jual', 14, 2)->default(0);

    // Info tambahan
    $table->string('golongan', 20)->nullable();  // bebas, keras, narkotika, psikotropika
    $table->boolean('butuh_resep')->default(false);
    $table->boolean('is_active')->default(true);

    // Supplier utama (untuk referensi cepat, relasi lengkap via supplier_barang)
    $table->foreignId('supplier_utama_id')->nullable()
          ->constrained('supplier')->nullOnDelete();

    $table->timestamps();

    $table->index(['nama', 'jenis', 'is_active']);
    $table->index('stok');
    $table->index('stok_minimum');
});
```

---

### 5.4 Tabel `supplier_barang` (Pivot — One-to-Many)

```php
// database/migrations/2026_01_01_000102_create_supplier_barang_table.php
// Relasi: 1 barang → banyak supplier yang menyediakan

Schema::create('supplier_barang', function (Blueprint $table) {
    $table->id();
    $table->foreignId('barang_id')
          ->constrained('barang')->onDelete('cascade');
    $table->foreignId('supplier_id')
          ->constrained('supplier')->onDelete('cascade');
    $table->string('kode_barang_supplier', 50)->nullable(); // kode item di katalog supplier
    $table->string('nama_barang_supplier', 150)->nullable();// nama di faktur supplier
    $table->decimal('harga_terakhir', 14, 2)->nullable();   // harga beli terakhir dari supplier ini
    $table->boolean('is_supplier_utama')->default(false);   // supplier prioritas
    $table->timestamps();

    $table->unique(['barang_id', 'supplier_id']);           // 1 supplier tidak boleh duplikat per barang
    $table->index('barang_id');
    $table->index('supplier_id');
});
```

> **Catatan relasi:** Satu barang dapat memiliki banyak supplier (`one-to-many` dari perspektif barang). Field `is_supplier_utama = true` menandai supplier prioritas untuk auto-select saat membuat PO.

---

### 5.5 Tabel `purchase_order`

```php
// database/migrations/2026_01_01_000103_create_purchase_order_table.php

Schema::create('purchase_order', function (Blueprint $table) {
    $table->id();
    $table->string('nomor_po', 30)->unique();           // PO-2026-05-0001
    $table->foreignId('supplier_id')
          ->constrained('supplier')->onDelete('restrict');
    $table->foreignId('dibuat_oleh')
          ->constrained('users')->onDelete('restrict');
    $table->foreignId('disetujui_oleh')->nullable()
          ->constrained('users')->nullOnDelete();

    $table->date('tanggal_po');
    $table->date('tanggal_kirim_estimasi')->nullable();  // estimasi tiba
    $table->date('tanggal_disetujui')->nullable();

    $table->enum('status', [
        'draft',       // baru dibuat, belum dikirim
        'dikirim',     // sudah dikirim ke supplier
        'sebagian',    // sebagian item sudah diterima
        'selesai',     // semua item diterima
        'dibatalkan',  // dibatalkan
    ])->default('draft');

    $table->decimal('total_nilai', 16, 2)->default(0);  // kalkulasi otomatis
    $table->text('catatan')->nullable();
    $table->timestamps();

    $table->index(['supplier_id', 'status']);
    $table->index('tanggal_po');
});
```

---

### 5.6 Tabel `po_item`

```php
// database/migrations/2026_01_01_000104_create_po_item_table.php

Schema::create('po_item', function (Blueprint $table) {
    $table->id();
    $table->foreignId('purchase_order_id')
          ->constrained('purchase_order')->onDelete('cascade');
    $table->foreignId('barang_id')
          ->constrained('barang')->onDelete('restrict');

    $table->unsignedInteger('jumlah_pesan');
    $table->decimal('harga_satuan', 14, 2);              // harga negosiasi dengan supplier
    $table->decimal('diskon_persen', 5, 2)->default(0);
    $table->decimal('subtotal', 14, 2);                  // kalkulasi otomatis
    $table->unsignedInteger('jumlah_diterima')->default(0); // update saat penerimaan

    $table->timestamps();

    $table->index('purchase_order_id');
    $table->index('barang_id');
});
```

---

### 5.7 Tabel `goods_receipt` (Penerimaan Barang)

```php
// database/migrations/2026_01_01_000105_create_goods_receipt_table.php

Schema::create('goods_receipt', function (Blueprint $table) {
    $table->id();
    $table->string('nomor_gr', 30)->unique();            // GR-2026-05-0001
    $table->foreignId('purchase_order_id')->nullable()
          ->constrained('purchase_order')->nullOnDelete();
    $table->foreignId('supplier_id')
          ->constrained('supplier')->onDelete('restrict');
    $table->foreignId('diterima_oleh')
          ->constrained('users')->onDelete('restrict');

    $table->date('tanggal_terima');
    $table->string('nomor_faktur_supplier', 50)->nullable(); // nomor invoice supplier
    $table->date('tanggal_faktur')->nullable();
    $table->date('tanggal_jatuh_tempo')->nullable();         // berdasarkan TOP supplier
    $table->string('nomor_surat_jalan', 50)->nullable();

    $table->decimal('total_nilai', 16, 2)->default(0);
    $table->enum('status', ['draft', 'diverifikasi', 'dibatalkan'])->default('draft');
    $table->text('catatan')->nullable();
    $table->timestamps();

    $table->index(['supplier_id', 'tanggal_terima']);
    $table->index('purchase_order_id');
});
```

---

### 5.8 Tabel `gr_item` (Item Penerimaan)

```php
// database/migrations/2026_01_01_000106_create_gr_item_table.php

Schema::create('gr_item', function (Blueprint $table) {
    $table->id();
    $table->foreignId('goods_receipt_id')
          ->constrained('goods_receipt')->onDelete('cascade');
    $table->foreignId('barang_id')
          ->constrained('barang')->onDelete('restrict');
    $table->foreignId('po_item_id')->nullable()
          ->constrained('po_item')->nullOnDelete();

    $table->unsignedInteger('jumlah_terima');
    $table->decimal('harga_satuan', 14, 2);              // harga aktual di faktur
    $table->decimal('diskon_persen', 5, 2)->default(0);
    $table->decimal('subtotal', 14, 2);

    // Batch & expired tracking
    $table->string('nomor_batch', 50)->nullable();
    $table->date('expired_date')->nullable();

    // HPR snapshot — disimpan saat verifikasi GR
    $table->decimal('hpr_sebelum', 14, 2)->default(0);  // HPR barang sebelum penerimaan ini
    $table->decimal('hpr_sesudah', 14, 2)->default(0);  // HPR setelah penerimaan ini

    $table->timestamps();

    $table->index('goods_receipt_id');
    $table->index('barang_id');
    $table->index('expired_date');
});
```

---

### 5.9 Tabel `mutasi_stok`

```php
// database/migrations/2026_01_01_000107_create_mutasi_stok_table.php

Schema::create('mutasi_stok', function (Blueprint $table) {
    $table->id();
    $table->foreignId('barang_id')
          ->constrained('barang')->onDelete('restrict');
    $table->foreignId('user_id')
          ->constrained('users')->onDelete('restrict');

    $table->enum('tipe', [
        'masuk_pembelian',   // dari GR
        'keluar_resep',      // dari dispensing farmasi
        'keluar_tindakan',   // dari pemakaian tindakan
        'penyesuaian_masuk', // stock opname tambah
        'penyesuaian_keluar',// stock opname kurang
        'retur_ke_supplier', // return barang ke supplier
        'expired',           // disposal barang expired
    ]);

    $table->unsignedInteger('jumlah');
    $table->unsignedInteger('stok_sebelum');
    $table->unsignedInteger('stok_sesudah');
    $table->decimal('hpr_sebelum', 14, 2)->default(0);
    $table->decimal('hpr_sesudah', 14, 2)->default(0);

    // Referensi sumber mutasi
    $table->string('referensi_tipe', 50)->nullable();  // goods_receipt, resep, tindakan, dll
    $table->unsignedBigInteger('referensi_id')->nullable();

    $table->text('keterangan')->nullable();
    $table->timestamps();

    $table->index(['barang_id', 'created_at']);
    $table->index(['referensi_tipe', 'referensi_id']);
});
```

---

## 6. Relasi Supplier — Obat/Alkes (One-to-Many)

### Diagram Relasi

```
supplier (1) ─────────────────────── (many) supplier_barang (many) ─── (1) barang
    │                                              │
    │  id                               barang_id │ supplier_id
    │                                              │
    └──────────────────────────────────────────────┘
    
    Perspektif dari Barang:
    ┌──────────────────────────────────────────────────────┐
    │ Paracetamol 500mg (barang_id: 1)                    │
    │   ├── Supplier A (Kimia Farma)    → harga 850/tab   │
    │   ├── Supplier B (Enseval)        → harga 820/tab   │
    │   └── Supplier C (Indo Farma)     → harga 900/tab   │
    │       is_supplier_utama = true → Supplier B         │
    └──────────────────────────────────────────────────────┘
    
    Perspektif dari Supplier:
    ┌──────────────────────────────────────────────────────┐
    │ Kimia Farma (supplier_id: 1)                        │
    │   ├── Paracetamol 500mg                              │
    │   ├── Amoxicillin 500mg                              │
    │   ├── Infus RL 500ml                                 │
    │   └── Spuit 3ml (Alkes)                              │
    └──────────────────────────────────────────────────────┘
```

### Business Rules Relasi

```
✓ 1 barang dapat dipasok oleh banyak supplier
✓ 1 supplier dapat memasok banyak barang
✓ Hanya boleh 1 supplier_utama per barang (is_supplier_utama = true)
✓ Saat membuat PO, supplier & daftar barangnya ditampilkan dari relasi ini
✓ Harga terakhir di supplier_barang diperbarui setiap kali GR dari supplier tsb diverifikasi
✓ Jika supplier dinonaktifkan, relasi supplier_barang tetap ada (data historis)
```

---

## 7. Algoritma Harga Pokok Rata-rata (HPR)

### Metode: Moving Average (Rata-rata Bergerak)

HPR diperbarui **setiap kali GR diverifikasi**, menggunakan rumus:

```
HPR Baru = (Stok Lama × HPR Lama) + (Jumlah Terima × Harga Beli)
           ─────────────────────────────────────────────────────
                        Stok Lama + Jumlah Terima
```

### Contoh Kalkulasi

```
Kondisi awal:
  Stok Paracetamol  = 200 tablet
  HPR saat ini      = Rp 850/tablet
  Nilai stok lama   = 200 × 850 = Rp 170.000

Penerimaan baru:
  Jumlah terima     = 500 tablet
  Harga beli        = Rp 800/tablet
  Nilai baru        = 500 × 800 = Rp 400.000

Kalkulasi HPR baru:
  HPR Baru = (170.000 + 400.000) / (200 + 500)
           = 570.000 / 700
           = Rp 814,29/tablet

Update ke database:
  barang.stok        = 700
  barang.harga_pokok = 814.29
```

### Kasus Khusus

| Kondisi | Penanganan |
|---------|-----------|
| Stok lama = 0 | HPR Baru = Harga Beli (tidak ada pembagi negatif) |
| GR dibatalkan | Rollback stok dan HPR ke snapshot `hpr_sebelum` di `gr_item` |
| Diskon dari supplier | Harga beli efektif = `harga_satuan × (1 - diskon_persen/100)` |
| Multi-item dalam 1 GR | Proses item satu per satu secara berurutan dalam 1 transaction |

---

## 8. Modul Supplier

### 8.1 Fitur
- CRUD supplier (kode, nama, tipe, PIC, kontak, NPWP, lead time, TOP)
- Mapping barang ke supplier (tambah/hapus/set utama)
- Riwayat pembelian per supplier
- Nonaktifkan supplier (soft delete via `is_active`)

### 8.2 Livewire Component

```php
// app/Livewire/Inventory/Supplier/SupplierTable.php

namespace App\Livewire\Inventory\Supplier;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Supplier;

class SupplierTable extends Component
{
    use WithPagination;

    public string $search   = '';
    public string $tipe     = '';
    public string $sortBy   = 'nama';
    public string $sortDir  = 'asc';
    public int    $perPage  = 15;

    protected $queryString = ['search', 'tipe'];

    public function updatingSearch(): void { $this->resetPage(); }

    public function render()
    {
        $suppliers = Supplier::query()
            ->when($this->search, fn($q) => $q
                ->where('nama',  'like', "%{$this->search}%")
                ->orWhere('kode', 'like', "%{$this->search}%")
            )
            ->when($this->tipe, fn($q) => $q->where('tipe', $this->tipe))
            ->withCount('barang')    // jumlah item yang disupply
            ->orderBy($this->sortBy, $this->sortDir)
            ->paginate($this->perPage);

        return view('livewire.inventory.supplier.supplier-table', compact('suppliers'));
    }
}
```

```php
// app/Livewire/Inventory/Supplier/SupplierForm.php

namespace App\Livewire\Inventory\Supplier;

use Livewire\Component;
use App\Models\Supplier;
use App\Services\Inventory\SupplierService;

class SupplierForm extends Component
{
    public ?Supplier $supplier = null;
    public bool      $isEdit   = false;

    public string $kode        = '';
    public string $nama        = '';
    public string $tipe        = 'distributor';
    public string $pic         = '';
    public string $telepon     = '';
    public string $email       = '';
    public string $alamat      = '';
    public string $npwp        = '';
    public int    $leadTimeHari = 3;
    public int    $topHari     = 30;

    protected function rules(): array
    {
        $supplierId = $this->supplier?->id;
        return [
            'kode'        => ['required', 'string', 'max:20',
                              \Illuminate\Validation\Rule::unique('supplier', 'kode')
                                ->ignore($supplierId)],
            'nama'        => ['required', 'string', 'max:150'],
            'tipe'        => ['required', \Illuminate\Validation\Rule::in([
                                'distributor', 'prinsipal', 'apotek', 'lainnya'])],
            'pic'         => ['nullable', 'string', 'max:100'],
            'telepon'     => ['nullable', 'string', 'max:20'],
            'email'       => ['nullable', 'email'],
            'alamat'      => ['nullable', 'string'],
            'npwp'        => ['nullable', 'string', 'max:30'],
            'leadTimeHari'=> ['required', 'integer', 'min:0', 'max:365'],
            'topHari'     => ['required', 'integer', 'min:0', 'max:365'],
        ];
    }

    public function mount(?Supplier $supplier = null): void
    {
        if ($supplier?->exists) {
            $this->isEdit   = true;
            $this->supplier = $supplier;
            $this->kode        = $supplier->kode;
            $this->nama        = $supplier->nama;
            $this->tipe        = $supplier->tipe;
            $this->pic         = $supplier->pic ?? '';
            $this->telepon     = $supplier->telepon ?? '';
            $this->email       = $supplier->email ?? '';
            $this->alamat      = $supplier->alamat ?? '';
            $this->npwp        = $supplier->npwp ?? '';
            $this->leadTimeHari= $supplier->lead_time_hari;
            $this->topHari     = $supplier->top_hari;
        } else {
            $this->kode = app(SupplierService::class)->generateKode();
        }
    }

    public function save(SupplierService $service): void
    {
        $data = $this->validate();
        $data = array_merge($data, [
            'lead_time_hari' => $this->leadTimeHari,
            'top_hari'       => $this->topHari,
        ]);
        unset($data['leadTimeHari'], $data['topHari']);

        if ($this->isEdit) {
            $service->update($this->supplier, $data);
            session()->flash('success', 'Data supplier berhasil diperbarui.');
        } else {
            $service->create($data);
            session()->flash('success', 'Supplier berhasil ditambahkan.');
        }

        $this->redirectRoute('inventory.supplier.index');
    }

    public function render()
    {
        return view('livewire.inventory.supplier.supplier-form');
    }
}
```

---

## 9. Modul Master Barang (Obat & Alkes)

### 9.1 Fitur
- CRUD master barang (kode, nama, jenis, satuan, stok minimum)
- Mapping supplier ke barang via `supplier_barang` (Livewire modal)
- Set supplier utama
- Lihat riwayat mutasi stok per barang
- Alert visual jika stok ≤ stok_minimum

### 9.2 Blade View — Badge Stok

```blade
{{-- resources/views/components/inventory/stok-badge.blade.php --}}
@props(['stok', 'stokMinimum'])

@php
    $ratio = $stokMinimum > 0 ? $stok / $stokMinimum : 1;
    if ($stok === 0) {
        $class = 'badge-danger';
        $label = 'Habis';
    } elseif ($ratio <= 1) {
        $class = 'badge-warning';
        $label = "Kritis ({$stok})";
    } elseif ($ratio <= 1.5) {
        $class = 'badge-info';
        $label = "Hampir Habis ({$stok})";
    } else {
        $class = 'badge-success';
        $label = $stok;
    }
@endphp

<span class="{{ $class }}">{{ $label }}</span>
```

### 9.3 Livewire — Mapping Supplier ke Barang

```php
// app/Livewire/Inventory/Barang/SupplierMappingModal.php

namespace App\Livewire\Inventory\Barang;

use Livewire\Component;
use App\Models\{Barang, Supplier, SupplierBarang};

class SupplierMappingModal extends Component
{
    public bool   $show     = false;
    public ?int   $barangId = null;
    public array  $mappings = [];

    // Form tambah mapping baru
    public int    $newSupplierId         = 0;
    public string $newKodeBarangSupplier = '';
    public string $newHargaTerakhir      = '';
    public bool   $newIsUtama            = false;

    protected $listeners = ['openSupplierMapping' => 'open'];

    public function open(int $barangId): void
    {
        $this->barangId = $barangId;
        $this->loadMappings();
        $this->show = true;
    }

    public function loadMappings(): void
    {
        $this->mappings = SupplierBarang::with('supplier')
            ->where('barang_id', $this->barangId)
            ->get()
            ->toArray();
    }

    public function addMapping(): void
    {
        $this->validate([
            'newSupplierId' => ['required', 'exists:supplier,id'],
            'newHargaTerakhir' => ['nullable', 'numeric', 'min:0'],
        ]);

        // Jika set utama → unset semua yang lain dulu
        if ($this->newIsUtama) {
            SupplierBarang::where('barang_id', $this->barangId)
                ->update(['is_supplier_utama' => false]);
        }

        SupplierBarang::updateOrCreate(
            ['barang_id' => $this->barangId, 'supplier_id' => $this->newSupplierId],
            [
                'kode_barang_supplier' => $this->newKodeBarangSupplier ?: null,
                'harga_terakhir'       => $this->newHargaTerakhir ?: null,
                'is_supplier_utama'    => $this->newIsUtama,
            ]
        );

        $this->reset(['newSupplierId','newKodeBarangSupplier','newHargaTerakhir','newIsUtama']);
        $this->loadMappings();

        // Update supplier_utama_id di tabel barang jika diperlukan
        if ($this->newIsUtama) {
            Barang::where('id', $this->barangId)
                ->update(['supplier_utama_id' => $this->newSupplierId]);
        }
    }

    public function setUtama(int $supplierBarangId): void
    {
        SupplierBarang::where('barang_id', $this->barangId)
            ->update(['is_supplier_utama' => false]);

        $sb = SupplierBarang::find($supplierBarangId);
        $sb->update(['is_supplier_utama' => true]);

        Barang::where('id', $this->barangId)
            ->update(['supplier_utama_id' => $sb->supplier_id]);

        $this->loadMappings();
    }

    public function removeMapping(int $supplierBarangId): void
    {
        $sb = SupplierBarang::findOrFail($supplierBarangId);

        // Jangan hapus kalau ini satu-satunya mapping
        $count = SupplierBarang::where('barang_id', $this->barangId)->count();
        if ($count <= 1) {
            $this->addError('mapping', 'Minimal harus ada 1 supplier terdaftar.');
            return;
        }

        // Jika yang dihapus adalah utama, set yang tersisa sebagai utama
        if ($sb->is_supplier_utama) {
            $next = SupplierBarang::where('barang_id', $this->barangId)
                ->where('id', '!=', $supplierBarangId)->first();
            $next?->update(['is_supplier_utama' => true]);
            Barang::where('id', $this->barangId)
                ->update(['supplier_utama_id' => $next?->supplier_id]);
        }

        $sb->delete();
        $this->loadMappings();
    }

    public function render()
    {
        $suppliers = Supplier::where('is_active', true)
            ->orderBy('nama')
            ->get();

        return view('livewire.inventory.barang.supplier-mapping-modal',
            compact('suppliers'));
    }
}
```

---

## 10. Modul Pembelian Barang (Purchase Order)

### 10.1 Alur Kerja

```
Draft PO → Dikirim ke Supplier → [Penerimaan Sebagian] → Selesai
    │
    └─► Dibatalkan (jika belum ada penerimaan)
```

### 10.2 Nomor PO — Auto-Generate

```
Format: PO-YYYY-MM-NNNN
Contoh: PO-2026-05-0001
        PO-2026-05-0002
        PO-2026-06-0001  ← reset per bulan
```

### 10.3 Livewire Component — Form PO

```php
// app/Livewire/Inventory/PurchaseOrder/PoForm.php

namespace App\Livewire\Inventory\PurchaseOrder;

use Livewire\Component;
use App\Models\{PurchaseOrder, Supplier, Barang, SupplierBarang};
use App\Services\Inventory\PembelianService;

class PoForm extends Component
{
    public ?PurchaseOrder $po = null;
    public bool           $isEdit = false;

    // Header PO
    public int    $supplierId          = 0;
    public string $tanggalPo           = '';
    public string $tanggalKirimEstimasi = '';
    public string $catatan             = '';

    // Items (array dinamis)
    public array  $items = [];

    // Search barang
    public string $searchBarang = '';
    public array  $hasilSearchBarang = [];

    protected function rules(): array
    {
        return [
            'supplierId'            => ['required', 'exists:supplier,id'],
            'tanggalPo'             => ['required', 'date'],
            'tanggalKirimEstimasi'  => ['nullable', 'date', 'after_or_equal:tanggalPo'],
            'items'                 => ['required', 'array', 'min:1'],
            'items.*.barang_id'     => ['required', 'exists:barang,id'],
            'items.*.jumlah_pesan'  => ['required', 'integer', 'min:1'],
            'items.*.harga_satuan'  => ['required', 'numeric', 'min:0'],
            'items.*.diskon_persen' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    public function mount(?PurchaseOrder $po = null): void
    {
        $this->tanggalPo = now()->format('Y-m-d');

        if ($po?->exists) {
            $this->isEdit   = true;
            $this->po       = $po;
            $this->supplierId           = $po->supplier_id;
            $this->tanggalPo           = $po->tanggal_po->format('Y-m-d');
            $this->tanggalKirimEstimasi = $po->tanggal_kirim_estimasi?->format('Y-m-d') ?? '';
            $this->catatan             = $po->catatan ?? '';
            $this->items = $po->items->map(fn($i) => [
                'barang_id'     => $i->barang_id,
                'nama_barang'   => $i->barang->nama,
                'satuan'        => $i->barang->satuan,
                'jumlah_pesan'  => $i->jumlah_pesan,
                'harga_satuan'  => $i->harga_satuan,
                'diskon_persen' => $i->diskon_persen,
                'subtotal'      => $i->subtotal,
            ])->toArray();
        }
    }

    // Load barang hanya dari supplier yang dipilih
    public function updatedSupplierId(): void
    {
        $this->items = [];
        $this->searchBarang = '';
    }

    public function searchBarang(): void
    {
        if (strlen($this->searchBarang) < 2) {
            $this->hasilSearchBarang = [];
            return;
        }

        $this->hasilSearchBarang = Barang::whereHas('suppliers', fn($q) =>
                $q->where('supplier_id', $this->supplierId)
            )
            ->where('is_active', true)
            ->where(fn($q) => $q
                ->where('nama', 'like', "%{$this->searchBarang}%")
                ->orWhere('kode', 'like', "%{$this->searchBarang}%")
            )
            ->with(['supplierBarang' => fn($q) =>
                $q->where('supplier_id', $this->supplierId)
            ])
            ->limit(10)
            ->get()
            ->map(fn($b) => [
                'id'              => $b->id,
                'kode'            => $b->kode,
                'nama'            => $b->nama,
                'satuan'          => $b->satuan,
                'stok'            => $b->stok,
                'stok_minimum'    => $b->stok_minimum,
                'harga_terakhir'  => $b->supplierBarang->first()?->harga_terakhir ?? 0,
            ])
            ->toArray();
    }

    public function addItem(int $barangId): void
    {
        // Cegah duplikat
        if (collect($this->items)->pluck('barang_id')->contains($barangId)) {
            return;
        }

        $barang = Barang::with(['supplierBarang' => fn($q) =>
            $q->where('supplier_id', $this->supplierId)
        ])->findOrFail($barangId);

        $hargaTerakhir = $barang->supplierBarang->first()?->harga_terakhir ?? 0;

        $this->items[] = [
            'barang_id'     => $barang->id,
            'nama_barang'   => $barang->nama,
            'satuan'        => $barang->satuan,
            'jumlah_pesan'  => 1,
            'harga_satuan'  => $hargaTerakhir,
            'diskon_persen' => 0,
            'subtotal'      => $hargaTerakhir,
        ];

        $this->searchBarang = '';
        $this->hasilSearchBarang = [];
    }

    public function removeItem(int $index): void
    {
        array_splice($this->items, $index, 1);
    }

    public function updatedItems(): void
    {
        // Recalculate subtotal tiap kali item berubah
        foreach ($this->items as $i => $item) {
            $harga   = (float)($item['harga_satuan']  ?? 0);
            $jumlah  = (int)  ($item['jumlah_pesan']  ?? 0);
            $diskon  = (float)($item['diskon_persen'] ?? 0);
            $this->items[$i]['subtotal'] = $harga * $jumlah * (1 - $diskon / 100);
        }
    }

    public function getTotalNilaiProperty(): float
    {
        return collect($this->items)->sum('subtotal');
    }

    public function save(PembelianService $service): void
    {
        $this->validate();

        $data = [
            'supplier_id'             => $this->supplierId,
            'tanggal_po'              => $this->tanggalPo,
            'tanggal_kirim_estimasi'  => $this->tanggalKirimEstimasi ?: null,
            'catatan'                 => $this->catatan ?: null,
            'total_nilai'             => $this->totalNilai,
            'dibuat_oleh'             => auth()->id(),
            'items'                   => $this->items,
        ];

        if ($this->isEdit) {
            $service->updatePo($this->po, $data);
            session()->flash('success', 'Purchase Order berhasil diperbarui.');
        } else {
            $po = $service->buatPo($data);
            session()->flash('success', "PO {$po->nomor_po} berhasil dibuat.");
            $this->redirectRoute('inventory.po.show', $po);
            return;
        }

        $this->redirectRoute('inventory.po.show', $this->po);
    }

    public function render()
    {
        $suppliers = Supplier::where('is_active', true)->orderBy('nama')->get();

        return view('livewire.inventory.purchase-order.po-form', [
            'suppliers'  => $suppliers,
            'totalNilai' => $this->totalNilai,
        ]);
    }
}
```

### 10.4 Blade View — Po Form (ringkas)

```blade
{{-- resources/views/livewire/inventory/purchase-order/po-form.blade.php --}}
<div class="space-y-6">

    {{-- Header PO --}}
    <div class="card">
        <div class="card-header"><h3 class="text-sm font-semibold text-gray-700">Header Purchase Order</h3></div>
        <div class="card-body grid grid-cols-1 md:grid-cols-2 gap-4">

            <div class="form-group">
                <label class="form-label">Supplier <span class="text-red-500">*</span></label>
                <select wire:model.live="supplierId" class="form-select">
                    <option value="">— Pilih Supplier —</option>
                    @foreach($suppliers as $s)
                        <option value="{{ $s->id }}">{{ $s->kode }} — {{ $s->nama }}</option>
                    @endforeach
                </select>
                @error('supplierId') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div class="form-group">
                <label class="form-label">Tanggal PO <span class="text-red-500">*</span></label>
                <input type="date" wire:model="tanggalPo" class="form-input" />
            </div>

            <div class="form-group">
                <label class="form-label">Estimasi Tiba</label>
                <input type="date" wire:model="tanggalKirimEstimasi" class="form-input" />
            </div>

            <div class="form-group md:col-span-2">
                <label class="form-label">Catatan</label>
                <textarea wire:model="catatan" rows="2" class="form-textarea" placeholder="Opsional"></textarea>
            </div>
        </div>
    </div>

    {{-- Pencarian Barang --}}
    @if($supplierId)
    <div class="card">
        <div class="card-header"><h3 class="text-sm font-semibold text-gray-700">Tambah Item</h3></div>
        <div class="card-body">
            <div class="relative">
                <input type="text" wire:model.live.debounce.300ms="searchBarang"
                    wire:keydown.enter="searchBarang"
                    class="form-input pl-9"
                    placeholder="Cari nama/kode barang dari supplier ini..." />
                <span class="absolute inset-y-0 left-3 flex items-center text-gray-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z"/>
                    </svg>
                </span>
            </div>

            {{-- Dropdown hasil search --}}
            @if(!empty($hasilSearchBarang))
            <div class="mt-1 border border-gray-200 rounded-lg divide-y divide-gray-100 shadow-card">
                @foreach($hasilSearchBarang as $b)
                <button type="button" wire:click="addItem({{ $b['id'] }})"
                    class="w-full text-left px-4 py-2.5 hover:bg-gray-50 transition-colors text-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <span class="font-medium text-gray-900">{{ $b['nama'] }}</span>
                            <span class="text-gray-400 ml-2 text-xs">{{ $b['kode'] }}</span>
                        </div>
                        <div class="flex items-center gap-3 text-xs">
                            <x-inventory.stok-badge :stok="$b['stok']" :stok-minimum="$b['stok_minimum']" />
                            <span class="text-gray-500">Rp {{ number_format($b['harga_terakhir'],0,',','.') }}</span>
                        </div>
                    </div>
                </button>
                @endforeach
            </div>
            @endif
        </div>
    </div>
    @endif

    {{-- Tabel Item PO --}}
    @if(!empty($items))
    <div class="card">
        <div class="card-header"><h3 class="text-sm font-semibold text-gray-700">Daftar Item</h3></div>
        <div class="card-body p-0">
            <div class="table-wrapper rounded-none border-0">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Nama Barang</th>
                            <th>Satuan</th>
                            <th>Jumlah Pesan</th>
                            <th>Harga Satuan (Rp)</th>
                            <th>Diskon (%)</th>
                            <th class="text-right">Subtotal (Rp)</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($items as $i => $item)
                        <tr>
                            <td class="font-medium text-gray-900">{{ $item['nama_barang'] }}</td>
                            <td class="text-gray-500">{{ $item['satuan'] }}</td>
                            <td>
                                <input type="number" wire:model.live="items.{{ $i }}.jumlah_pesan"
                                    class="form-input w-24 text-center"
                                    min="1" />
                            </td>
                            <td>
                                <input type="number" wire:model.live="items.{{ $i }}.harga_satuan"
                                    class="form-input w-36"
                                    min="0" step="0.01" />
                            </td>
                            <td>
                                <input type="number" wire:model.live="items.{{ $i }}.diskon_persen"
                                    class="form-input w-20 text-center"
                                    min="0" max="100" step="0.01" />
                            </td>
                            <td class="text-right font-medium">
                                {{ number_format($item['subtotal'] ?? 0, 0, ',', '.') }}
                            </td>
                            <td>
                                <button type="button" wire:click="removeItem({{ $i }})"
                                    class="text-red-400 hover:text-red-600">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="bg-gray-50 font-semibold">
                            <td colspan="5" class="px-4 py-3 text-right text-gray-700">Total Nilai PO</td>
                            <td class="px-4 py-3 text-right text-gray-900">
                                Rp {{ number_format($totalNilai, 0, ',', '.') }}
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    {{-- Tombol Submit --}}
    <div class="flex justify-end gap-3">
        <a href="{{ route('inventory.po.index') }}" class="btn-secondary">Batal</a>
        <button type="button" wire:click="save" wire:loading.attr="disabled" class="btn-primary">
            <span wire:loading.remove>{{ $isEdit ? 'Simpan Perubahan' : 'Buat PO' }}</span>
            <span wire:loading class="flex items-center gap-2">
                <div class="spinner w-4 h-4"></div> Menyimpan...
            </span>
        </button>
    </div>
    @endif
</div>
```

---

## 11. Modul Penerimaan Barang (Goods Receipt)

### 11.1 Alur Kerja

```
Pilih PO yang sudah dikirim
    │
    ▼
Input jumlah diterima per item
    + nomor faktur supplier
    + nomor batch & expired date per item
    │
    ▼
Simpan sebagai Draft GR
    │
    ▼
Verifikasi GR ──────────────────────────────────────────────────────
    │  (trigger otomatis)                                           │
    ├── Update stok barang (+ jumlah_terima)                        │
    ├── Hitung HPR baru (moving average)                            │
    ├── Catat mutasi_stok (tipe: masuk_pembelian)                   │
    ├── Update harga_terakhir di supplier_barang                    │
    ├── Update jumlah_diterima di po_item                           │
    └── Update status PO (sebagian / selesai)                       │
                                                                    │
GR Dibatalkan (hanya jika status = draft) ──────────────────────────
    └── Rollback stok & HPR ke snapshot hpr_sebelum
```

### 11.2 Service — Verifikasi GR dengan Update HPR

```php
// app/Services/Inventory/PenerimaanService.php

namespace App\Services\Inventory;

use App\Models\{GoodsReceipt, GrItem, Barang, PoItem, MutasiStok, SupplierBarang};
use Illuminate\Support\Facades\DB;

class PenerimaanService
{
    public function buatGr(array $data): GoodsReceipt
    {
        return DB::transaction(function () use ($data) {
            $nomorGr = $this->generateNomorGr();
            $items   = $data['items'] ?? [];
            unset($data['items']);

            // Hitung total nilai
            $totalNilai = collect($items)->sum(fn($i) =>
                $i['jumlah_terima'] * $i['harga_satuan'] * (1 - ($i['diskon_persen'] ?? 0) / 100)
            );

            $gr = GoodsReceipt::create(array_merge($data, [
                'nomor_gr'    => $nomorGr,
                'total_nilai' => $totalNilai,
                'status'      => 'draft',
            ]));

            foreach ($items as $item) {
                $subtotal = $item['jumlah_terima']
                          * $item['harga_satuan']
                          * (1 - ($item['diskon_persen'] ?? 0) / 100);

                $gr->items()->create(array_merge($item, [
                    'subtotal' => $subtotal,
                ]));
            }

            return $gr->load('items.barang');
        });
    }

    /**
     * Verifikasi GR — trigger update stok & HPR
     */
    public function verifikasiGr(GoodsReceipt $gr, int $userId): GoodsReceipt
    {
        if ($gr->status !== 'draft') {
            throw new \RuntimeException('GR sudah diverifikasi atau dibatalkan.');
        }

        return DB::transaction(function () use ($gr, $userId) {
            foreach ($gr->items as $grItem) {
                $this->prosesItemGr($grItem, $userId);
            }

            // Update status PO jika GR ini linked ke PO
            if ($gr->purchase_order_id) {
                $this->updateStatusPo($gr->purchase_order_id);
            }

            $gr->update([
                'status'       => 'diverifikasi',
                'diterima_oleh'=> $userId,
            ]);

            return $gr->fresh(['items.barang']);
        });
    }

    private function prosesItemGr(GrItem $grItem, int $userId): void
    {
        $barang = Barang::lockForUpdate()->findOrFail($grItem->barang_id);

        // ── Hitung HPR Baru (Moving Average) ──────────────────
        $stokLama   = $barang->stok;
        $hprLama    = $barang->harga_pokok;
        $jumlahMasuk= $grItem->jumlah_terima;

        // Harga beli efektif setelah diskon
        $hargaBeli  = $grItem->harga_satuan * (1 - $grItem->diskon_persen / 100);

        if ($stokLama + $jumlahMasuk === 0) {
            $hprBaru = $hargaBeli;
        } elseif ($stokLama === 0) {
            // Stok kosong → HPR = harga beli saja
            $hprBaru = $hargaBeli;
        } else {
            $hprBaru = (($stokLama * $hprLama) + ($jumlahMasuk * $hargaBeli))
                     / ($stokLama + $jumlahMasuk);
        }

        // ── Simpan snapshot HPR ke gr_item ────────────────────
        $grItem->update([
            'hpr_sebelum' => $hprLama,
            'hpr_sesudah' => round($hprBaru, 2),
        ]);

        // ── Update stok & HPR di tabel barang ─────────────────
        $stokBaru = $stokLama + $jumlahMasuk;
        $barang->update([
            'stok'         => $stokBaru,
            'harga_pokok'  => round($hprBaru, 2),
        ]);

        // ── Catat mutasi stok ──────────────────────────────────
        MutasiStok::create([
            'barang_id'      => $barang->id,
            'user_id'        => $userId,
            'tipe'           => 'masuk_pembelian',
            'jumlah'         => $jumlahMasuk,
            'stok_sebelum'   => $stokLama,
            'stok_sesudah'   => $stokBaru,
            'hpr_sebelum'    => $hprLama,
            'hpr_sesudah'    => round($hprBaru, 2),
            'referensi_tipe' => 'goods_receipt',
            'referensi_id'   => $grItem->goods_receipt_id,
            'keterangan'     => "GR: {$grItem->goodsReceipt->nomor_gr} | Batch: {$grItem->nomor_batch}",
        ]);

        // ── Update jumlah_diterima di po_item ─────────────────
        if ($grItem->po_item_id) {
            $poItem = PoItem::find($grItem->po_item_id);
            $poItem?->increment('jumlah_diterima', $jumlahMasuk);
        }

        // ── Update harga_terakhir di supplier_barang ──────────
        SupplierBarang::where('barang_id', $barang->id)
            ->where('supplier_id', $grItem->goodsReceipt->supplier_id)
            ->update(['harga_terakhir' => $grItem->harga_satuan]);
    }

    /**
     * Rollback — batalkan GR (hanya status draft)
     */
    public function batalkanGr(GoodsReceipt $gr): GoodsReceipt
    {
        if ($gr->status !== 'draft') {
            throw new \RuntimeException('Hanya GR berstatus draft yang bisa dibatalkan.');
        }

        $gr->update(['status' => 'dibatalkan']);
        return $gr;
    }

    private function updateStatusPo(int $poId): void
    {
        $po    = \App\Models\PurchaseOrder::with('items')->find($poId);
        if (!$po) return;

        $totalPesan   = $po->items->sum('jumlah_pesan');
        $totalDiterima= $po->items->sum('jumlah_diterima');

        if ($totalDiterima >= $totalPesan) {
            $po->update(['status' => 'selesai']);
        } elseif ($totalDiterima > 0) {
            $po->update(['status' => 'sebagian']);
        }
    }

    private function generateNomorGr(): string
    {
        $prefix  = 'GR-' . now()->format('Y-m-');
        $last    = GoodsReceipt::where('nomor_gr', 'like', $prefix . '%')
                    ->orderByDesc('nomor_gr')
                    ->value('nomor_gr');

        $seq     = $last ? (int) substr($last, -4) + 1 : 1;
        return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }
}
```

### 11.3 Livewire — Form GR

```php
// app/Livewire/Inventory/GoodsReceipt/GrForm.php

namespace App\Livewire\Inventory\GoodsReceipt;

use Livewire\Component;
use App\Models\{PurchaseOrder, GoodsReceipt, Supplier};
use App\Services\Inventory\PenerimaanService;

class GrForm extends Component
{
    public int    $supplierId         = 0;
    public int    $poId               = 0;
    public string $tanggalTerima      = '';
    public string $nomorFakturSupplier= '';
    public string $tanggalFaktur      = '';
    public string $nomorSuratJalan    = '';
    public string $catatan            = '';
    public array  $items              = [];

    public array  $poTersedia         = [];

    protected function rules(): array
    {
        return [
            'supplierId'               => ['required', 'exists:supplier,id'],
            'tanggalTerima'            => ['required', 'date', 'before_or_equal:today'],
            'nomorFakturSupplier'      => ['nullable', 'string', 'max:50'],
            'items'                    => ['required', 'array', 'min:1'],
            'items.*.barang_id'        => ['required', 'exists:barang,id'],
            'items.*.jumlah_terima'    => ['required', 'integer', 'min:1'],
            'items.*.harga_satuan'     => ['required', 'numeric', 'min:0'],
            'items.*.diskon_persen'    => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.nomor_batch'      => ['nullable', 'string', 'max:50'],
            'items.*.expired_date'     => ['nullable', 'date', 'after:today'],
        ];
    }

    public function mount(): void
    {
        $this->tanggalTerima = now()->format('Y-m-d');
    }

    public function updatedSupplierId(): void
    {
        // Load PO yang belum selesai dari supplier ini
        $this->poTersedia = PurchaseOrder::where('supplier_id', $this->supplierId)
            ->whereIn('status', ['dikirim', 'sebagian'])
            ->with('items.barang')
            ->get()
            ->toArray();
        $this->items = [];
        $this->poId  = 0;
    }

    public function loadDariPo(int $poId): void
    {
        $po = PurchaseOrder::with('items.barang')->findOrFail($poId);
        $this->poId = $poId;

        // Hitung sisa yang belum diterima
        $this->items = $po->items
            ->filter(fn($i) => $i->jumlah_pesan > $i->jumlah_diterima)
            ->map(fn($i) => [
                'barang_id'     => $i->barang_id,
                'po_item_id'    => $i->id,
                'nama_barang'   => $i->barang->nama,
                'satuan'        => $i->barang->satuan,
                'jumlah_pesan'  => $i->jumlah_pesan,
                'sisa_pesan'    => $i->jumlah_pesan - $i->jumlah_diterima,
                'jumlah_terima' => $i->jumlah_pesan - $i->jumlah_diterima, // default = sisa
                'harga_satuan'  => $i->harga_satuan,
                'diskon_persen' => $i->diskon_persen,
                'subtotal'      => $i->subtotal,
                'nomor_batch'   => '',
                'expired_date'  => '',
            ])
            ->values()
            ->toArray();

        // Auto-fill tanggal jatuh tempo berdasarkan TOP supplier
        $supplier = Supplier::find($this->supplierId);
        if ($supplier && $this->tanggalFaktur) {
            $jatuhTempo = \Carbon\Carbon::parse($this->tanggalFaktur)
                ->addDays($supplier->top_hari)
                ->format('Y-m-d');
        }
    }

    public function getTotalNilaiProperty(): float
    {
        return collect($this->items)->sum(fn($i) =>
            ($i['jumlah_terima'] ?? 0)
            * ($i['harga_satuan'] ?? 0)
            * (1 - ($i['diskon_persen'] ?? 0) / 100)
        );
    }

    public function simpanDraft(PenerimaanService $service): void
    {
        $this->validate();
        $this->prosesSimapn($service, 'draft');
    }

    public function verifikasi(PenerimaanService $service): void
    {
        $this->validate();
        $gr = $this->prosesSimapn($service, 'draft');
        $service->verifikasiGr($gr, auth()->id());
        session()->flash('success', "GR {$gr->nomor_gr} diverifikasi. Stok & HPR telah diperbarui.");
        $this->redirectRoute('inventory.gr.show', $gr);
    }

    private function prosesSimapn(PenerimaanService $service, string $status): GoodsReceipt
    {
        $gr = $service->buatGr([
            'supplier_id'           => $this->supplierId,
            'purchase_order_id'     => $this->poId ?: null,
            'tanggal_terima'        => $this->tanggalTerima,
            'nomor_faktur_supplier' => $this->nomorFakturSupplier ?: null,
            'tanggal_faktur'        => $this->tanggalFaktur ?: null,
            'nomor_surat_jalan'     => $this->nomorSuratJalan ?: null,
            'catatan'               => $this->catatan ?: null,
            'diterima_oleh'         => auth()->id(),
            'items'                 => $this->items,
        ]);

        return $gr;
    }

    public function render()
    {
        $suppliers = Supplier::where('is_active', true)->orderBy('nama')->get();
        return view('livewire.inventory.goods-receipt.gr-form', [
            'suppliers'  => $suppliers,
            'totalNilai' => $this->totalNilai,
        ]);
    }
}
```

---

## 12. Modul Alert Stok Minimum & Prioritas Pembelian

### 12.1 Konsep

Modul ini memberikan **halaman khusus** yang menampilkan semua barang yang stoknya sudah mencapai atau di bawah `stok_minimum`, diurutkan berdasarkan tingkat kekritisan, dan memungkinkan pengguna membuat PO langsung dari halaman ini.

```
Tingkat Kekritisan:
  🔴 HABIS       : stok = 0
  🟠 KRITIS      : 0 < stok ≤ stok_minimum
  🟡 HAMPIR HABIS: stok_minimum < stok ≤ stok_minimum × 1.5
```

### 12.2 Livewire Component — Halaman Prioritas Pembelian

```php
// app/Livewire/Inventory/AlertStok/PrioritasPembelianTable.php

namespace App\Livewire\Inventory\AlertStok;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\{Barang, Supplier};
use App\Services\Inventory\AlertStokService;

class PrioritasPembelianTable extends Component
{
    use WithPagination;

    public string $search      = '';
    public string $filterJenis = '';
    public string $filterLevel = '';  // habis | kritis | hampir_habis
    public array  $selected    = [];  // barang_id yang dipilih untuk dibuat PO
    public int    $targetSupplierId = 0; // supplier untuk PO massal

    protected $queryString = ['filterJenis', 'filterLevel'];

    public function getBarangKritisProperty()
    {
        return Barang::query()
            ->where('is_active', true)
            ->where(function ($q) {
                $q->where('stok', '<=', \Illuminate\Support\Facades\DB::raw('stok_minimum * 1.5'))
                  ->orWhere('stok', 0);
            })
            ->when($this->search, fn($q) => $q
                ->where('nama', 'like', "%{$this->search}%")
                ->orWhere('kode', 'like', "%{$this->search}%")
            )
            ->when($this->filterJenis, fn($q) => $q->where('jenis', $this->filterJenis))
            ->when($this->filterLevel === 'habis',       fn($q) => $q->where('stok', 0))
            ->when($this->filterLevel === 'kritis',      fn($q) => $q
                ->where('stok', '>', 0)
                ->whereRaw('stok <= stok_minimum')
            )
            ->when($this->filterLevel === 'hampir_habis', fn($q) => $q
                ->whereRaw('stok > stok_minimum')
                ->whereRaw('stok <= stok_minimum * 1.5')
            )
            ->with(['supplierUtama', 'supplierBarang.supplier'])
            ->orderByRaw('CASE WHEN stok = 0 THEN 0 WHEN stok <= stok_minimum THEN 1 ELSE 2 END')
            ->orderByRaw('stok / NULLIF(stok_minimum, 0) ASC')
            ->paginate(20);
    }

    public function toggleSelect(int $barangId): void
    {
        if (in_array($barangId, $this->selected)) {
            $this->selected = array_filter($this->selected, fn($id) => $id !== $barangId);
        } else {
            $this->selected[] = $barangId;
        }
    }

    public function selectAll(): void
    {
        $this->selected = Barang::where('is_active', true)
            ->whereRaw('stok <= stok_minimum')
            ->pluck('id')
            ->toArray();
    }

    public function clearSelection(): void
    {
        $this->selected = [];
    }

    /**
     * Buat PO dari daftar barang yang dipilih
     * Otomatis dikelompokkan per supplier utama
     */
    public function buatPoMassal(): void
    {
        if (empty($this->selected)) {
            $this->addError('selected', 'Pilih minimal 1 barang.');
            return;
        }

        // Kelompokkan barang berdasarkan supplier utama
        $groups = Barang::whereIn('id', $this->selected)
            ->with(['supplierUtama', 'supplierBarang' => fn($q) =>
                $q->where('is_supplier_utama', true)])
            ->get()
            ->groupBy('supplier_utama_id');

        $poList = [];
        foreach ($groups as $supplierId => $barangs) {
            if (!$supplierId) continue; // skip jika tidak ada supplier utama

            $supplier = Supplier::find($supplierId);
            $items = $barangs->map(function ($b) {
                // Jumlah order = stok_maksimum - stok_saat_ini (atau 2× stok_minimum jika tidak ada maks)
                $jumlahOrder = $b->stok_maksimum
                    ? max(0, $b->stok_maksimum - $b->stok)
                    : ($b->stok_minimum * 2 - $b->stok);

                $hargaTerakhir = $b->supplierBarang->first()?->harga_terakhir ?? $b->harga_pokok;

                return [
                    'barang_id'     => $b->id,
                    'nama_barang'   => $b->nama,
                    'satuan'        => $b->satuan,
                    'jumlah_pesan'  => max(1, (int) $jumlahOrder),
                    'harga_satuan'  => $hargaTerakhir,
                    'diskon_persen' => 0,
                    'subtotal'      => max(1, (int) $jumlahOrder) * $hargaTerakhir,
                ];
            })->toArray();

            $poList[] = [
                'supplier_id'  => $supplierId,
                'nama_supplier'=> $supplier->nama,
                'jumlah_item'  => count($items),
            ];

            // Simpan ke session untuk konfirmasi sebelum submit
        }

        // Redirect ke halaman konfirmasi PO massal
        session(['po_massal_draft' => [
            'selected_barang' => $this->selected,
            'groups'          => $poList,
        ]]);

        $this->redirectRoute('inventory.po.massal-confirm');
    }

    public function render()
    {
        return view('livewire.inventory.alert-stok.prioritas-pembelian-table', [
            'barangKritis' => $this->barangKritis,
            'summary'      => [
                'total_habis'       => Barang::where('is_active', true)->where('stok', 0)->count(),
                'total_kritis'      => Barang::where('is_active', true)
                                        ->where('stok', '>', 0)
                                        ->whereRaw('stok <= stok_minimum')->count(),
                'total_hampir_habis'=> Barang::where('is_active', true)
                                        ->whereRaw('stok > stok_minimum')
                                        ->whereRaw('stok <= stok_minimum * 1.5')->count(),
            ],
        ]);
    }
}
```

### 12.3 Blade View — Prioritas Pembelian

```blade
{{-- resources/views/livewire/inventory/alert-stok/prioritas-pembelian-table.blade.php --}}
<div class="space-y-5">

    {{-- Summary Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="stat-card border-l-4 border-red-500">
            <div class="stat-icon bg-red-50">
                <svg class="w-6 h-6 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
            </div>
            <div>
                <p class="stat-value text-red-600">{{ $summary['total_habis'] }}</p>
                <p class="stat-label">Stok Habis</p>
            </div>
        </div>
        <div class="stat-card border-l-4 border-orange-500">
            <div class="stat-icon bg-orange-50">
                <svg class="w-6 h-6 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <div>
                <p class="stat-value text-orange-600">{{ $summary['total_kritis'] }}</p>
                <p class="stat-label">Stok Kritis (≤ Min)</p>
            </div>
        </div>
        <div class="stat-card border-l-4 border-amber-400">
            <div class="stat-icon bg-amber-50">
                <svg class="w-6 h-6 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <div>
                <p class="stat-value text-amber-600">{{ $summary['total_hampir_habis'] }}</p>
                <p class="stat-label">Hampir Habis (≤ 1.5× Min)</p>
            </div>
        </div>
    </div>

    {{-- Toolbar --}}
    <div class="card">
        <div class="card-body">
            <div class="flex flex-col md:flex-row gap-3 justify-between">
                {{-- Search & Filter --}}
                <div class="flex flex-wrap gap-2">
                    <div class="relative">
                        <input type="text" wire:model.live.debounce.300ms="search"
                            class="form-input pl-9 w-64"
                            placeholder="Cari nama / kode barang..." />
                        <span class="absolute inset-y-0 left-3 flex items-center text-gray-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z"/>
                            </svg>
                        </span>
                    </div>

                    <select wire:model.live="filterJenis" class="form-select w-40">
                        <option value="">Semua Jenis</option>
                        <option value="obat">Obat</option>
                        <option value="alkes">Alkes</option>
                        <option value="bahan_habis_pakai">Bahan Habis Pakai</option>
                    </select>

                    <select wire:model.live="filterLevel" class="form-select w-44">
                        <option value="">Semua Level</option>
                        <option value="habis">🔴 Habis</option>
                        <option value="kritis">🟠 Kritis</option>
                        <option value="hampir_habis">🟡 Hampir Habis</option>
                    </select>
                </div>

                {{-- Aksi Massal --}}
                <div class="flex gap-2">
                    @if(!empty($selected))
                    <span class="badge badge-primary self-center">{{ count($selected) }} dipilih</span>
                    <button type="button" wire:click="clearSelection" class="btn-secondary btn-sm">
                        Batal Pilih
                    </button>
                    <button type="button" wire:click="buatPoMassal" class="btn-primary btn-sm">
                        Buat PO ({{ count($selected) }} item)
                    </button>
                    @else
                    <button type="button" wire:click="selectAll" class="btn-secondary btn-sm">
                        Pilih Semua Kritis
                    </button>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Tabel --}}
    <div class="table-wrapper">
        <table class="table">
            <thead>
                <tr>
                    <th class="w-8">
                        <input type="checkbox" class="form-checkbox"
                            wire:click="selectAll" />
                    </th>
                    <th>Kode</th>
                    <th>Nama Barang</th>
                    <th>Jenis</th>
                    <th class="text-center">Stok</th>
                    <th class="text-center">Min</th>
                    <th class="text-center">Level</th>
                    <th>Supplier Utama</th>
                    <th class="text-right">HPR (Rp)</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($barangKritis as $b)
                @php
                    $ratio = $b->stok_minimum > 0 ? $b->stok / $b->stok_minimum : 1;
                    if ($b->stok === 0) {
                        $levelClass = 'badge-danger';
                        $levelLabel = '🔴 Habis';
                        $rowClass   = 'bg-red-50/40';
                    } elseif ($b->stok <= $b->stok_minimum) {
                        $levelClass = 'badge-warning';
                        $levelLabel = '🟠 Kritis';
                        $rowClass   = 'bg-orange-50/40';
                    } else {
                        $levelClass = 'bg-amber-100 text-amber-700';
                        $levelLabel = '🟡 Hampir Habis';
                        $rowClass   = '';
                    }
                @endphp
                <tr class="{{ $rowClass }}">
                    <td>
                        <input type="checkbox" class="form-checkbox"
                            wire:click="toggleSelect({{ $b->id }})"
                            @checked(in_array($b->id, $selected)) />
                    </td>
                    <td class="font-mono text-xs text-gray-500">{{ $b->kode }}</td>
                    <td class="font-medium text-gray-900">{{ $b->nama }}</td>
                    <td>
                        <span class="badge badge-gray">{{ ucfirst($b->jenis) }}</span>
                    </td>
                    <td class="text-center font-bold {{ $b->stok === 0 ? 'text-red-600' : ($b->stok <= $b->stok_minimum ? 'text-orange-600' : 'text-amber-600') }}">
                        {{ $b->stok }}
                    </td>
                    <td class="text-center text-gray-500">{{ $b->stok_minimum }}</td>
                    <td class="text-center">
                        <span class="badge {{ $levelClass }}">{{ $levelLabel }}</span>
                    </td>
                    <td class="text-sm text-gray-600">
                        {{ $b->supplierUtama?->nama ?? '—' }}
                    </td>
                    <td class="text-right text-sm">
                        {{ number_format($b->harga_pokok, 0, ',', '.') }}
                    </td>
                    <td>
                        <div class="flex gap-1">
                            <a href="{{ route('inventory.po.create', ['barang_id' => $b->id]) }}"
                               class="btn-primary btn-sm">Beli</a>
                            <a href="{{ route('inventory.barang.show', $b) }}"
                               class="btn-secondary btn-sm">Detail</a>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="10">
                        <div class="empty-state">
                            <svg class="empty-state-icon text-emerald-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                      d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <p class="empty-state-text text-emerald-600 font-medium">Semua stok aman!</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $barangKritis->links() }}</div>
</div>
```

---

## 13. Model Eloquent

```php
// app/Models/Supplier.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{HasMany, BelongsToMany};

class Supplier extends Model
{
    protected $table = 'supplier';
    protected $fillable = [
        'kode','nama','tipe','pic','telepon','email',
        'alamat','npwp','lead_time_hari','top_hari','is_active',
    ];
    protected $casts = ['is_active' => 'boolean'];

    public function barang(): BelongsToMany
    {
        return $this->belongsToMany(Barang::class, 'supplier_barang')
                    ->withPivot(['kode_barang_supplier','harga_terakhir','is_supplier_utama'])
                    ->withTimestamps();
    }

    public function supplierBarang(): HasMany
    {
        return $this->hasMany(SupplierBarang::class);
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function goodsReceipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class);
    }

    public function scopeActive($q) { return $q->where('is_active', true); }
}
```

```php
// app/Models/Barang.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, BelongsToMany};
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Barang extends Model
{
    protected $table = 'barang';
    protected $fillable = [
        'kode','nama','nama_generik','jenis','kategori',
        'satuan','satuan_besar','isi_satuan_besar','kemasan',
        'stok','stok_minimum','stok_maksimum',
        'harga_pokok','harga_jual',
        'golongan','butuh_resep','is_active','supplier_utama_id',
    ];
    protected $casts = [
        'butuh_resep' => 'boolean',
        'is_active'   => 'boolean',
    ];

    use LogsActivity;
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['stok','harga_pokok'])->logOnlyDirty();
    }

    public function supplierUtama(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_utama_id');
    }

    public function suppliers(): BelongsToMany
    {
        return $this->belongsToMany(Supplier::class, 'supplier_barang')
                    ->withPivot(['kode_barang_supplier','harga_terakhir','is_supplier_utama'])
                    ->withTimestamps();
    }

    public function supplierBarang(): HasMany
    {
        return $this->hasMany(SupplierBarang::class);
    }

    public function mutasiStok(): HasMany
    {
        return $this->hasMany(MutasiStok::class)->latest();
    }

    public function scopeActive($q) { return $q->where('is_active', true); }

    public function scopeStokKritis($q)
    {
        return $q->whereRaw('stok <= stok_minimum');
    }

    public function scopeStokHabis($q)
    {
        return $q->where('stok', 0);
    }

    public function getIsStokKritisAttribute(): bool
    {
        return $this->stok <= $this->stok_minimum;
    }

    public function getLevelStokAttribute(): string
    {
        if ($this->stok === 0)                               return 'habis';
        if ($this->stok <= $this->stok_minimum)              return 'kritis';
        if ($this->stok <= $this->stok_minimum * 1.5)       return 'hampir_habis';
        return 'aman';
    }
}
```

```php
// app/Models/SupplierBarang.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo};

class SupplierBarang extends Model
{
    protected $table    = 'supplier_barang';
    protected $fillable = [
        'barang_id','supplier_id','kode_barang_supplier',
        'nama_barang_supplier','harga_terakhir','is_supplier_utama',
    ];
    protected $casts = ['is_supplier_utama' => 'boolean'];

    public function barang():   BelongsTo { return $this->belongsTo(Barang::class); }
    public function supplier(): BelongsTo { return $this->belongsTo(Supplier::class); }
}
```

```php
// app/Models/PurchaseOrder.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class PurchaseOrder extends Model
{
    protected $table    = 'purchase_order';
    protected $fillable = [
        'nomor_po','supplier_id','dibuat_oleh','disetujui_oleh',
        'tanggal_po','tanggal_kirim_estimasi','tanggal_disetujui',
        'status','total_nilai','catatan',
    ];
    protected $casts = [
        'tanggal_po'             => 'date',
        'tanggal_kirim_estimasi' => 'date',
        'tanggal_disetujui'      => 'date',
    ];

    public function supplier():    BelongsTo { return $this->belongsTo(Supplier::class); }
    public function dibuatOleh():  BelongsTo { return $this->belongsTo(User::class, 'dibuat_oleh'); }
    public function items():       HasMany   { return $this->hasMany(PoItem::class); }
    public function goodsReceipts():HasMany  { return $this->hasMany(GoodsReceipt::class); }

    public function scopeDraft($q)    { return $q->where('status', 'draft'); }
    public function scopeDikirim($q)  { return $q->where('status', 'dikirim'); }
}
```

```php
// app/Models/GoodsReceipt.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class GoodsReceipt extends Model
{
    protected $table    = 'goods_receipt';
    protected $fillable = [
        'nomor_gr','purchase_order_id','supplier_id','diterima_oleh',
        'tanggal_terima','nomor_faktur_supplier','tanggal_faktur',
        'tanggal_jatuh_tempo','nomor_surat_jalan','total_nilai','status','catatan',
    ];
    protected $casts = [
        'tanggal_terima'      => 'date',
        'tanggal_faktur'      => 'date',
        'tanggal_jatuh_tempo' => 'date',
    ];

    public function supplier():       BelongsTo { return $this->belongsTo(Supplier::class); }
    public function purchaseOrder():  BelongsTo { return $this->belongsTo(PurchaseOrder::class); }
    public function diterimaOleh():   BelongsTo { return $this->belongsTo(User::class, 'diterima_oleh'); }
    public function items():          HasMany   { return $this->hasMany(GrItem::class); }
}
```

---

## 14. Repository Layer

```php
// app/Repositories/Inventory/BarangRepository.php

namespace App\Repositories\Inventory;

use App\Models\Barang;
use Illuminate\Pagination\LengthAwarePaginator;

class BarangRepository
{
    public function findAll(array $params = []): LengthAwarePaginator
    {
        return Barang::query()
            ->when($params['search'] ?? null, fn($q, $s) => $q
                ->where('nama', 'like', "%$s%")
                ->orWhere('kode', 'like', "%$s%")
            )
            ->when($params['jenis']     ?? null, fn($q, $v) => $q->where('jenis', $v))
            ->when($params['is_active'] ?? true,  fn($q, $v) => $q->where('is_active', $v))
            ->with('supplierUtama')
            ->orderBy($params['sort_by'] ?? 'nama', $params['sort_dir'] ?? 'asc')
            ->paginate($params['per_page'] ?? 15);
    }

    public function findStokKritis(): LengthAwarePaginator
    {
        return Barang::active()
            ->stokKritis()
            ->with(['supplierUtama', 'supplierBarang.supplier'])
            ->orderByRaw('stok / NULLIF(stok_minimum, 0) ASC')
            ->paginate(20);
    }

    public function updateStokDanHpr(int $id, int $stokBaru, float $hprBaru): void
    {
        Barang::where('id', $id)->update([
            'stok'        => $stokBaru,
            'harga_pokok' => round($hprBaru, 2),
        ]);
    }
}
```

---

## 15. Service Layer

```php
// app/Services/Inventory/SupplierService.php

namespace App\Services\Inventory;

use App\Models\Supplier;

class SupplierService
{
    public function generateKode(): string
    {
        $last = Supplier::orderByDesc('kode')->value('kode');
        $seq  = $last ? (int) ltrim(str_replace('SUP-', '', $last), '0') + 1 : 1;
        return 'SUP-' . str_pad($seq, 3, '0', STR_PAD_LEFT);
    }

    public function create(array $data): Supplier
    {
        return Supplier::create($data);
    }

    public function update(Supplier $supplier, array $data): Supplier
    {
        $supplier->update($data);
        return $supplier->fresh();
    }
}
```

```php
// app/Services/Inventory/PembelianService.php

namespace App\Services\Inventory;

use App\Models\{PurchaseOrder, PoItem};
use Illuminate\Support\Facades\DB;

class PembelianService
{
    public function buatPo(array $data): PurchaseOrder
    {
        return DB::transaction(function () use ($data) {
            $items = $data['items'] ?? [];
            unset($data['items']);

            $nomorPo = $this->generateNomorPo();

            $po = PurchaseOrder::create(array_merge($data, [
                'nomor_po' => $nomorPo,
                'status'   => 'draft',
            ]));

            foreach ($items as $item) {
                $subtotal = $item['jumlah_pesan'] * $item['harga_satuan']
                          * (1 - ($item['diskon_persen'] ?? 0) / 100);
                $po->items()->create(array_merge($item, ['subtotal' => $subtotal]));
            }

            return $po->load('items.barang');
        });
    }

    public function approvePo(PurchaseOrder $po, int $userId): PurchaseOrder
    {
        if ($po->status !== 'draft') {
            throw new \RuntimeException('Hanya PO berstatus draft yang bisa di-approve.');
        }

        $po->update([
            'status'           => 'dikirim',
            'disetujui_oleh'   => $userId,
            'tanggal_disetujui'=> now(),
        ]);

        return $po;
    }

    public function batalkanPo(PurchaseOrder $po): PurchaseOrder
    {
        if (!in_array($po->status, ['draft', 'dikirim'])) {
            throw new \RuntimeException('PO yang sudah ada penerimaannya tidak bisa dibatalkan.');
        }

        $grExists = $po->goodsReceipts()
            ->where('status', 'diverifikasi')
            ->exists();

        if ($grExists) {
            throw new \RuntimeException('PO sudah ada penerimaan terverifikasi, tidak bisa dibatalkan.');
        }

        $po->update(['status' => 'dibatalkan']);
        return $po;
    }

    private function generateNomorPo(): string
    {
        $prefix = 'PO-' . now()->format('Y-m-');
        $last   = PurchaseOrder::where('nomor_po', 'like', $prefix . '%')
                    ->orderByDesc('nomor_po')
                    ->value('nomor_po');
        $seq    = $last ? (int) substr($last, -4) + 1 : 1;
        return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }

    public function updatePo(PurchaseOrder $po, array $data): PurchaseOrder
    {
        if ($po->status !== 'draft') {
            throw new \RuntimeException('Hanya PO draft yang bisa diedit.');
        }

        return DB::transaction(function () use ($po, $data) {
            $items = $data['items'] ?? [];
            unset($data['items']);

            $po->update($data);
            $po->items()->delete();

            foreach ($items as $item) {
                $subtotal = $item['jumlah_pesan'] * $item['harga_satuan']
                          * (1 - ($item['diskon_persen'] ?? 0) / 100);
                $po->items()->create(array_merge($item, ['subtotal' => $subtotal]));
            }

            return $po->fresh('items');
        });
    }
}
```

```php
// app/Services/Inventory/AlertStokService.php

namespace App\Services\Inventory;

use App\Models\Barang;
use Illuminate\Support\Facades\Notification;
use App\Notifications\Inventory\StokKritisNotification;

class AlertStokService
{
    public function cekDanKirimAlert(int $barangId): void
    {
        $barang = Barang::find($barangId);
        if (!$barang) return;

        if ($barang->stok <= $barang->stok_minimum) {
            // Kirim notifikasi ke role apoteker & admin via Laravel Notifications
            $users = \App\Models\User::role(['apoteker', 'admin', 'super_admin'])->get();
            Notification::send($users, new StokKritisNotification($barang));
        }
    }
}
```

---

## 16. Form Request Validation

```php
// app/Http/Requests/Inventory/StorePurchaseOrderRequest.php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('pembelian.create');
    }

    public function rules(): array
    {
        return [
            'supplier_id'               => ['required', 'exists:supplier,id'],
            'tanggal_po'                => ['required', 'date'],
            'tanggal_kirim_estimasi'    => ['nullable', 'date', 'after_or_equal:tanggal_po'],
            'catatan'                   => ['nullable', 'string'],
            'items'                     => ['required', 'array', 'min:1'],
            'items.*.barang_id'         => ['required', 'exists:barang,id'],
            'items.*.jumlah_pesan'      => ['required', 'integer', 'min:1'],
            'items.*.harga_satuan'      => ['required', 'numeric', 'min:0'],
            'items.*.diskon_persen'     => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            // Cek duplikat barang dalam 1 PO
            $barangIds = collect($this->items ?? [])->pluck('barang_id');
            if ($barangIds->count() !== $barangIds->unique()->count()) {
                $v->errors()->add('items', 'Terdapat barang duplikat dalam daftar item PO.');
            }
        });
    }
}
```

```php
// app/Http/Requests/Inventory/StoreGoodsReceiptRequest.php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class StoreGoodsReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('penerimaan.create');
    }

    public function rules(): array
    {
        return [
            'supplier_id'              => ['required', 'exists:supplier,id'],
            'purchase_order_id'        => ['nullable', 'exists:purchase_order,id'],
            'tanggal_terima'           => ['required', 'date', 'before_or_equal:today'],
            'nomor_faktur_supplier'    => ['nullable', 'string', 'max:50'],
            'tanggal_faktur'           => ['nullable', 'date'],
            'nomor_surat_jalan'        => ['nullable', 'string', 'max:50'],
            'items'                    => ['required', 'array', 'min:1'],
            'items.*.barang_id'        => ['required', 'exists:barang,id'],
            'items.*.jumlah_terima'    => ['required', 'integer', 'min:1'],
            'items.*.harga_satuan'     => ['required', 'numeric', 'min:0'],
            'items.*.diskon_persen'    => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.nomor_batch'      => ['nullable', 'string', 'max:50'],
            'items.*.expired_date'     => ['nullable', 'date', 'after:today'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.*.expired_date.after' => 'Tanggal expired harus setelah hari ini.',
            'items.*.jumlah_terima.min'  => 'Jumlah diterima minimal 1.',
        ];
    }
}
```

---

## 17. Livewire Components — Daftar Lengkap

```
app/Livewire/Inventory/
├── Supplier/
│   ├── SupplierTable.php      # Tabel + search + filter tipe
│   └── SupplierForm.php       # Create / edit supplier
│
├── Barang/
│   ├── BarangTable.php        # Tabel + filter jenis + badge stok
│   ├── BarangForm.php         # Create / edit barang
│   ├── SupplierMappingModal.php  # Kelola relasi barang ↔ supplier
│   └── MutasiStokHistory.php  # Riwayat mutasi stok per barang
│
├── PurchaseOrder/
│   ├── PoTable.php            # Daftar PO + filter status
│   ├── PoForm.php             # Buat / edit PO (search barang dari supplier)
│   ├── PoDetail.php           # Detail PO + progress penerimaan
│   └── PoMassalConfirm.php    # Konfirmasi PO massal dari alert stok
│
├── GoodsReceipt/
│   ├── GrTable.php            # Daftar GR
│   ├── GrForm.php             # Buat GR dari PO / tanpa PO
│   └── GrDetail.php           # Detail GR + snapshot HPR
│
└── AlertStok/
    └── PrioritasPembelianTable.php  # Halaman khusus stok kritis
```

---

## 18. Route & Controller

```php
// routes/web.php — tambahkan group inventory

Route::middleware(['auth'])->prefix('inventory')->name('inventory.')->group(function () {

    // Supplier
    Route::middleware('permission:supplier.view')->group(function () {
        Route::get('/supplier',             [SupplierController::class, 'index'])->name('supplier.index');
        Route::get('/supplier/create',      [SupplierController::class, 'create'])->name('supplier.create')
             ->middleware('permission:supplier.create');
        Route::get('/supplier/{supplier}',  [SupplierController::class, 'show'])->name('supplier.show');
        Route::get('/supplier/{supplier}/edit', [SupplierController::class, 'edit'])->name('supplier.edit')
             ->middleware('permission:supplier.edit');
    });

    // Master Barang
    Route::middleware('permission:barang.view')->group(function () {
        Route::get('/barang',              [BarangController::class, 'index'])->name('barang.index');
        Route::get('/barang/create',       [BarangController::class, 'create'])->name('barang.create')
             ->middleware('permission:barang.create');
        Route::get('/barang/{barang}',     [BarangController::class, 'show'])->name('barang.show');
        Route::get('/barang/{barang}/edit',[BarangController::class, 'edit'])->name('barang.edit')
             ->middleware('permission:barang.edit');
    });

    // Purchase Order
    Route::middleware('permission:pembelian.view')->group(function () {
        Route::get('/po',                   [PoController::class, 'index'])->name('po.index');
        Route::get('/po/create',            [PoController::class, 'create'])->name('po.create')
             ->middleware('permission:pembelian.create');
        Route::get('/po/massal-confirm',    [PoController::class, 'massalConfirm'])->name('po.massal-confirm');
        Route::get('/po/{po}',              [PoController::class, 'show'])->name('po.show');
        Route::get('/po/{po}/edit',         [PoController::class, 'edit'])->name('po.edit')
             ->middleware('permission:pembelian.edit');
        Route::patch('/po/{po}/approve',    [PoController::class, 'approve'])->name('po.approve')
             ->middleware('permission:pembelian.approve');
        Route::patch('/po/{po}/cancel',     [PoController::class, 'cancel'])->name('po.cancel');
    });

    // Goods Receipt
    Route::middleware('permission:penerimaan.view')->group(function () {
        Route::get('/gr',                   [GrController::class, 'index'])->name('gr.index');
        Route::get('/gr/create',            [GrController::class, 'create'])->name('gr.create')
             ->middleware('permission:penerimaan.create');
        Route::get('/gr/{gr}',              [GrController::class, 'show'])->name('gr.show');
        Route::patch('/gr/{gr}/verifikasi', [GrController::class, 'verifikasi'])->name('gr.verifikasi')
             ->middleware('permission:penerimaan.verifikasi');
        Route::patch('/gr/{gr}/batalkan',   [GrController::class, 'batalkan'])->name('gr.batalkan');
    });

    // Alert Stok Minimum / Prioritas Pembelian
    Route::get('/alert-stok', [AlertStokController::class, 'index'])
         ->name('alert-stok.index')
         ->middleware('permission:barang.view');
});
```

---

## 19. Struktur Folder

```
app/
├── Http/
│   ├── Controllers/
│   │   └── Inventory/
│   │       ├── SupplierController.php
│   │       ├── BarangController.php
│   │       ├── PoController.php
│   │       ├── GrController.php
│   │       └── AlertStokController.php
│   │
│   └── Requests/
│       └── Inventory/
│           ├── StoreSupplierRequest.php
│           ├── StoreBarangRequest.php
│           ├── StorePurchaseOrderRequest.php
│           └── StoreGoodsReceiptRequest.php
│
├── Livewire/
│   └── Inventory/          # (lihat Section 17)
│
├── Models/
│   ├── Supplier.php
│   ├── Barang.php
│   ├── SupplierBarang.php  # Pivot model
│   ├── PurchaseOrder.php
│   ├── PoItem.php
│   ├── GoodsReceipt.php
│   ├── GrItem.php
│   └── MutasiStok.php
│
├── Repositories/
│   └── Inventory/
│       ├── SupplierRepository.php
│       ├── BarangRepository.php
│       ├── PurchaseOrderRepository.php
│       └── GoodsReceiptRepository.php
│
├── Services/
│   └── Inventory/
│       ├── SupplierService.php
│       ├── BarangService.php
│       ├── PembelianService.php
│       ├── PenerimaanService.php    # Core: verifikasiGr + update HPR
│       └── AlertStokService.php
│
└── Notifications/
    └── Inventory/
        └── StokKritisNotification.php

database/
├── migrations/
│   ├── 2026_01_01_000100_create_supplier_table.php
│   ├── 2026_01_01_000101_create_barang_table.php
│   ├── 2026_01_01_000102_create_supplier_barang_table.php
│   ├── 2026_01_01_000103_create_purchase_order_table.php
│   ├── 2026_01_01_000104_create_po_item_table.php
│   ├── 2026_01_01_000105_create_goods_receipt_table.php
│   ├── 2026_01_01_000106_create_gr_item_table.php
│   └── 2026_01_01_000107_create_mutasi_stok_table.php
│
└── seeders/
    ├── SupplierSeeder.php
    └── BarangSeeder.php

resources/views/
├── inventory/
│   ├── supplier/
│   │   ├── index.blade.php
│   │   ├── show.blade.php
│   │   └── form.blade.php
│   ├── barang/
│   │   ├── index.blade.php
│   │   └── show.blade.php
│   ├── purchase-order/
│   │   ├── index.blade.php
│   │   ├── show.blade.php
│   │   └── form.blade.php
│   ├── goods-receipt/
│   │   ├── index.blade.php
│   │   └── show.blade.php
│   └── alert-stok/
│       └── index.blade.php
│
├── livewire/
│   └── inventory/     # (Livewire blade views sesuai struktur Livewire)
│
└── components/
    └── inventory/
        └── stok-badge.blade.php
```

---

## 20. User Stories & Business Rules

| ID | Persona | Skenario | Expected Behavior |
|----|---------|----------|-------------------|
| US01 | Apoteker | Buka halaman Alert Stok | Lihat 3 kategori: Habis / Kritis / Hampir Habis, sorted by kritikalitas |
| US02 | Apoteker | Klik "Buat PO" dari 1 barang kritis | Redirect ke form PO dengan barang & supplier utama sudah terisi |
| US03 | Admin | Pilih 5 barang kritis → "Buat PO Massal" | Barang dikelompokkan per supplier → 2 PO draft terbuat |
| US04 | Admin | Input NIK supplier duplikat NPWP | Validasi unique NPWP di Form Request |
| US05 | Apoteker | Buat PO ke Supplier A, tambah barang yang bukan dari Supplier A | Search hanya tampilkan barang dari Supplier A |
| US06 | Gudang | Buat GR dari PO → input jumlah diterima < PO | Status PO berubah ke "sebagian", sisa item masih bisa diterima |
| US07 | Gudang | Verifikasi GR | Stok +500, HPR dihitung ulang via moving average, mutasi stok tercatat |
| US08 | Admin | GR diverifikasi → cek HPR barang | `barang.harga_pokok` terupdate, snapshot `hpr_sebelum` & `hpr_sesudah` di `gr_item` tersimpan |
| US09 | Gudang | Batalkan GR sudah diverifikasi | Error: "GR sudah diverifikasi tidak bisa dibatalkan" |
| US10 | Gudang | Terima barang dengan stok = 0 | HPR Baru = harga beli (tidak ada pembagi nol) |
| US11 | Admin | Hapus supplier yang ada mapping barang | Relasi `supplier_barang` ikut cascade delete, PO existing tetap ada (restrict) |
| US12 | Apoteker | Set supplier utama barang | `is_supplier_utama` berubah, yang lama otomatis di-unset |
| US13 | Admin | Lihat riwayat HPR per barang | Tabel `mutasi_stok` menampilkan `hpr_sebelum` dan `hpr_sesudah` tiap transaksi |

---

## 21. Seeder Data Awal

```php
// database/seeders/SupplierSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Supplier;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        $suppliers = [
            [
                'kode' => 'SUP-001', 'nama' => 'PT Kimia Farma Trading & Distribution',
                'tipe' => 'distributor', 'pic' => 'Budi Setiawan',
                'telepon' => '02151234567', 'email' => 'order@kimiafarmadist.co.id',
                'alamat' => 'Jl. Veteran No. 9, Jakarta Pusat',
                'npwp' => '01.234.567.8-001.000',
                'lead_time_hari' => 2, 'top_hari' => 30,
            ],
            [
                'kode' => 'SUP-002', 'nama' => 'PT Enseval Putera Megatrading',
                'tipe' => 'distributor', 'pic' => 'Sari Dewi',
                'telepon' => '02198765432', 'email' => 'order@enseval.com',
                'alamat' => 'Jl. Pulo Gadung No. 6, Jakarta Timur',
                'npwp' => '02.345.678.9-001.000',
                'lead_time_hari' => 3, 'top_hari' => 45,
            ],
            [
                'kode' => 'SUP-003', 'nama' => 'PT Rajawali Nusindo',
                'tipe' => 'distributor', 'pic' => 'Wayan Dharma',
                'telepon' => '03612345678', 'email' => 'bali@rajawali.co.id',
                'alamat' => 'Jl. Sunset Road No. 100, Kuta, Bali',
                'lead_time_hari' => 1, 'top_hari' => 30,
            ],
        ];

        foreach ($suppliers as $s) {
            Supplier::updateOrCreate(['kode' => $s['kode']], $s);
            $this->command->info("✓ Supplier: {$s['nama']}");
        }
    }
}
```

```php
// database/seeders/BarangSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\{Barang, Supplier, SupplierBarang};

class BarangSeeder extends Seeder
{
    public function run(): void
    {
        $supplier1 = Supplier::where('kode', 'SUP-001')->first();
        $supplier2 = Supplier::where('kode', 'SUP-002')->first();
        $supplier3 = Supplier::where('kode', 'SUP-003')->first();

        $barangList = [
            [
                'kode' => 'OBT-000001', 'nama' => 'Paracetamol 500mg', 'jenis' => 'obat',
                'kategori' => 'analgesik', 'satuan' => 'tablet',
                'stok' => 500, 'stok_minimum' => 100, 'stok_maksimum' => 2000,
                'harga_pokok' => 850, 'harga_jual' => 1500,
                'golongan' => 'bebas', 'butuh_resep' => false,
                'supplier_utama_id' => $supplier1?->id,
                'suppliers' => [
                    ['supplier' => $supplier1, 'harga' => 820, 'utama' => true],
                    ['supplier' => $supplier2, 'harga' => 850, 'utama' => false],
                ],
            ],
            [
                'kode' => 'OBT-000002', 'nama' => 'Amoxicillin 500mg', 'jenis' => 'obat',
                'kategori' => 'antibiotik', 'satuan' => 'kapsul',
                'stok' => 8, 'stok_minimum' => 50, 'stok_maksimum' => 500, // ← KRITIS
                'harga_pokok' => 1200, 'harga_jual' => 3000,
                'golongan' => 'keras', 'butuh_resep' => true,
                'supplier_utama_id' => $supplier2?->id,
                'suppliers' => [
                    ['supplier' => $supplier2, 'harga' => 1200, 'utama' => true],
                ],
            ],
            [
                'kode' => 'ALK-000001', 'nama' => 'Spuit 3ml', 'jenis' => 'alkes',
                'kategori' => 'disposable', 'satuan' => 'pcs',
                'stok' => 0, 'stok_minimum' => 200, // ← HABIS
                'harga_pokok' => 2500, 'harga_jual' => 5000,
                'butuh_resep' => false,
                'supplier_utama_id' => $supplier3?->id,
                'suppliers' => [
                    ['supplier' => $supplier3, 'harga' => 2500, 'utama' => true],
                    ['supplier' => $supplier1, 'harga' => 2700, 'utama' => false],
                ],
            ],
            [
                'kode' => 'OBT-000003', 'nama' => 'Infus RL 500ml', 'jenis' => 'obat',
                'kategori' => 'cairan_infus', 'satuan' => 'botol',
                'stok' => 45, 'stok_minimum' => 30, 'stok_maksimum' => 300, // ← HAMPIR HABIS
                'harga_pokok' => 18500, 'harga_jual' => 35000,
                'butuh_resep' => false,
                'supplier_utama_id' => $supplier1?->id,
                'suppliers' => [
                    ['supplier' => $supplier1, 'harga' => 18000, 'utama' => true],
                    ['supplier' => $supplier3, 'harga' => 19000, 'utama' => false],
                ],
            ],
        ];

        foreach ($barangList as $data) {
            $suppliersData = $data['suppliers'] ?? [];
            unset($data['suppliers']);

            $barang = Barang::updateOrCreate(['kode' => $data['kode']], $data);

            foreach ($suppliersData as $s) {
                if (!$s['supplier']) continue;
                SupplierBarang::updateOrCreate(
                    ['barang_id' => $barang->id, 'supplier_id' => $s['supplier']->id],
                    ['harga_terakhir' => $s['harga'], 'is_supplier_utama' => $s['utama']]
                );
            }

            $this->command->info("✓ Barang: {$barang->nama} [stok={$barang->stok}, min={$barang->stok_minimum}]");
        }
    }
}
```

```php
// database/seeders/DatabaseSeeder.php — tambahkan:
$this->call([
    SupplierSeeder::class,
    BarangSeeder::class,
]);
```

```bash
# Perintah migrasi & seed
php artisan migrate
php artisan db:seed --class=SupplierSeeder
php artisan db:seed --class=BarangSeeder
```

---

## 22. Roadmap

### Phase 1 — Core (Minggu 1–2)
- [ ] Migration 7 tabel
- [ ] Model Eloquent + relasi
- [ ] CRUD Supplier (Livewire)
- [ ] CRUD Master Barang + mapping supplier
- [ ] Seeder data awal

### Phase 2 — Purchase Order (Minggu 3–4)
- [ ] Form PO dengan search barang per-supplier
- [ ] Auto-generate nomor PO
- [ ] Approve PO (workflow draft → dikirim)
- [ ] Detail PO + progress penerimaan

### Phase 3 — Goods Receipt & HPR (Minggu 5–6)
- [ ] Form GR linked ke PO
- [ ] Service `verifikasiGr` — update stok + HPR moving average
- [ ] Snapshot HPR di `gr_item`
- [ ] Riwayat mutasi stok per barang
- [ ] Update harga_terakhir di supplier_barang

### Phase 4 — Alert & Prioritas (Minggu 7)
- [ ] Halaman Prioritas Pembelian (Livewire)
- [ ] 3-level alert: Habis / Kritis / Hampir Habis
- [ ] Buat PO 1-klik dari halaman alert
- [ ] PO Massal — kelompok per supplier
- [ ] Laravel Notification ke role apoteker & admin

### Phase 5 — Laporan & Polish (Minggu 8)
- [ ] Laporan mutasi stok (export Excel + PDF)
- [ ] Laporan penerimaan barang per periode
- [ ] Laporan HPR history per barang
- [ ] Laporan nilai inventory (stok × HPR)
- [ ] Integrasi ke modul Farmasi (keluar_resep → mutasi stok)

---

*PRD_Manajemen_Inventory.md v1.0.0*  
*Konsisten dengan PRD_EMR_Laravel.md (Laravel 12 · Livewire 3 · MySQL · Tailwind CSS)*

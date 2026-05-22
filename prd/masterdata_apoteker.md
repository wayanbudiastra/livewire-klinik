# Master Data Apoteker — Farmasi & Manajemen Stok
**Versi:** 2.0.0 (Laravel 12 + Livewire 3)
**Tech Stack:** Laravel 12 · Livewire 3 · Spatie Permission · Eloquent ORM · Tailwind CSS
**Scope:** Modul Farmasi → Master Data & Stok
**Lokasi Menu:** Sidebar → Farmasi → Master Data / Stok Obat
**Changelog v2.0.0:** Rewrite ke Laravel 12 + Livewire 3, tambah satuan, gudang, batch expired

---

## Daftar Isi

1. [Ringkasan Modul](#1-ringkasan-modul)
2. [Entity & Relasi](#2-entity--relasi)
3. [Migration & Model](#3-migration--model)
4. [Business Rules](#4-business-rules)
5. [Repository & Service Layer](#5-repository--service-layer)
6. [Form Request Validation](#6-form-request-validation)
7. [Livewire Components](#7-livewire-components)
8. [Blade Views](#8-blade-views)
9. [Routes & RBAC](#9-routes--rbac)
10. [Struktur Folder](#10-struktur-folder)
11. [User Stories](#11-user-stories)
12. [Seed Data Awal](#12-seed-data-awal)

---

## 1. Ringkasan Modul

```
Farmasi/
├── Master Data
│   ├── Satuan            → Tablet, Botol, Box, Strip, Pcs, dll.
│   ├── Gudang/Lokasi     → Gudang Utama, Apotek Rawat Jalan, Apotek IGD
│   └── Obat & Alkes      → Data master item farmasi (Obat/Alkes, Paten/Generik)
│
└── Stok
    ├── Stok per Gudang   → Stok berbeda di tiap lokasi
    ├── Min-Max Alert     → Reorder Point & Overstock Warning
    ├── Batch Expired     → Log tanggal kadaluarsa per batch
    └── Laporan           → Stok Opname, Slow/Fast Moving, Expired
```

---

## 2. Entity & Relasi

```
Satuan (id, nama, is_active)
    └── Obat.satuan_besar_id  (FK)
    └── Obat.satuan_kecil_id  (FK)

LokasiGudang (id, nama, kode, is_active)
    └── StokGudang.lokasi_gudang_id (FK)

Obat (tabel existing, diperluas)
    ├── StokGudang[]    (1-to-many)
    └── BatchExpired[]  (1-to-many)

StokGudang (obat_id, lokasi_gudang_id, stok, stok_min, stok_max)

BatchExpired (obat_id, nomor_batch, tanggal_expired, stok_batch, catatan)
```

---

## 3. Migration & Model

### 3.1 Tabel Satuan (baru)

```php
// database/migrations/2026_01_06_000001_create_satuan_table.php

Schema::create('satuan', function (Blueprint $table) {
    $table->id();
    $table->string('nama')->unique();  // Tablet, Botol, Box, Strip, Pcs
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

### 3.2 Tabel Lokasi Gudang (baru)

```php
// database/migrations/2026_01_06_000002_create_lokasi_gudang_table.php

Schema::create('lokasi_gudang', function (Blueprint $table) {
    $table->id();
    $table->string('kode')->unique();  // GD-UTAMA, APT-RJ, APT-IGD
    $table->string('nama');
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

### 3.3 Update Tabel `obat` (addendum dari setup_awal.md)

```php
// database/migrations/2026_01_06_000003_update_obat_add_apoteker_fields.php

Schema::table('obat', function (Blueprint $table) {
    $table->string('barcode')->nullable()->after('kode');
    $table->enum('jenis_barang', ['obat', 'alkes'])->default('obat')->after('nama');
    $table->boolean('is_paten')->default(false)->after('generik');
    $table->foreignId('satuan_besar_id')->nullable()
          ->constrained('satuan')->nullOnDelete()->after('satuan');
    $table->foreignId('satuan_kecil_id')->nullable()
          ->constrained('satuan')->nullOnDelete()->after('satuan_besar_id');
    $table->unsignedSmallInteger('konversi')->default(1)->after('satuan_kecil_id');
    $table->decimal('harga_bpjs', 12, 2)->nullable()->after('harga');
    // stok_min & stok_max dikelola per gudang di tabel stok_gudang
});
```

### 3.4 Tabel Stok Gudang (baru)

```php
// database/migrations/2026_01_06_000004_create_stok_gudang_table.php

Schema::create('stok_gudang', function (Blueprint $table) {
    $table->id();
    $table->foreignId('obat_id')->constrained('obat')->onDelete('cascade');
    $table->foreignId('lokasi_gudang_id')->constrained('lokasi_gudang')->onDelete('cascade');
    $table->unsignedInteger('stok')->default(0);
    $table->unsignedInteger('stok_min')->default(10);   // Reorder Point
    $table->unsignedInteger('stok_max')->default(100);  // Overstock threshold
    $table->unique(['obat_id', 'lokasi_gudang_id']);
    $table->timestamps();
});
```

### 3.5 Tabel Batch Expired (baru)

```php
// database/migrations/2026_01_06_000005_create_batch_expired_table.php

Schema::create('batch_expired', function (Blueprint $table) {
    $table->id();
    $table->foreignId('obat_id')->constrained('obat')->onDelete('cascade');
    $table->string('nomor_batch');
    $table->date('tanggal_expired');
    $table->unsignedInteger('stok_batch')->default(0);
    $table->string('catatan')->nullable();
    $table->timestamps();
});
```

---

### 3.6 Model Satuan

```php
// app/Models/Satuan.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Satuan extends Model
{
    protected $table = 'satuan';
    protected $fillable = ['nama', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function scopeAktif($query)
    {
        return $query->where('is_active', true);
    }
}
```

### 3.7 Model LokasiGudang

```php
// app/Models/LokasiGudang.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LokasiGudang extends Model
{
    protected $table = 'lokasi_gudang';
    protected $fillable = ['kode', 'nama', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function stokGudang()
    {
        return $this->hasMany(StokGudang::class);
    }

    public function scopeAktif($query)
    {
        return $query->where('is_active', true);
    }
}
```

### 3.8 Model Obat (diupdate)

```php
// app/Models/Obat.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Obat extends Model
{
    protected $table = 'obat';

    protected $fillable = [
        'kode', 'barcode', 'nama', 'generik',
        'jenis_barang', 'is_paten',
        'satuan', 'satuan_besar_id', 'satuan_kecil_id', 'konversi',
        'stok', 'harga', 'harga_beli', 'harga_bpjs',
        'kategori', 'is_active', 'expired_date',
    ];

    protected function casts(): array
    {
        return [
            'is_active'    => 'boolean',
            'is_paten'     => 'boolean',
            'expired_date' => 'date',
            'harga'        => 'decimal:2',
            'harga_beli'   => 'decimal:2',
            'harga_bpjs'   => 'decimal:2',
        ];
    }

    public function satuanBesar()
    {
        return $this->belongsTo(Satuan::class, 'satuan_besar_id');
    }

    public function satuanKecil()
    {
        return $this->belongsTo(Satuan::class, 'satuan_kecil_id');
    }

    public function stokGudang()
    {
        return $this->hasMany(StokGudang::class);
    }

    public function batchExpired()
    {
        return $this->hasMany(BatchExpired::class)->orderBy('tanggal_expired');
    }

    public function itemResep()
    {
        return $this->hasMany(ItemResep::class);
    }

    // ── Helpers ──────────────────────────────────────────────

    public function scopeAktif($query)
    {
        return $query->where('is_active', true);
    }

    // Cek apakah stok di bawah min (reorder alert)
    public function isReorderPoint(int $lokasiGudangId): bool
    {
        $stok = $this->stokGudang()->where('lokasi_gudang_id', $lokasiGudangId)->first();
        if (! $stok) return false;
        return $stok->stok <= $stok->stok_min;
    }

    // Cek batch yang akan expired dalam N hari
    public function getBatchAkanExpiredAttribute(int $hariKedepan = 90)
    {
        return $this->batchExpired()
            ->where('tanggal_expired', '>=', now())
            ->where('tanggal_expired', '<=', now()->addDays($hariKedepan))
            ->where('stok_batch', '>', 0)
            ->get();
    }
}
```

### 3.9 Model StokGudang

```php
// app/Models/StokGudang.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StokGudang extends Model
{
    protected $table = 'stok_gudang';

    protected $fillable = [
        'obat_id', 'lokasi_gudang_id',
        'stok', 'stok_min', 'stok_max',
    ];

    public function obat()
    {
        return $this->belongsTo(Obat::class);
    }

    public function lokasiGudang()
    {
        return $this->belongsTo(LokasiGudang::class);
    }

    public function isReorderPoint(): bool
    {
        return $this->stok <= $this->stok_min;
    }

    public function isOverstock(): bool
    {
        return $this->stok >= $this->stok_max;
    }

    public function getStatusStokAttribute(): string
    {
        if ($this->stok <= 0)            return 'habis';
        if ($this->isReorderPoint())      return 'reorder';
        if ($this->isOverstock())         return 'overstock';
        return 'normal';
    }
}
```

### 3.10 Model BatchExpired

```php
// app/Models/BatchExpired.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BatchExpired extends Model
{
    protected $table = 'batch_expired';

    protected $fillable = [
        'obat_id', 'nomor_batch',
        'tanggal_expired', 'stok_batch', 'catatan',
    ];

    protected function casts(): array
    {
        return ['tanggal_expired' => 'date'];
    }

    public function obat()
    {
        return $this->belongsTo(Obat::class);
    }

    public function getSisaHariAttribute(): int
    {
        return (int) now()->diffInDays($this->tanggal_expired, false);
    }

    public function getStatusExpiredAttribute(): string
    {
        $sisa = $this->sisa_hari;
        if ($sisa < 0)   return 'expired';
        if ($sisa <= 30) return 'kritis';
        if ($sisa <= 90) return 'warning';
        return 'aman';
    }
}
```

---

## 4. Business Rules

### 4.1 Filter Transaksi (Hard Rule)

```
Hanya Status = 'Aktif' yang muncul di:
  ✓ Input Resep / Pemilihan Obat di SOAP
  ✓ Proses Dispensing
  ✓ Penerimaan Barang (PO)
  ✓ Stok Opname

Status 'Non-Aktif':
  ✓ Tetap tersimpan di database (audit trail)
  ✗ Tidak muncul di semua kolom pencarian transaksi baru
  ✓ Tidak membatalkan transaksi yang sudah berjalan
```

### 4.2 Validasi Satuan & Konversi

```
Contoh: Box → Tablet, konversi = 100
  → 1 Box diterima = 100 Tablet masuk stok
  → Sistem otomatis breakdown stok saat penerimaan

Aturan:
  ✓ satuan_besar wajib jika satuan_kecil diisi
  ✓ konversi wajib > 0
  ✓ Jika satuan_besar = satuan_kecil → konversi = 1
```

### 4.3 Alert Stok

```
Status Stok:
  'habis'    → stok = 0        → merah, blokir transaksi
  'reorder'  → stok ≤ stok_min → kuning, tampil di alert
  'normal'   → stok_min < stok < stok_max → hijau
  'overstock'→ stok ≥ stok_max → biru, peringatan

Alert Expired:
  'expired' → tanggal_expired < hari ini
  'kritis'  → sisa ≤ 30 hari  → merah
  'warning' → sisa ≤ 90 hari  → kuning
  'aman'    → sisa > 90 hari  → hijau
```

### 4.4 Stok per Gudang

```
Satu item obat bisa ada di banyak lokasi gudang.
Stok total = jumlah stok dari semua lokasi.
Min-Max dikonfigurasi per lokasi, bukan global.
```

---

## 5. Repository & Service Layer

### 5.1 ObatRepository

```php
// app/Repositories/ObatRepository.php

namespace App\Repositories;

use App\Models\BatchExpired;
use App\Models\Obat;
use App\Models\StokGudang;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ObatRepository
{
    public function paginate(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        return Obat::with(['satuanBesar:id,nama', 'satuanKecil:id,nama'])
            ->when($filters['search']      ?? null, fn ($q, $s) =>
                $q->where('nama', 'like', "%{$s}%")
                  ->orWhere('kode', 'like', "%{$s}%")
                  ->orWhere('barcode', 'like', "%{$s}%"))
            ->when($filters['jenis_barang'] ?? null, fn ($q, $j) =>
                $q->where('jenis_barang', $j))
            ->when(isset($filters['is_active']),
                fn ($q) => $q->where('is_active', $filters['is_active']))
            ->when($filters['reorder'] ?? false, fn ($q) =>
                $q->whereHas('stokGudang', fn ($sq) =>
                    $sq->whereColumn('stok', '<=', 'stok_min')))
            ->orderBy($filters['sort_by'] ?? 'nama', $filters['sort_dir'] ?? 'asc')
            ->paginate($perPage);
    }

    public function getReorderAlert(): Collection
    {
        return Obat::aktif()
            ->with(['stokGudang.lokasiGudang'])
            ->whereHas('stokGudang', fn ($q) =>
                $q->whereColumn('stok', '<=', 'stok_min'))
            ->get();
    }

    public function getBatchAkanExpired(int $hariKedepan = 90): Collection
    {
        return BatchExpired::with('obat:id,kode,nama')
            ->where('tanggal_expired', '>=', now())
            ->where('tanggal_expired', '<=', now()->addDays($hariKedepan))
            ->where('stok_batch', '>', 0)
            ->orderBy('tanggal_expired')
            ->get();
    }

    public function upsertStokGudang(int $obatId, int $lokasiId, array $data): StokGudang
    {
        return StokGudang::updateOrCreate(
            ['obat_id' => $obatId, 'lokasi_gudang_id' => $lokasiId],
            $data
        );
    }
}
```

### 5.2 FarmasiService

```php
// app/Services/FarmasiService.php

namespace App\Services;

use App\Models\BatchExpired;
use App\Models\Obat;
use App\Models\StokGudang;
use App\Repositories\ObatRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FarmasiService
{
    public function __construct(
        private readonly ObatRepository $repo
    ) {}

    // ── Master Obat ───────────────────────────────────────────

    public function createObat(array $data): Obat
    {
        $obat = Obat::create($data);

        activity('farmasi')
            ->performedOn($obat)
            ->causedBy(auth()->user())
            ->log('Obat/Alkes baru ditambahkan');

        return $obat;
    }

    public function updateObat(int $id, array $data): Obat
    {
        $obat = Obat::findOrFail($id);
        $obat->update($data);

        activity('farmasi')
            ->performedOn($obat)
            ->causedBy(auth()->user())
            ->log('Data obat/alkes diupdate');

        return $obat;
    }

    public function toggleAktif(int $id): Obat
    {
        $obat = Obat::findOrFail($id);
        $obat->update(['is_active' => ! $obat->is_active]);
        return $obat;
    }

    // ── Stok Gudang ───────────────────────────────────────────

    public function aturMinMax(int $obatId, int $lokasiId, int $stokMin, int $stokMax): StokGudang
    {
        if ($stokMin >= $stokMax) {
            throw ValidationException::withMessages([
                'stok_min' => 'Stok minimum harus lebih kecil dari stok maksimum.',
            ]);
        }

        return $this->repo->upsertStokGudang($obatId, $lokasiId, [
            'stok_min' => $stokMin,
            'stok_max' => $stokMax,
        ]);
    }

    // ── Batch Expired ─────────────────────────────────────────

    public function tambahBatch(int $obatId, array $data): BatchExpired
    {
        if (strtotime($data['tanggal_expired']) <= time()) {
            throw ValidationException::withMessages([
                'tanggal_expired' => 'Tanggal expired tidak boleh di masa lalu.',
            ]);
        }

        return BatchExpired::create(array_merge($data, ['obat_id' => $obatId]));
    }

    public function getReorderAlert()
    {
        return $this->repo->getReorderAlert();
    }

    public function getBatchAkanExpired(int $hari = 90)
    {
        return $this->repo->getBatchAkanExpired($hari);
    }

    // ── Kalkulasi Stok Total ──────────────────────────────────

    public function getStokTotal(int $obatId): int
    {
        return StokGudang::where('obat_id', $obatId)->sum('stok');
    }
}
```

---

## 6. Form Request Validation

### 6.1 StoreObatRequest

```php
// app/Http/Requests/Farmasi/StoreObatRequest.php

namespace App\Http\Requests\Farmasi;

use Illuminate\Foundation\Http\FormRequest;

class StoreObatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('obat.create');
    }

    public function rules(): array
    {
        return [
            'kode'           => ['required', 'string', 'unique:obat,kode'],
            'barcode'        => ['nullable', 'string'],
            'nama'           => ['required', 'string', 'min:3'],
            'generik'        => ['nullable', 'string'],
            'jenis_barang'   => ['required', 'in:obat,alkes'],
            'is_paten'       => ['boolean'],
            'satuan'         => ['required', 'string'],
            'satuan_besar_id'=> ['nullable', 'integer', 'exists:satuan,id'],
            'satuan_kecil_id'=> ['nullable', 'integer', 'exists:satuan,id'],
            'konversi'       => ['required', 'integer', 'min:1'],
            'stok'           => ['required', 'integer', 'min:0'],
            'harga'          => ['required', 'numeric', 'min:0'],
            'harga_beli'     => ['nullable', 'numeric', 'min:0'],
            'harga_bpjs'     => ['nullable', 'numeric', 'min:0'],
            'kategori'       => ['nullable', 'string'],
            'expired_date'   => ['nullable', 'date', 'after:today'],
        ];
    }
}
```

### 6.2 StoreBatchExpiredRequest

```php
// app/Http/Requests/Farmasi/StoreBatchExpiredRequest.php

namespace App\Http\Requests\Farmasi;

use Illuminate\Foundation\Http\FormRequest;

class StoreBatchExpiredRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('obat.edit');
    }

    public function rules(): array
    {
        return [
            'nomor_batch'     => ['required', 'string'],
            'tanggal_expired' => ['required', 'date', 'after:today'],
            'stok_batch'      => ['required', 'integer', 'min:1'],
            'catatan'         => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'tanggal_expired.after' => 'Tanggal expired harus di masa depan.',
        ];
    }
}
```

---

## 7. Livewire Components

### 7.1 ObatTable — List & Search

```php
// app/Livewire/Farmasi/ObatTable.php

namespace App\Livewire\Farmasi;

use App\Models\Obat;
use App\Services\FarmasiService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ObatTable extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search       = '';

    #[Url]
    public string $filterJenis  = '';

    #[Url]
    public string $filterStatus = 'aktif'; // tampilkan aktif saja by default

    #[Url]
    public bool   $filterReorder = false;

    public int $perPage = 10;

    public function updatingSearch(): void    { $this->resetPage(); }
    public function updatingFilterJenis(): void { $this->resetPage(); }

    #[Computed]
    public function obat()
    {
        return Obat::with(['satuanBesar:id,nama', 'satuanKecil:id,nama'])
            ->when($this->search, fn ($q, $s) =>
                $q->where('nama', 'like', "%{$s}%")
                  ->orWhere('kode', 'like', "%{$s}%")
                  ->orWhere('barcode', 'like', "%{$s}%"))
            ->when($this->filterJenis, fn ($q, $j) =>
                $q->where('jenis_barang', $j))
            ->when($this->filterStatus === 'aktif',
                fn ($q) => $q->where('is_active', true))
            ->when($this->filterStatus === 'nonaktif',
                fn ($q) => $q->where('is_active', false))
            ->when($this->filterReorder,
                fn ($q) => $q->whereHas('stokGudang', fn ($sq) =>
                    $sq->whereColumn('stok', '<=', 'stok_min')))
            ->orderBy('nama')
            ->paginate($this->perPage);
    }

    public function toggleAktif(int $id): void
    {
        $this->authorize('obat.edit');
        app(FarmasiService::class)->toggleAktif($id);
        unset($this->obat);
        $this->dispatch('notify', type: 'success', message: 'Status obat diupdate.');
    }

    #[On('obat-saved')]
    public function refresh(): void { unset($this->obat); }

    public function render()
    {
        return view('livewire.farmasi.obat-table');
    }
}
```

### 7.2 ObatForm — Create/Edit

```php
// app/Livewire/Farmasi/ObatForm.php

namespace App\Livewire\Farmasi;

use App\Models\Obat;
use App\Models\Satuan;
use App\Services\FarmasiService;
use Illuminate\Validation\Rule;
use Livewire\Component;

class ObatForm extends Component
{
    public bool  $showModal = false;
    public ?int  $obatId   = null;
    public bool  $isEdit   = false;

    public string $kode            = '';
    public string $barcode         = '';
    public string $nama            = '';
    public string $generik         = '';
    public string $jenis_barang    = 'obat';
    public bool   $is_paten        = false;
    public string $satuan          = '';
    public ?int   $satuan_besar_id = null;
    public ?int   $satuan_kecil_id = null;
    public int    $konversi        = 1;
    public string $harga           = '';
    public string $harga_beli      = '';
    public string $harga_bpjs      = '';
    public string $kategori        = '';
    public string $expired_date    = '';
    public bool   $is_active       = true;

    public function getRules(): array
    {
        $uniqueKode = $this->isEdit
            ? Rule::unique('obat', 'kode')->ignore($this->obatId)
            : 'unique:obat,kode';

        return [
            'kode'           => ['required', 'string', $uniqueKode],
            'barcode'        => ['nullable', 'string'],
            'nama'           => ['required', 'string', 'min:3'],
            'generik'        => ['nullable', 'string'],
            'jenis_barang'   => ['required', 'in:obat,alkes'],
            'satuan'         => ['required', 'string'],
            'satuan_besar_id'=> ['nullable', 'integer', 'exists:satuan,id'],
            'satuan_kecil_id'=> ['nullable', 'integer', 'exists:satuan,id'],
            'konversi'       => ['required', 'integer', 'min:1'],
            'harga'          => ['required', 'numeric', 'min:0'],
            'harga_beli'     => ['nullable', 'numeric', 'min:0'],
            'harga_bpjs'     => ['nullable', 'numeric', 'min:0'],
            'kategori'       => ['nullable', 'string'],
            'expired_date'   => ['nullable', 'date'],
        ];
    }

    public function openCreate(): void
    {
        $this->authorize('obat.create');
        $this->reset(['obatId','kode','barcode','nama','generik','harga','harga_beli',
                      'harga_bpjs','kategori','expired_date','satuan_besar_id','satuan_kecil_id']);
        $this->jenis_barang = 'obat';
        $this->is_paten     = false;
        $this->konversi     = 1;
        $this->is_active    = true;
        $this->isEdit       = false;
        $this->showModal    = true;
        $this->resetValidation();
    }

    public function openEdit(int $id): void
    {
        $this->authorize('obat.edit');
        $obat = Obat::findOrFail($id);
        $this->obatId          = $id;
        $this->kode            = $obat->kode;
        $this->barcode         = $obat->barcode         ?? '';
        $this->nama            = $obat->nama;
        $this->generik         = $obat->generik         ?? '';
        $this->jenis_barang    = $obat->jenis_barang    ?? 'obat';
        $this->is_paten        = (bool) $obat->is_paten;
        $this->satuan          = $obat->satuan;
        $this->satuan_besar_id = $obat->satuan_besar_id;
        $this->satuan_kecil_id = $obat->satuan_kecil_id;
        $this->konversi        = $obat->konversi        ?? 1;
        $this->harga           = (string) $obat->harga;
        $this->harga_beli      = $obat->harga_beli ? (string) $obat->harga_beli : '';
        $this->harga_bpjs      = $obat->harga_bpjs ? (string) $obat->harga_bpjs : '';
        $this->kategori        = $obat->kategori        ?? '';
        $this->expired_date    = $obat->expired_date ? $obat->expired_date->format('Y-m-d') : '';
        $this->is_active       = (bool) $obat->is_active;
        $this->isEdit          = true;
        $this->showModal       = true;
        $this->resetValidation();
    }

    public function save(FarmasiService $service): void
    {
        $this->validate($this->getRules());

        $data = [
            'kode'            => strtoupper($this->kode),
            'barcode'         => $this->barcode          ?: null,
            'nama'            => $this->nama,
            'generik'         => $this->generik          ?: null,
            'jenis_barang'    => $this->jenis_barang,
            'is_paten'        => $this->is_paten,
            'satuan'          => $this->satuan,
            'satuan_besar_id' => $this->satuan_besar_id,
            'satuan_kecil_id' => $this->satuan_kecil_id,
            'konversi'        => $this->konversi,
            'harga'           => (float) $this->harga,
            'harga_beli'      => $this->harga_beli ? (float) $this->harga_beli : null,
            'harga_bpjs'      => $this->harga_bpjs ? (float) $this->harga_bpjs : null,
            'kategori'        => $this->kategori         ?: null,
            'expired_date'    => $this->expired_date     ?: null,
            'is_active'       => $this->is_active,
        ];

        $this->isEdit
            ? $service->updateObat($this->obatId, $data)
            : $service->createObat($data);

        $this->showModal = false;
        $this->dispatch('obat-saved');
        $msg = $this->isEdit ? 'Data obat berhasil diupdate.' : 'Obat/Alkes baru ditambahkan.';
        $this->dispatch('notify', type: 'success', message: $msg);
    }

    public function getSatuanListProperty()
    {
        return Satuan::aktif()->orderBy('nama')->get(['id', 'nama']);
    }

    public function render()
    {
        return view('livewire.farmasi.obat-form');
    }
}
```

### 7.3 StokAlert — Dashboard Alert Reorder & Expired

```php
// app/Livewire/Farmasi/StokAlert.php

namespace App\Livewire\Farmasi;

use App\Services\FarmasiService;
use Livewire\Attributes\Computed;
use Livewire\Component;

class StokAlert extends Component
{
    public int $hariExpired = 90; // tampilkan expired dalam 90 hari ke depan

    #[Computed]
    public function reorderList()
    {
        return app(FarmasiService::class)->getReorderAlert();
    }

    #[Computed]
    public function expiredList()
    {
        return app(FarmasiService::class)->getBatchAkanExpired($this->hariExpired);
    }

    public function render()
    {
        return view('livewire.farmasi.stok-alert');
    }
}
```

---

## 8. Blade Views

### Halaman Index Farmasi (Tab)

```blade
{{-- resources/views/farmasi/index.blade.php --}}
<x-app-layout>
    <x-slot name="title">Farmasi</x-slot>

    <div class="page-header">
        <div>
            <h2 class="page-title">Farmasi</h2>
            <p class="page-subtitle">Master data obat/alkes, stok, dan monitoring expired</p>
        </div>
    </div>

    @php $tab = request()->query('tab', 'stok'); @endphp

    <div class="border-b border-gray-200 dark:border-gray-700 mb-6">
        <nav class="flex gap-0 -mb-px overflow-x-auto">
            @foreach ([
                'stok'       => 'Stok Obat & Alkes',
                'alert'      => 'Alert Stok & Expired',
                'satuan'     => 'Satuan',
                'gudang'     => 'Lokasi Gudang',
            ] as $key => $label)
            <a href="?tab={{ $key }}"
               @class([
                   'px-5 py-3 text-sm font-medium border-b-2 whitespace-nowrap transition-colors',
                   'border-[#0a3d62] text-[#0a3d62] dark:border-blue-400 dark:text-blue-400' => $tab === $key,
                   'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400' => $tab !== $key,
               ])>{{ $label }}</a>
            @endforeach
        </nav>
    </div>

    @switch($tab)
        @case('stok')
            <livewire:farmasi.obat-table />
            <livewire:farmasi.obat-form />
            @break
        @case('alert')
            <livewire:farmasi.stok-alert />
            @break
        @case('satuan')
            <livewire:farmasi.satuan-table />
            @break
        @case('gudang')
            <livewire:farmasi.gudang-table />
            @break
    @endswitch

    {{-- Toast --}}
    <div x-data="{ show:false, type:'success', message:'' }"
         x-on:notify.window="show=true; type=$event.detail.type; message=$event.detail.message; setTimeout(()=>show=false,3500)"
         x-show="show" x-transition class="fixed bottom-5 right-5 z-50 min-w-72">
        <div :class="{ 'bg-emerald-50 border-emerald-400 text-emerald-800': type==='success',
                        'bg-red-50 border-red-400 text-red-800': type==='error' }"
             class="flex items-center gap-3 rounded-xl border px-4 py-3 shadow-lg text-sm font-medium">
            <span x-text="message"></span>
        </div>
    </div>
</x-app-layout>
```

### Blade Component — Badge Status Stok

```blade
{{-- resources/views/components/stok-status.blade.php --}}
@props(['status'])

@php
$map = [
    'habis'     => ['label' => 'Habis',     'class' => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300'],
    'reorder'   => ['label' => 'Reorder!',  'class' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300'],
    'overstock' => ['label' => 'Overstock', 'class' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300'],
    'normal'    => ['label' => 'Normal',    'class' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'],
];
$item = $map[$status] ?? $map['normal'];
@endphp

<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium {{ $item['class'] }}">
    {{ $item['label'] }}
</span>
```

---

## 9. Routes & RBAC

### Routes

```php
// Tambahkan ke routes/web.php

Route::prefix('farmasi')->name('farmasi.')->middleware(['auth', 'active'])->group(function () {

    // Master Data & Stok (apoteker)
    Route::middleware('permission:obat.view')->group(function () {
        Route::get('/stok-obat', fn () => view('farmasi.index'))
             ->name('stok.index');
    });

    // Resep (sudah ada, placeholder)
    Route::middleware('permission:resep.view')->group(function () {
        Route::get('/resep', fn () => view('coming-soon', [
            'modul'    => 'Resep Elektronik',
            'deskripsi'=> 'Validasi & dispensing resep dari dokter.',
            'progress' => 10,
        ]))->name('resep.index');
    });
});
```

### Permissions yang Digunakan

```
obat.view    → apoteker, admin, dokter, kasir
obat.create  → apoteker, admin
obat.edit    → apoteker, admin
obat.delete  → admin
```

### Update Sidebar

```blade
{{-- Di layouts/app.blade.php --}}
<x-sidebar-item route="farmasi.stok.index" icon="beaker" permission="obat.view">
    Farmasi
</x-sidebar-item>
```

---

## 10. Struktur Folder

```
app/
├── Http/Requests/Farmasi/
│   ├── StoreObatRequest.php
│   ├── UpdateObatRequest.php
│   └── StoreBatchExpiredRequest.php
│
├── Livewire/Farmasi/
│   ├── ObatTable.php         ← list + search + filter jenis/status
│   ├── ObatForm.php          ← modal create/edit
│   ├── StokAlert.php         ← dashboard reorder & expired alert
│   ├── SatuanTable.php       ← kelola satuan
│   └── GudangTable.php       ← kelola lokasi gudang
│
├── Models/
│   ├── Obat.php              (diupdate)
│   ├── Satuan.php            (baru)
│   ├── LokasiGudang.php      (baru)
│   ├── StokGudang.php        (baru)
│   └── BatchExpired.php      (baru)
│
├── Repositories/
│   └── ObatRepository.php   (baru)
│
└── Services/
    └── FarmasiService.php    (baru)

resources/views/
├── components/
│   └── stok-status.blade.php
├── farmasi/
│   └── index.blade.php
└── livewire/farmasi/
    ├── obat-table.blade.php
    ├── obat-form.blade.php
    ├── stok-alert.blade.php
    ├── satuan-table.blade.php
    └── gudang-table.blade.php

database/migrations/
├── 2026_01_06_000001_create_satuan_table.php
├── 2026_01_06_000002_create_lokasi_gudang_table.php
├── 2026_01_06_000003_update_obat_add_apoteker_fields.php
├── 2026_01_06_000004_create_stok_gudang_table.php
└── 2026_01_06_000005_create_batch_expired_table.php
```

---

## 11. User Stories

| ID | Persona | Skenario | Expected Behavior |
|----|---------|----------|-------------------|
| **US01** | Apoteker | Tambah obat baru "Paracetamol 500mg" | Form create dengan satuan, harga umum, harga BPJS, jenis obat/alkes |
| **US02** | Apoteker | Tentukan satuan Box → Tablet, konversi 100 | Sistem otomatis hitung stok saat penerimaan |
| **US03** | Apoteker | Stok Amoxicillin menyentuh stok_min | Muncul alert reorder di dashboard & badge kuning di tabel |
| **US04** | Apoteker | Input batch Paracetamol expired 15/01/2026 | Muncul di warning expired 3 bulan sebelumnya |
| **US05** | Dokter | Input resep → pilih obat | Hanya obat dengan status **Aktif** yang muncul |
| **US06** | Admin | Nonaktifkan obat yang sudah tidak diproduksi | Status Non-Aktif, tidak muncul di transaksi baru, data historis tetap ada |
| **US07** | Apoteker | Lihat stok di Apotek IGD vs Gudang Utama | Stok per lokasi ditampilkan secara terpisah |
| **US08** | Apoteker | Filter tabel tampilkan hanya yang reorder | Toggle filter "Reorder" → hanya obat stok ≤ stok_min |

---

## 12. Seed Data Awal

```php
// database/seeders/FarmasiSeeder.php

namespace Database\Seeders;

use App\Models\LokasiGudang;
use App\Models\Satuan;
use Illuminate\Database\Seeder;

class FarmasiSeeder extends Seeder
{
    public function run(): void
    {
        // ── Satuan ────────────────────────────────────────────
        $satuanList = ['Tablet','Kapsul','Botol','Box','Strip','Pcs',
                       'Ampul','Vial','Sachet','Tube','ml','mg','gram'];

        foreach ($satuanList as $s) {
            Satuan::firstOrCreate(['nama' => $s]);
        }
        $this->command->info('✓ Satuan: ' . count($satuanList) . ' item');

        // ── Lokasi Gudang ─────────────────────────────────────
        $gudangList = [
            ['kode' => 'GD-UTAMA', 'nama' => 'Gudang Utama'],
            ['kode' => 'APT-RJ',   'nama' => 'Apotek Rawat Jalan'],
            ['kode' => 'APT-IGD',  'nama' => 'Apotek IGD'],
            ['kode' => 'APT-RI',   'nama' => 'Apotek Rawat Inap'],
        ];

        foreach ($gudangList as $g) {
            LokasiGudang::firstOrCreate(['kode' => $g['kode']], $g);
        }
        $this->command->info('✓ Lokasi Gudang: ' . count($gudangList) . ' lokasi');
        $this->command->info('✅ FarmasiSeeder selesai.');
    }
}
```

Tambahkan ke `DatabaseSeeder.php`:
```php
$this->call([
    // ... existing seeders ...
    FarmasiSeeder::class,
]);
```

---

## Appendix — Alur Alert Stok

```
Setiap kali stok berubah (penerimaan/dispensing):
    ↓
Cek: stok ≤ stok_min?
    → YA → set status 'reorder', tampil badge kuning di tabel
            kirim notif ke apoteker (opsional fase berikutnya)
    → TIDAK → cek stok ≥ stok_max?
                → YA → badge biru 'overstock'
                → TIDAK → badge hijau 'normal'

Setiap hari (scheduler, fase berikutnya):
    → Scan batch_expired WHERE tanggal_expired <= NOW() + 90 hari
    → Tampil di dashboard alert expired
```

---

*masterdata_apoteker.md v2.0.0 · Laravel 12 + Livewire 3 · Living document*

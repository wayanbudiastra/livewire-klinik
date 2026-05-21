# Master Data V2 — Clinical & Asset Setup
**Versi:** 2.3-r2 (Laravel 12 + Livewire 3)
**Tech Stack:** Laravel 12 · Livewire 3 · Spatie Permission · Eloquent ORM
**Depends On:** `setup_awal.md` · `masterdata_v1.md v2.0.0`
**Changelog v2.3-r2:** Rewrite penuh dari Next.js/Prisma ke Laravel 12 + Livewire 3

---

## Daftar Isi

1. [Ringkasan & Konteks Integrasi](#1-ringkasan--konteks-integrasi)
2. [Modul yang Dicakup](#2-modul-yang-dicakup)
3. [Business Rules & Access Logic](#3-business-rules--access-logic)
4. [Migration & Model](#4-migration--model)
5. [Enum & Kategori Tindakan](#5-enum--kategori-tindakan)
6. [Search Engine Logic](#6-search-engine-logic)
7. [Repository Layer](#7-repository-layer)
8. [Service Layer](#8-service-layer)
9. [Form Request Validation](#9-form-request-validation)
10. [Livewire Components](#10-livewire-components)
11. [Routes & RBAC](#11-routes--rbac)
12. [Struktur Folder](#12-struktur-folder)
13. [User Stories](#13-user-stories)
14. [Seed Data Awal](#14-seed-data-awal)

---

## 1. Ringkasan & Konteks Integrasi

```
setup_awal.md              → Tech stack, migration dasar, seeder awal
    ↓
masterdata_v1.md           → User management, RBAC, Livewire components
    ↓
masterdata_v2.md (ini)     → Clinical master data:
                             Poli, Tindakan (lokal + mapping poli),
                             Lab, Radiologi, Peralatan Medis (global)
```

### Gap yang Diselesaikan V2

`setup_awal.md` sudah mendefinisikan tabel `master_tindakan` dan `poli`, namun belum ada:
- Pembedaan **kategori tindakan** (lokal vs global) menggunakan Eloquent enum
- Tabel **item_penunjang** (Lab & Radiologi) dan **peralatan_medis**
- **Mapping Tindakan ↔ Poli** via tabel pivot `tindakan_poli`
- **Search engine** Livewire yang filter by `poli_id` dokter yang login
- **Tracking penggunaan** alat medis per poli

---

## 2. Modul yang Dicakup

| # | Modul | Tipe Akses | Deskripsi |
|---|-------|-----------|-----------|
| 1 | **Master Poliklinik** | — | Diperluas dengan relasi tindakan & peralatan |
| 2 | **Master Tindakan** | 🔒 Lokal (mapped) | Dokter hanya lihat tindakan poli-nya sendiri |
| 3 | **Master Laboratorium** | 🌐 Global | Tersedia di semua poli tanpa mapping |
| 4 | **Master Radiologi** | 🌐 Global | Tersedia di semua poli tanpa mapping |
| 5 | **Master Peralatan Medis** | 🌐 Global | Inventaris alat + tracking penggunaan per poli |

---

## 3. Business Rules & Access Logic

### 3.1 Tindakan — Lokal (Mapping Wajib)

```
Dokter Poli Mata login
    ↓
Eloquent query: MasterTindakan
    ->whereHas('poli', fn($q) => $q->where('poli.id', $dokter->poli_id))
    ->where('kategori', 'tindakan')
    ↓
Hanya tampil: Funduskopi, Tonometri, Refraksi, dll.
    ↓
Tindakan Poli Bedah TIDAK muncul.
```

**Aturan:**
- Setiap `MasterTindakan` dengan `kategori = tindakan` **wajib** relasi ke minimal satu `Poli` via tabel `tindakan_poli`
- Admin dapat memetakan satu tindakan ke **lebih dari satu poli** (many-to-many)
- Tindakan tanpa mapping poli tidak muncul di antarmuka dokter manapun

### 3.2 Lab, Radiologi, Peralatan — Global (Tanpa Mapping)

```
Input item baru (Lab / Rad / Peralatan)
    ↓
Langsung tersedia di seluruh poli
    ↓
Tidak perlu aksi mapping oleh Admin
```

### 3.3 Tabel Keputusan Akses

| Kategori | Model | Ada `poli_id`? | Filter di Query? | Perlu Mapping? |
|----------|-------|:--------------:|:----------------:|:--------------:|
| `tindakan` | `MasterTindakan` + `tindakan_poli` | ✅ via pivot | ✅ Ya | ✅ Wajib |
| `lab` | `ItemPenunjang` | ❌ | ❌ Tidak | ❌ |
| `radiologi` | `ItemPenunjang` | ❌ | ❌ Tidak | ❌ |
| `peralatan` | `PeralatanMedis` | ❌ tracking saja | ❌ Tidak | ❌ |

---

## 4. Migration & Model

### 4.1 Update Tabel `master_tindakan` (addendum dari setup_awal.md)

```php
// database/migrations/2026_01_02_000001_update_master_tindakan_add_v2_fields.php

Schema::table('master_tindakan', function (Blueprint $table) {
    $table->enum('kategori', ['tindakan', 'lab', 'radiologi'])
          ->default('tindakan')->change();
    $table->string('deskripsi')->nullable()->after('nama');
    $table->decimal('tarif_bpjs', 12, 2)->nullable()->after('tarif');
});
```

### 4.2 Tabel Pivot Tindakan ↔ Poli

```php
// database/migrations/2026_01_02_000002_create_tindakan_poli_table.php

Schema::create('tindakan_poli', function (Blueprint $table) {
    $table->id();
    $table->foreignId('master_tindakan_id')
          ->constrained('master_tindakan')->onDelete('cascade');
    $table->foreignId('poli_id')
          ->constrained('poli')->onDelete('cascade');
    $table->unique(['master_tindakan_id', 'poli_id']);
    $table->timestamps();
});
```

### 4.3 Tabel Item Penunjang (Lab & Radiologi)

```php
// database/migrations/2026_01_02_000003_create_item_penunjang_table.php

Schema::create('item_penunjang', function (Blueprint $table) {
    $table->id();
    $table->string('kode')->unique();
    $table->string('nama');
    $table->string('deskripsi')->nullable();
    $table->enum('kategori', ['lab', 'radiologi']);
    $table->decimal('tarif', 12, 2);
    $table->decimal('tarif_bpjs', 12, 2)->nullable();
    $table->string('satuan_waktu')->nullable(); // e.g. "2 jam", "1 hari kerja"
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

### 4.4 Tabel Peralatan Medis

```php
// database/migrations/2026_01_02_000004_create_peralatan_medis_table.php

Schema::create('peralatan_medis', function (Blueprint $table) {
    $table->id();
    $table->string('kode')->unique();
    $table->string('nama');
    $table->string('merk')->nullable();
    $table->string('nomor_seri')->nullable()->unique();
    $table->string('deskripsi')->nullable();
    $table->enum('status', ['tersedia', 'digunakan', 'maintenance', 'rusak'])
          ->default('tersedia');
    $table->string('lokasi_terakhir')->nullable();
    $table->foreignId('poli_terakhir_id')->nullable()->constrained('poli')->nullOnDelete();
    $table->date('tanggal_kalibrasi')->nullable();
    $table->timestamps();
});
```

### 4.5 Tabel Riwayat Penggunaan Alat

```php
// database/migrations/2026_01_02_000005_create_penggunaan_alat_table.php

Schema::create('penggunaan_alat', function (Blueprint $table) {
    $table->id();
    $table->foreignId('peralatan_id')
          ->constrained('peralatan_medis')->onDelete('restrict');
    $table->foreignId('poli_id')
          ->constrained('poli')->onDelete('restrict');
    $table->foreignId('kunjungan_id')->nullable()
          ->constrained('kunjungan')->nullOnDelete();
    $table->string('dipakai_oleh')->nullable();
    $table->dateTime('waktu_mulai')->useCurrent();
    $table->dateTime('waktu_selesai')->nullable();
    $table->text('catatan')->nullable();
    $table->timestamps();
});
```

### 4.6 Tabel Permintaan Penunjang

```php
// database/migrations/2026_01_02_000006_create_permintaan_penunjang_table.php

Schema::create('permintaan_penunjang', function (Blueprint $table) {
    $table->id();
    $table->foreignId('kunjungan_id')
          ->constrained('kunjungan')->onDelete('restrict');
    $table->foreignId('item_penunjang_id')
          ->constrained('item_penunjang')->onDelete('restrict');
    $table->unsignedSmallInteger('jumlah')->default(1);
    $table->text('catatan')->nullable();
    $table->enum('status', ['dipesan', 'diproses', 'selesai', 'dibatalkan'])
          ->default('dipesan');
    $table->string('hasil_url')->nullable(); // Link PDF/image hasil
    $table->timestamps();
});
```

---

### 4.7 Model — MasterTindakan (diupdate)

```php
// app/Models/MasterTindakan.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MasterTindakan extends Model
{
    protected $table = 'master_tindakan';

    protected $fillable = [
        'kode', 'nama', 'deskripsi', 'tarif', 'tarif_bpjs',
        'kategori', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'tarif'      => 'decimal:2',
            'tarif_bpjs' => 'decimal:2',
            'is_active'  => 'boolean',
        ];
    }

    // Many-to-many ke Poli (hanya untuk kategori = tindakan)
    public function poli()
    {
        return $this->belongsToMany(Poli::class, 'tindakan_poli');
    }

    // Scope: tindakan milik poli tertentu
    public function scopeUntukPoli($query, int $poliId)
    {
        return $query->where('kategori', 'tindakan')
                     ->whereHas('poli', fn ($q) => $q->where('poli.id', $poliId));
    }

    // Scope: item global (lab/radiologi)
    public function scopeGlobal($query)
    {
        return $query->whereIn('kategori', ['lab', 'radiologi']);
    }

    public function scopeAktif($query)
    {
        return $query->where('is_active', true);
    }
}
```

### 4.8 Model — Poli (diupdate)

```php
// app/Models/Poli.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Poli extends Model
{
    protected $fillable = ['nama', 'kode', 'deskripsi', 'lantai', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function dokter()
    {
        return $this->hasMany(Dokter::class);
    }

    public function kunjungan()
    {
        return $this->hasMany(Kunjungan::class);
    }

    public function tindakan()
    {
        return $this->belongsToMany(MasterTindakan::class, 'tindakan_poli');
    }

    public function penggunaanAlat()
    {
        return $this->hasMany(PenggunaanAlat::class);
    }
}
```

### 4.9 Model — ItemPenunjang

```php
// app/Models/ItemPenunjang.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItemPenunjang extends Model
{
    protected $fillable = [
        'kode', 'nama', 'deskripsi', 'kategori',
        'tarif', 'tarif_bpjs', 'satuan_waktu', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'tarif'      => 'decimal:2',
            'tarif_bpjs' => 'decimal:2',
            'is_active'  => 'boolean',
        ];
    }

    public function permintaan()
    {
        return $this->hasMany(PermintaanPenunjang::class);
    }

    public function scopeAktif($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeLab($query)
    {
        return $query->where('kategori', 'lab');
    }

    public function scopeRadiologi($query)
    {
        return $query->where('kategori', 'radiologi');
    }
}
```

### 4.10 Model — PeralatanMedis

```php
// app/Models/PeralatanMedis.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PeralatanMedis extends Model
{
    protected $fillable = [
        'kode', 'nama', 'merk', 'nomor_seri', 'deskripsi',
        'status', 'lokasi_terakhir', 'poli_terakhir_id', 'tanggal_kalibrasi',
    ];

    protected function casts(): array
    {
        return ['tanggal_kalibrasi' => 'date'];
    }

    public function poliTerakhir()
    {
        return $this->belongsTo(Poli::class, 'poli_terakhir_id');
    }

    public function riwayatPenggunaan()
    {
        return $this->hasMany(PenggunaanAlat::class, 'peralatan_id');
    }

    public function scopeTersedia($query)
    {
        return $query->where('status', 'tersedia');
    }
}
```

---

## 5. Enum & Kategori Tindakan

```php
// Nilai enum yang dipakai di database:

// master_tindakan.kategori:
'tindakan'  // Lokal — wajib mapping ke Poli
'lab'       // Global — tanpa mapping (disimpan di item_penunjang)
'radiologi' // Global — tanpa mapping (disimpan di item_penunjang)

// peralatan_medis.status:
'tersedia'    // Siap digunakan
'digunakan'   // Sedang dipakai di poli
'maintenance' // Dalam perawatan
'rusak'       // Tidak bisa digunakan
```

**Pembagian di UI:**
```
MasterTindakan (kategori = tindakan)
├── Pemasangan Infus, Jahit Luka, EKG, Pemeriksaan Fisik
└── Filter by poli_id dokter yang login

ItemPenunjang (kategori = lab)
├── Darah Lengkap, Urinalisis, HbA1C, Kultur Darah
└── Tampil semua (global)

ItemPenunjang (kategori = radiologi)
├── Foto Thorax, USG Abdomen, CT-Scan, MRI
└── Tampil semua (global)

PeralatanMedis
├── Oxymeter, Tensimeter, Nebulizer, ECG Monitor
└── Tampil semua (global) + tracking poli penggunaan
```

---

## 6. Search Engine Logic

### 6.1 Alur Query (Livewire)

```
Dokter membuka form order di kunjungan
    ↓
Livewire component: wire:model.live.debounce.400ms="search"
    ↓
PHP query gabung dua sumber:
    │
    ├─ [A] MasterTindakan
    │       ->where('kategori', 'tindakan')
    │       ->where('is_active', true)
    │       ->whereHas('poli', fn($q) => $q->where('poli.id', $poliId))
    │       ->where('nama', 'like', "%{$search}%")
    │
    └─ [B] ItemPenunjang
            ->whereIn('kategori', ['lab', 'radiologi'])
            ->where('is_active', true)
            ->where('nama', 'like', "%{$search}%")
    ↓
Merge hasil + tandai field 'sumber'
    ↓
Tampil di Livewire dropdown secara real-time
```

### 6.2 Implementasi di Repository

```php
// app/Repositories/MasterdataRepository.php

namespace App\Repositories;

use App\Models\ItemPenunjang;
use App\Models\MasterTindakan;
use Illuminate\Support\Collection;

class MasterdataRepository
{
    /**
     * Search engine utama — gabungkan tindakan lokal + penunjang global.
     * Dipanggil dari Livewire component saat dokter input order.
     */
    public function searchOrderable(string $keyword, int $poliId, int $limit = 30): Collection
    {
        // [A] Tindakan lokal — filter by poliId dokter
        $tindakan = MasterTindakan::aktif()
            ->untukPoli($poliId)
            ->where('nama', 'like', "%{$keyword}%")
            ->select('id', 'kode', 'nama', 'tarif', 'tarif_bpjs', 'kategori')
            ->limit($limit)
            ->get()
            ->map(fn ($t) => array_merge($t->toArray(), ['sumber' => 'tindakan']));

        // [B] Penunjang global — Lab & Radiologi
        $penunjang = ItemPenunjang::aktif()
            ->whereIn('kategori', ['lab', 'radiologi'])
            ->where('nama', 'like', "%{$keyword}%")
            ->select('id', 'kode', 'nama', 'tarif', 'tarif_bpjs', 'kategori', 'satuan_waktu')
            ->limit($limit)
            ->get()
            ->map(fn ($p) => array_merge($p->toArray(), ['sumber' => 'penunjang']));

        return $tindakan->merge($penunjang)->sortBy('nama')->values();
    }

    public function getTindakanByPoli(int $poliId): Collection
    {
        return MasterTindakan::aktif()
            ->untukPoli($poliId)
            ->orderBy('nama')
            ->get();
    }

    public function getAllPenunjang(?string $kategori = null): Collection
    {
        return ItemPenunjang::aktif()
            ->when($kategori, fn ($q, $k) => $q->where('kategori', $k),
                             fn ($q)      => $q->whereIn('kategori', ['lab', 'radiologi']))
            ->orderBy('nama')
            ->get();
    }

    public function getMappingByTindakan(int $tindakanId): Collection
    {
        return MasterTindakan::with('poli:id,nama,kode')
            ->findOrFail($tindakanId)
            ->poli;
    }

    public function syncMappingPoli(int $tindakanId, array $poliIds): void
    {
        MasterTindakan::findOrFail($tindakanId)->poli()->sync($poliIds);
    }
}
```

---

## 7. Service Layer

```php
// app/Services/MasterdataService.php

namespace App\Services;

use App\Models\MasterTindakan;
use App\Models\ItemPenunjang;
use App\Models\PeralatanMedis;
use App\Models\PenggunaanAlat;
use App\Models\PermintaanPenunjang;
use App\Repositories\MasterdataRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MasterdataService
{
    public function __construct(
        private readonly MasterdataRepository $repo
    ) {}

    // ── Search Engine ────────────────────────────────────────

    public function searchOrderable(string $keyword, int $poliId)
    {
        if (! $poliId) {
            throw ValidationException::withMessages([
                'poli_id' => 'Poli dokter tidak ditemukan. Pastikan profil dokter sudah dikonfigurasi.',
            ]);
        }
        return $this->repo->searchOrderable($keyword, $poliId);
    }

    // ── Master Tindakan ──────────────────────────────────────

    public function createTindakan(array $data, array $poliIds): MasterTindakan
    {
        if (empty($poliIds)) {
            throw ValidationException::withMessages([
                'poli_ids' => 'Tindakan wajib dipetakan ke minimal satu Poli.',
            ]);
        }

        return DB::transaction(function () use ($data, $poliIds) {
            $tindakan = MasterTindakan::create($data);
            $tindakan->poli()->sync($poliIds);

            activity('masterdata')
                ->performedOn($tindakan)
                ->causedBy(auth()->user())
                ->withProperties(['poli_count' => count($poliIds)])
                ->log('Tindakan baru dibuat');

            return $tindakan;
        });
    }

    public function updateTindakan(int $id, array $data, array $poliIds): MasterTindakan
    {
        return DB::transaction(function () use ($id, $data, $poliIds) {
            $tindakan = MasterTindakan::findOrFail($id);
            $tindakan->update($data);
            $tindakan->poli()->sync($poliIds);

            activity('masterdata')
                ->performedOn($tindakan)
                ->causedBy(auth()->user())
                ->log('Tindakan diupdate');

            return $tindakan->fresh('poli');
        });
    }

    // ── Item Penunjang ───────────────────────────────────────

    public function createPenunjang(array $data): ItemPenunjang
    {
        $item = ItemPenunjang::create($data);

        activity('masterdata')
            ->performedOn($item)
            ->causedBy(auth()->user())
            ->log("Item penunjang ({$item->kategori}) ditambahkan");

        return $item;
    }

    public function updatePenunjang(int $id, array $data): ItemPenunjang
    {
        $item = ItemPenunjang::findOrFail($id);
        $item->update($data);
        return $item;
    }

    // ── Peralatan Medis ──────────────────────────────────────

    public function createPeralatan(array $data): PeralatanMedis
    {
        return PeralatanMedis::create($data);
    }

    public function pakaiAlat(int $peralatanId, int $poliId, ?int $kunjunganId = null): PenggunaanAlat
    {
        $alat = PeralatanMedis::findOrFail($peralatanId);

        match ($alat->status) {
            'digunakan'   => throw ValidationException::withMessages([
                'peralatan' => "Alat sedang digunakan di poli lain.",
            ]),
            'maintenance' => throw ValidationException::withMessages([
                'peralatan' => "Alat sedang dalam maintenance.",
            ]),
            'rusak'       => throw ValidationException::withMessages([
                'peralatan' => "Alat dalam kondisi rusak dan tidak dapat digunakan.",
            ]),
            default       => null,
        };

        return DB::transaction(function () use ($alat, $peralatanId, $poliId, $kunjunganId) {
            $alat->update(['status' => 'digunakan', 'poli_terakhir_id' => $poliId]);

            return PenggunaanAlat::create([
                'peralatan_id'  => $peralatanId,
                'poli_id'       => $poliId,
                'kunjungan_id'  => $kunjunganId,
                'dipakai_oleh'  => auth()->user()->nama,
            ]);
        });
    }

    public function selesaiPakaiAlat(int $penggunaanId): PenggunaanAlat
    {
        return DB::transaction(function () use ($penggunaanId) {
            $penggunaan = PenggunaanAlat::with('peralatan')->findOrFail($penggunaanId);
            $penggunaan->update(['waktu_selesai' => now()]);
            $penggunaan->peralatan->update(['status' => 'tersedia']);
            return $penggunaan;
        });
    }

    // ── Permintaan Penunjang ─────────────────────────────────

    public function buatPermintaan(int $kunjunganId, array $items): void
    {
        $data = collect($items)->map(fn ($item) => [
            'kunjungan_id'      => $kunjunganId,
            'item_penunjang_id' => $item['id'],
            'jumlah'            => $item['jumlah'] ?? 1,
            'catatan'           => $item['catatan'] ?? null,
            'status'            => 'dipesan',
            'created_at'        => now(),
            'updated_at'        => now(),
        ])->toArray();

        PermintaanPenunjang::insert($data);
    }
}
```

---

## 8. Form Request Validation

### 8.1 StoreTindakanRequest

```php
// app/Http/Requests/Masterdata/StoreTindakanRequest.php

namespace App\Http\Requests\Masterdata;

use Illuminate\Foundation\Http\FormRequest;

class StoreTindakanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('masterdata.create');
    }

    public function rules(): array
    {
        return [
            'kode'       => ['required', 'string', 'min:2', 'unique:master_tindakan,kode'],
            'nama'       => ['required', 'string', 'min:3'],
            'deskripsi'  => ['nullable', 'string'],
            'tarif'      => ['required', 'numeric', 'min:0'],
            'tarif_bpjs' => ['nullable', 'numeric', 'min:0'],
            'poli_ids'   => ['required', 'array', 'min:1'],
            'poli_ids.*' => ['integer', 'exists:poli,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'poli_ids.min' => 'Tindakan wajib dipetakan ke minimal satu Poli.',
            'kode.unique'  => 'Kode tindakan sudah digunakan.',
        ];
    }
}
```

### 8.2 StorePenunjangRequest

```php
// app/Http/Requests/Masterdata/StorePenunjangRequest.php

namespace App\Http\Requests\Masterdata;

use Illuminate\Foundation\Http\FormRequest;

class StorePenunjangRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('masterdata.create');
    }

    public function rules(): array
    {
        return [
            'kode'        => ['required', 'string', 'min:2', 'unique:item_penunjang,kode'],
            'nama'        => ['required', 'string', 'min:3'],
            'kategori'    => ['required', 'in:lab,radiologi'],
            'tarif'       => ['required', 'numeric', 'min:0'],
            'tarif_bpjs'  => ['nullable', 'numeric', 'min:0'],
            'deskripsi'   => ['nullable', 'string'],
            'satuan_waktu'=> ['nullable', 'string'],
        ];
    }
}
```

### 8.3 StorePeralatanRequest

```php
// app/Http/Requests/Masterdata/StorePeralatanRequest.php

namespace App\Http\Requests\Masterdata;

use Illuminate\Foundation\Http\FormRequest;

class StorePeralatanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('masterdata.create');
    }

    public function rules(): array
    {
        return [
            'kode'       => ['required', 'string', 'unique:peralatan_medis,kode'],
            'nama'       => ['required', 'string', 'min:3'],
            'merk'       => ['nullable', 'string'],
            'nomor_seri' => ['nullable', 'string', 'unique:peralatan_medis,nomor_seri'],
            'deskripsi'  => ['nullable', 'string'],
        ];
    }
}
```

---

## 9. Livewire Components

### 9.1 TindakanTable — Kelola Master Tindakan

```php
// app/Livewire/Pengaturan/Masterdata/TindakanTable.php

namespace App\Livewire\Pengaturan\Masterdata;

use App\Models\MasterTindakan;
use App\Services\MasterdataService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class TindakanTable extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $filterPoli = '';

    public function updatingSearch(): void   { $this->resetPage(); }
    public function updatingFilterPoli(): void { $this->resetPage(); }

    #[Computed]
    public function tindakan()
    {
        return MasterTindakan::with('poli:id,nama')
            ->where('kategori', 'tindakan')
            ->when($this->search, fn ($q, $s) => $q->where('nama', 'like', "%{$s}%"))
            ->when($this->filterPoli, fn ($q, $p) =>
                $q->whereHas('poli', fn ($sq) => $sq->where('poli.id', $p))
            )
            ->orderBy('nama')
            ->paginate(15);
    }

    public function toggleAktif(int $id): void
    {
        $this->authorize('masterdata.edit');
        $item = MasterTindakan::findOrFail($id);
        $item->update(['is_active' => ! $item->is_active]);
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Status diupdate.']);
    }

    public function render()
    {
        return view('livewire.pengaturan.masterdata.tindakan-table');
    }
}
```

### 9.2 OrderItemSearch — Search Engine untuk Dokter

```php
// app/Livewire/Pemeriksaan/OrderItemSearch.php

namespace App\Livewire\Pemeriksaan;

use App\Services\MasterdataService;
use Livewire\Attributes\Computed;
use Livewire\Component;

class OrderItemSearch extends Component
{
    public string $search    = '';
    public int    $poliId    = 0;
    public array  $selected  = []; // Item yang sudah dipilih dokter

    public function mount(): void
    {
        // Ambil poliId dari profil dokter yang login
        $dokter = auth()->user()->dokter;
        $this->poliId = $dokter ? $dokter->poli_id : 0;
    }

    #[Computed]
    public function results()
    {
        if (strlen($this->search) < 2) return collect();

        return app(MasterdataService::class)
            ->searchOrderable($this->search, $this->poliId);
    }

    public function pilih(int $id, string $sumber): void
    {
        // Tambahkan ke daftar order
        $this->selected[] = ['id' => $id, 'sumber' => $sumber, 'jumlah' => 1];
        $this->search = '';
        $this->dispatch('item-dipilih', item: end($this->selected));
    }

    public function render()
    {
        return view('livewire.pemeriksaan.order-item-search');
    }
}
```

---

## 10. Blade Views

### Layout Pengaturan Masterdata (Tab Nav)

```blade
{{-- resources/views/pengaturan/masterdata/index.blade.php --}}
<x-app-layout>
    <x-slot name="title">Master Data Klinis</x-slot>

    <div class="page-header">
        <div>
            <h2 class="page-title">Master Data Klinis</h2>
            <p class="page-subtitle">Kelola poli, tindakan, lab, radiologi, dan peralatan medis</p>
        </div>
    </div>

    {{-- Tab Navigation --}}
    <div class="border-b border-gray-200 dark:border-gray-700 mb-6">
        <nav class="flex gap-1 -mb-px" x-data="{ tab: '{{ request()->query('tab', 'tindakan') }}' }">
            @foreach ([
                'tindakan'  => 'Tindakan',
                'lab'       => 'Laboratorium',
                'radiologi' => 'Radiologi',
                'peralatan' => 'Peralatan Medis',
                'poli'      => 'Poliklinik',
            ] as $key => $label)
            <a href="?tab={{ $key }}"
               @class([
                   'px-4 py-2.5 text-sm font-medium border-b-2 transition-colors',
                   'border-primary-600 text-primary-700 dark:text-primary-400 dark:border-primary-400'
                       => request()->query('tab', 'tindakan') === $key,
                   'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400'
                       => request()->query('tab', 'tindakan') !== $key,
               ])>
                {{ $label }}
            </a>
            @endforeach
        </nav>
    </div>

    {{-- Konten per tab --}}
    @switch(request()->query('tab', 'tindakan'))
        @case('tindakan')
            <livewire:pengaturan.masterdata.tindakan-table />
            @break
        @case('lab')
            <livewire:pengaturan.masterdata.penunjang-table :kategori="'lab'" />
            @break
        @case('radiologi')
            <livewire:pengaturan.masterdata.penunjang-table :kategori="'radiologi'" />
            @break
        @case('peralatan')
            <livewire:pengaturan.masterdata.peralatan-table />
            @break
        @case('poli')
            <livewire:pengaturan.masterdata.poli-table />
            @break
    @endswitch

</x-app-layout>
```

### Contoh Blade Livewire — Tindakan Table

```blade
{{-- resources/views/livewire/pengaturan/masterdata/tindakan-table.blade.php --}}
<div>
    {{-- Toolbar --}}
    <div class="mb-4 flex flex-col sm:flex-row gap-3 justify-between">
        <div class="flex gap-2">
            <input wire:model.live.debounce.400ms="search" type="text"
                   placeholder="Cari tindakan..."
                   class="form-input w-60 dark:bg-gray-800 dark:border-gray-600"/>

            <select wire:model.live="filterPoli"
                    class="form-select w-44 dark:bg-gray-800 dark:border-gray-600">
                <option value="">Semua Poli</option>
                @foreach (\App\Models\Poli::where('is_active', true)->orderBy('nama')->get() as $p)
                    <option value="{{ $p->id }}">{{ $p->nama }}</option>
                @endforeach
            </select>
        </div>

        @can('masterdata.create')
        <button wire:click="$dispatch('open-tindakan-form')" class="btn-primary">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Tambah Tindakan
        </button>
        @endcan
    </div>

    {{-- Tabel --}}
    <div class="table-wrapper">
        <table class="table">
            <thead>
                <tr>
                    <th>Kode</th>
                    <th>Nama Tindakan</th>
                    <th>Tarif</th>
                    <th>Tarif BPJS</th>
                    <th>Poli</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->tindakan as $item)
                <tr wire:key="tindakan-{{ $item->id }}">
                    <td class="font-mono text-xs text-gray-500">{{ $item->kode }}</td>
                    <td class="font-medium text-gray-900 dark:text-gray-100">{{ $item->nama }}</td>
                    <td>Rp {{ number_format($item->tarif, 0, ',', '.') }}</td>
                    <td class="text-gray-500">
                        {{ $item->tarif_bpjs ? 'Rp '.number_format($item->tarif_bpjs, 0, ',', '.') : '-' }}
                    </td>
                    <td>
                        <div class="flex flex-wrap gap-1">
                            @foreach ($item->poli->take(3) as $poli)
                            <span class="badge-primary text-xs">{{ $poli->kode }}</span>
                            @endforeach
                            @if ($item->poli->count() > 3)
                            <span class="badge-gray text-xs">+{{ $item->poli->count() - 3 }}</span>
                            @endif
                        </div>
                    </td>
                    <td>
                        <button wire:click="toggleAktif({{ $item->id }})"
                                @class([
                                    'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium',
                                    'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300' => $item->is_active,
                                    'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300' => !$item->is_active,
                                ])>
                            {{ $item->is_active ? 'Aktif' : 'Nonaktif' }}
                        </button>
                    </td>
                    <td>
                        <div class="flex gap-1">
                            @can('masterdata.edit')
                            <button wire:click="$dispatch('open-tindakan-edit', { id: {{ $item->id }} })"
                                    class="btn-info btn-sm">Edit</button>
                            <button wire:click="$dispatch('open-poli-mapping', { id: {{ $item->id }} })"
                                    class="btn-secondary btn-sm">Poli</button>
                            @endcan
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7">
                        <div class="empty-state">
                            <p class="empty-state-text">Tidak ada tindakan ditemukan</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $this->tindakan->links() }}</div>
</div>
```

---

## 11. Routes & RBAC

### 11.1 Routes

```php
// Tambahkan ke routes/web.php dalam group middleware(['auth', 'active'])

Route::prefix('pengaturan')->name('pengaturan.')->group(function () {

    // Master Data Klinis — hanya super_admin dan admin dengan permission
    Route::middleware('permission:masterdata.view')->group(function () {
        Route::get('/masterdata', fn () => view('pengaturan.masterdata.index'))
             ->name('masterdata');
    });
});
```

### 11.2 Tambahan Permission ke Seeder

```php
// Tambahkan ke database/seeders/RolePermissionSeeder.php

$permissions = [
    // ... permission existing ...

    // Master Data Klinis
    'masterdata.view', 'masterdata.create', 'masterdata.edit', 'masterdata.delete',

    // Penggunaan peralatan
    'peralatan.pakai',

    // Permintaan penunjang
    'penunjang.create', 'penunjang.view',
];

// Tambahkan ke role:
'admin' => [
    // ... existing ...
    'masterdata.view', 'masterdata.create', 'masterdata.edit',
],

'dokter' => [
    // ... existing ...
    'masterdata.view',       // Read only untuk search order
    'penunjang.create',      // Buat permintaan lab/rad
    'penunjang.view',
    'peralatan.pakai',       // Catat penggunaan alat
],

'perawat' => [
    // ... existing ...
    'masterdata.view',
    'peralatan.pakai',
],

'kasir' => [
    // ... existing ...
    'masterdata.view',       // Untuk kalkulasi billing
],
```

---

## 12. Struktur Folder

```
app/
├── Http/
│   └── Requests/
│       └── Masterdata/
│           ├── StoreTindakanRequest.php
│           ├── UpdateTindakanRequest.php
│           ├── StorePenunjangRequest.php
│           ├── UpdatePenunjangRequest.php
│           └── StorePeralatanRequest.php
│
├── Livewire/
│   ├── Pengaturan/
│   │   └── Masterdata/
│   │       ├── TindakanTable.php        ← list + search + filter poli
│   │       ├── TindakanForm.php         ← modal create/edit + poli selector
│   │       ├── PoliMappingModal.php     ← kelola mapping poli ↔ tindakan
│   │       ├── PenunjangTable.php       ← list lab/rad
│   │       ├── PenunjangForm.php        ← modal create/edit
│   │       ├── PeralatanTable.php       ← list + status badge
│   │       ├── PeralatanForm.php        ← modal create/edit
│   │       └── PoliTable.php           ← kelola poli
│   │
│   └── Pemeriksaan/
│       └── OrderItemSearch.php          ← search engine untuk dokter
│
├── Models/
│   ├── MasterTindakan.php  (diupdate)
│   ├── Poli.php            (diupdate)
│   ├── ItemPenunjang.php   (baru)
│   ├── PeralatanMedis.php  (baru)
│   ├── PenggunaanAlat.php  (baru)
│   └── PermintaanPenunjang.php (baru)
│
├── Repositories/
│   └── MasterdataRepository.php (baru)
│
└── Services/
    └── MasterdataService.php    (baru)

resources/views/
├── pengaturan/
│   └── masterdata/
│       └── index.blade.php              ← halaman utama + tab nav
│
└── livewire/
    └── pengaturan/
        └── masterdata/
            ├── tindakan-table.blade.php
            ├── tindakan-form.blade.php
            ├── poli-mapping-modal.blade.php
            ├── penunjang-table.blade.php
            ├── penunjang-form.blade.php
            ├── peralatan-table.blade.php
            └── peralatan-form.blade.php

database/
└── migrations/
    ├── 2026_01_02_000001_update_master_tindakan_add_v2_fields.php
    ├── 2026_01_02_000002_create_tindakan_poli_table.php
    ├── 2026_01_02_000003_create_item_penunjang_table.php
    ├── 2026_01_02_000004_create_peralatan_medis_table.php
    ├── 2026_01_02_000005_create_penggunaan_alat_table.php
    └── 2026_01_02_000006_create_permintaan_penunjang_table.php
```

---

## 13. User Stories

| ID | Persona | Skenario | Expected Behavior |
|----|---------|----------|-------------------|
| **US01** | Super Admin | Menambah Oxymeter ke katalog | Alat langsung tersedia semua poli. Status default: `tersedia` |
| **US02** | Super Admin | Menambah tindakan "Tonometri" tanpa pilih poli | Form error: *"Tindakan wajib dipetakan ke minimal satu Poli"* |
| **US03** | Super Admin | Mapping "Tonometri" ke Poli Mata | Tindakan hanya muncul bagi dokter Poli Mata |
| **US04** | Dokter Poli Mata | Membuka search order | Tampil: tindakan Poli Mata + semua Lab + semua Radiologi |
| **US05** | Dokter Poli Mata | Ketik "Darah" di search | Muncul "Darah Lengkap (Lab)", "Darah Rutin (Lab)" |
| **US06** | Dokter Poli Bedah | Membuka search order | Tindakan Poli Mata tidak terlihat |
| **US07** | Dokter | Merujuk USG Abdomen | Semua Radiologi muncul tanpa Admin perlu setup mapping |
| **US08** | Perawat | Mencatat penggunaan Nebulizer untuk Poli Anak | Status berubah `digunakan`, poli tercatat di riwayat |
| **US09** | Perawat | Mencoba pakai Nebulizer yang sedang `digunakan` | Error: *"Alat sedang digunakan di poli lain"* |
| **US10** | Super Admin | Melihat riwayat ECG Monitor | Log: tanggal, poli, kunjungan, durasi penggunaan |

---

## 14. Seed Data Awal

```php
// Tambahkan ke database/seeders/DatabaseSeeder.php

$this->call([
    RolePermissionSeeder::class,
    UserSeeder::class,
    KlinikSeeder::class,
    PoliSeeder::class,    // ← update dengan kolom lantai & deskripsi
    ObatSeeder::class,
    MasterdataV2Seeder::class,  // ← tambahkan seeder baru
]);
```

```php
// database/seeders/MasterdataV2Seeder.php

namespace Database\Seeders;

use App\Models\ItemPenunjang;
use App\Models\MasterTindakan;
use App\Models\PeralatanMedis;
use App\Models\Poli;
use Illuminate\Database\Seeder;

class MasterdataV2Seeder extends Seeder
{
    public function run(): void
    {
        $poliMap = Poli::pluck('id', 'kode')->toArray();

        // ── Tindakan Lokal (dengan mapping poli) ────────────
        $tindakan = [
            ['kode'=>'T001','nama'=>'Pemeriksaan Fisik Umum','tarif'=>50000, 'poli'=>['UMUM','ANAK','KIA']],
            ['kode'=>'T002','nama'=>'Pemeriksaan Visus',     'tarif'=>75000, 'poli'=>['MATA']],
            ['kode'=>'T003','nama'=>'Tonometri',             'tarif'=>100000,'poli'=>['MATA']],
            ['kode'=>'T004','nama'=>'Ekstraksi Gigi',        'tarif'=>150000,'poli'=>['GIGI']],
            ['kode'=>'T005','nama'=>'Tambal Gigi Composite', 'tarif'=>200000,'poli'=>['GIGI']],
            ['kode'=>'T006','nama'=>'Pemasangan Infus',      'tarif'=>85000, 'poli'=>['UMUM','BEDAH','ANAK','KIA']],
            ['kode'=>'T007','nama'=>'Jahit Luka',            'tarif'=>120000,'poli'=>['UMUM','BEDAH']],
            ['kode'=>'T008','nama'=>'Sirkumsisi',            'tarif'=>500000,'poli'=>['BEDAH']],
            ['kode'=>'T009','nama'=>'USG Obstetri',          'tarif'=>250000,'poli'=>['KIA']],
            ['kode'=>'T010','nama'=>'Nebulisasi',            'tarif'=>60000, 'poli'=>['UMUM','ANAK']],
        ];

        foreach ($tindakan as $t) {
            $poliKodes = $t['poli'];
            unset($t['poli']);

            $item = MasterTindakan::firstOrCreate(
                ['kode' => $t['kode']],
                array_merge($t, ['kategori' => 'tindakan'])
            );

            $poliIds = collect($poliKodes)
                ->map(fn ($k) => $poliMap[$k] ?? null)
                ->filter()
                ->toArray();

            $item->poli()->syncWithoutDetaching($poliIds);
        }

        $this->command->info('✓ Seeded ' . count($tindakan) . ' Tindakan + mapping poli');

        // ── Item Lab (Global) ────────────────────────────────
        $labs = [
            ['kode'=>'L001','nama'=>'Darah Lengkap',       'tarif'=>85000,  'satuan_waktu'=>'2 jam'],
            ['kode'=>'L002','nama'=>'Urinalisis',          'tarif'=>45000,  'satuan_waktu'=>'1 jam'],
            ['kode'=>'L003','nama'=>'Gula Darah Sewaktu',  'tarif'=>30000,  'satuan_waktu'=>'30 menit'],
            ['kode'=>'L004','nama'=>'HbA1C',               'tarif'=>120000, 'satuan_waktu'=>'3 jam'],
            ['kode'=>'L005','nama'=>'Fungsi Ginjal',       'tarif'=>95000,  'satuan_waktu'=>'2 jam'],
            ['kode'=>'L006','nama'=>'Fungsi Hati SGOT/SGPT','tarif'=>95000, 'satuan_waktu'=>'2 jam'],
            ['kode'=>'L007','nama'=>'Profil Lipid',        'tarif'=>110000, 'satuan_waktu'=>'3 jam'],
            ['kode'=>'L008','nama'=>'Kultur Darah',        'tarif'=>250000, 'satuan_waktu'=>'5 hari kerja'],
        ];

        foreach ($labs as $l) {
            ItemPenunjang::firstOrCreate(['kode' => $l['kode']], array_merge($l, ['kategori' => 'lab']));
        }

        $this->command->info('✓ Seeded ' . count($labs) . ' Item Lab');

        // ── Item Radiologi (Global) ──────────────────────────
        $rads = [
            ['kode'=>'R001','nama'=>'Foto Thorax PA',     'tarif'=>150000,'satuan_waktu'=>'1 jam'],
            ['kode'=>'R002','nama'=>'USG Abdomen',        'tarif'=>300000,'satuan_waktu'=>'30 menit'],
            ['kode'=>'R003','nama'=>'CT-Scan Kepala',     'tarif'=>900000,'satuan_waktu'=>'2 jam'],
            ['kode'=>'R004','nama'=>'MRI Lumbal',         'tarif'=>2500000,'satuan_waktu'=>'2 jam'],
            ['kode'=>'R005','nama'=>'EKG 12 Lead',        'tarif'=>120000,'satuan_waktu'=>'30 menit'],
            ['kode'=>'R006','nama'=>'Foto Panoramik Gigi','tarif'=>200000,'satuan_waktu'=>'30 menit'],
        ];

        foreach ($rads as $r) {
            ItemPenunjang::firstOrCreate(['kode' => $r['kode']], array_merge($r, ['kategori' => 'radiologi']));
        }

        $this->command->info('✓ Seeded ' . count($rads) . ' Item Radiologi');

        // ── Peralatan Medis (Global) ─────────────────────────
        $peralatan = [
            ['kode'=>'A001','nama'=>'Oxymeter',           'merk'=>'Contec',      'nomor_seri'=>'CX8001'],
            ['kode'=>'A002','nama'=>'Tensimeter Digital',  'merk'=>'Omron',       'nomor_seri'=>'OM7200'],
            ['kode'=>'A003','nama'=>'Nebulizer',           'merk'=>'Omron',       'nomor_seri'=>'NEB001'],
            ['kode'=>'A004','nama'=>'ECG Monitor 12 Lead', 'merk'=>'GE Healthcare','nomor_seri'=>'GE1200'],
            ['kode'=>'A005','nama'=>'Glucometer',          'merk'=>'Accu-Check',  'nomor_seri'=>'AC4500'],
            ['kode'=>'A006','nama'=>'Infusion Pump',       'merk'=>'Terumo',      'nomor_seri'=>'TE2200'],
        ];

        foreach ($peralatan as $p) {
            PeralatanMedis::firstOrCreate(['kode' => $p['kode']], $p);
        }

        $this->command->info('✓ Seeded ' . count($peralatan) . ' Peralatan Medis');
        $this->command->info('✅ MasterdataV2Seeder selesai.');
    }
}
```

---

## Appendix — Diagram Relasi Antar Model (Eloquent)

```
User (dokter)
    └── Dokter::poli_id ──────────────────────────────► Poli
                                                          │
                                                 tindakan_poli (pivot)
                                                          │
                                                 MasterTindakan (kategori=tindakan)
                                                          │
                                                 Tindakan (transaksi dari kunjungan)

Kunjungan ─────────────────────────────────────────────────┤
    ├── tindakan[]          → Tindakan → MasterTindakan
    └── permintaan_penunjang[] → PermintaanPenunjang → ItemPenunjang (lab/radiologi)

PeralatanMedis (global)
    └── penggunaan_alat[] ──────────────────────────────► Poli
                          └──────────────────────────────► Kunjungan (nullable)

SEARCH FLOW DOKTER (Livewire wire:model):
auth()->user()->dokter->poli_id
    → [A] MasterTindakan::untukPoli($poliId)->where('nama', like)
    → [B] ItemPenunjang::whereIn('kategori', ['lab','radiologi'])->where('nama', like)
    = Collection merged, sorted by nama
```

---

*masterdata_v2.md — v2.3-r2 · Laravel 12 + Livewire 3 · Living document*

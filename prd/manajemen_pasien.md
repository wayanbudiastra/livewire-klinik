# Manajemen Data Pasien — Patient Setup
**Versi:** 2.0.0 (Laravel 12 + Livewire 3)
**Tech Stack:** Laravel 12 · Livewire 3 · Spatie Permission · Eloquent ORM · Tailwind CSS
**Depends On:** `setup_awal.md` · `masterdata_v1.md v2.0.0` · `masterdata_v2.md v2.3-r2`
**Changelog v2.0.0:** Rewrite penuh dari Next.js/Prisma/shadcn ke Laravel 12 + Livewire 3

---

## Daftar Isi

1. [Konteks & Posisi dalam Project](#1-konteks--posisi-dalam-project)
2. [Field Pasien & Aturan Mandatori](#2-field-pasien--aturan-mandatori)
3. [Format Nomor Rekam Medis — Auto-Generate](#3-format-nomor-rekam-medis--auto-generate)
4. [Tipe Pasien — WNI & WNA](#4-tipe-pasien--wni--wna)
5. [Kontak Darurat](#5-kontak-darurat)
6. [Migration & Model](#6-migration--model)
7. [Repository Layer](#7-repository-layer)
8. [Service Layer](#8-service-layer)
9. [Form Request Validation](#9-form-request-validation)
10. [Livewire Components](#10-livewire-components)
11. [Blade Views](#11-blade-views)
12. [Routes & RBAC](#12-routes--rbac)
13. [Struktur Folder](#13-struktur-folder)
14. [User Stories & Business Rules](#14-user-stories--business-rules)
15. [Seed Data Awal](#15-seed-data-awal)

---

## 1. Konteks & Posisi dalam Project

```
setup_awal.md              → Tech stack, migration dasar, seeder awal
    ↓
masterdata_v1.md           → User management, RBAC, middleware
    ↓
masterdata_v2.md           → Poli, Tindakan, Lab, Radiologi, Peralatan
    ↓
masterdata_v3.md           → Data Dokter, Mapping Poli, Sharing Fee, Jadwal
    ↓
manajemen_pasien.md (ini)  → Extend model Pasien:
                             - Field mandatori baru (tempat lahir, tipe WNI/WNA)
                             - NoRM auto-generate (RM-XXXXXX)
                             - Kontak Darurat (model baru, 1-to-many)
                             - Livewire CRUD lengkap
```

### Perubahan dari Model Pasien di setup_awal.md

| Field / Fitur | Setup Awal | manajemen_pasien v2.0 |
|---|---|---|
| `tempat_lahir` | ❌ Belum ada | ✅ Mandatori |
| `tipe_pasien` (WNI/WNA) | ❌ Belum ada | ✅ Mandatori |
| `nama` | ✅ Ada | ✅ + validasi min 3 karakter |
| `tanggal_lahir` | ✅ Ada | ✅ + guard masa depan |
| `alamat` | ❌ nullable | ✅ **Mandatori** |
| `telepon` | ❌ nullable | ✅ **Mandatori** |
| `nomor_rm` | ✅ Manual | ✅ **Auto-generate** `RM-XXXXXX` |
| `golongan_darah` | `String?` bebas | ✅ Enum terstruktur |
| `KontakDarurat` | ❌ Belum ada | ✅ Model baru, 1-to-many |
| NIK wajib jika WNI | ❌ | ✅ Validasi kondisional |
| No. Paspor wajib jika WNA | ❌ | ✅ Field baru + validasi |

---

## 2. Field Pasien & Aturan Mandatori

### 2.1 Tabel Field Lengkap

| Field | Tipe DB | Mandatori | Keterangan |
|-------|---------|:---------:|-----------|
| `nomor_rm` | `string unique` | ✅ auto | Auto-generate `RM-XXXXXX` · read-only setelah dibuat |
| `nama` | `string` | ✅ | Min 3 · hanya huruf, spasi, tanda hubung |
| `tempat_lahir` | `string` | ✅ | Kota / kabupaten tempat lahir |
| `tanggal_lahir` | `date` | ✅ | Tidak boleh masa depan · min 1875-01-01 |
| `jenis_kelamin` | `enum(L,P)` | ✅ | `L` = Laki-laki · `P` = Perempuan |
| `tipe_pasien` | `enum(WNI,WNA)` | ✅ | Tidak bisa diubah setelah simpan |
| `nik` | `string nullable unique` | ✅ jika WNI | 16 digit angka |
| `no_paspor` | `string nullable unique` | ✅ jika WNA | 5–20 karakter · auto-uppercase |
| `negara_asal` | `string nullable` | ✅ jika WNA | Nama negara asal |
| `alamat` | `text` | ✅ | Min 10 karakter |
| `telepon` | `string` | ✅ | Format `08xx` / `+62xx` |
| `email` | `string nullable` | ❌ | Format email valid jika diisi |
| `golongan_darah` | `enum nullable` | ❌ | A · B · AB · O · tidak_diketahui |
| `alergi` | `text nullable` | ❌ | Teks bebas · max 1000 karakter |
| `no_bpjs` | `string nullable unique` | ❌ | 13 digit · hanya WNI |
| `no_asuransi` | `string nullable` | ❌ | Nomor asuransi swasta |
| `foto` | `string nullable` | ❌ | Path file foto |
| `is_active` | `boolean` | auto | Default `true` |

### 2.2 Aturan Validasi

```
NAMA
  ✓ Min 3 karakter, max 100
  ✓ Regex: /^[a-zA-Z\s\-'.]+$/

TANGGAL LAHIR
  ✓ Tidak boleh masa depan (before_or_equal:today)
  ✓ Tidak boleh sebelum 1875-01-01

NIK (wajib jika WNI)
  ✓ Tepat 16 digit angka · unik di database

NO. PASPOR (wajib jika WNA)
  ✓ 5–20 karakter alphanumeric · unik · auto-uppercase

TELEPON
  ✓ Regex: /^(\+62|62|0)[0-9]{8,13}$/
  ✓ Min 10 digit, max 15 digit

NOMOR BPJS (jika diisi)
  ✓ Tepat 13 digit angka · tidak duplikat
  ✓ Hanya untuk tipe_pasien = WNI
```

---

## 3. Format Nomor Rekam Medis — Auto-Generate

### Format

```
RM-XXXXXX    ← prefix tetap + 6 digit sequential

Contoh:
  RM-000001  pasien pertama
  RM-000042
  RM-001234
  RM-999999  batas maksimum
```

### Implementasi — Service Method

```php
// app/Services/PasienService.php

public function generateNomorRM(): string
{
    return DB::transaction(function () {
        // Lock untuk mencegah concurrent insert
        $last = Pasien::lockForUpdate()
            ->orderByDesc('nomor_rm')
            ->value('nomor_rm');

        $nextNum = 1;
        if ($last && preg_match('/^RM-(\d{6})$/', $last, $m)) {
            $nextNum = (int) $m[1] + 1;
        }

        if ($nextNum > 999_999) {
            throw new \RuntimeException('Nomor RM mencapai batas RM-999999.');
        }

        $nomor = 'RM-' . str_pad($nextNum, 6, '0', STR_PAD_LEFT);

        // Safeguard duplikat (race condition)
        if (Pasien::where('nomor_rm', $nomor)->exists()) {
            $nomor = 'RM-' . str_pad($nextNum + 1, 6, '0', STR_PAD_LEFT);
        }

        return $nomor;
    });
}
```

### Aturan Bisnis NoRM

| Aturan | Keterangan |
|--------|-----------|
| Auto-generate | Dibuat sistem saat `create`, tidak bisa diinput manual |
| Unik | Constraint `unique` di database |
| Immutable | Setelah dibuat, `nomor_rm` read-only selamanya |
| Di kartu pasien | Dicetak beserta QR Code untuk scan cepat |

---

## 4. Tipe Pasien — WNI & WNA

### Perbedaan Field per Tipe

```
tipe_pasien = WNI                     tipe_pasien = WNA
─────────────────────────────────     ─────────────────────────────────
nik (16 digit)     → WAJIB            no_paspor          → WAJIB
no_bpjs            → opsional         negara_asal        → WAJIB
no_paspor          → disembunyikan    no_asuransi swasta → opsional
negara_asal        → disembunyikan    nik                → disembunyikan
                                      no_bpjs            → disembunyikan
```

### Conditional Rendering di Livewire

```php
// app/Livewire/Pasien/PasienForm.php

public string $tipe_pasien = 'WNI';

// Di blade, gunakan @if untuk conditional fields:
```

```blade
{{-- Kondisional berdasarkan tipe_pasien --}}
@if ($tipe_pasien === 'WNI')
    {{-- NIK + BPJS --}}
    <x-form.input wire:model="nik" label="NIK" name="nik" required />
    <x-form.input wire:model="no_bpjs" label="No. BPJS" name="no_bpjs" />
@elseif ($tipe_pasien === 'WNA')
    {{-- Paspor + Negara --}}
    <x-form.input wire:model="no_paspor" label="No. Paspor" name="no_paspor" required />
    <x-form.input wire:model="negara_asal" label="Negara Asal" name="negara_asal" required />
    <x-form.input wire:model="no_asuransi" label="No. Asuransi" name="no_asuransi" />
@endif
```

---

## 5. Kontak Darurat

### Field

| Field | Tipe | Mandatori | Keterangan |
|-------|------|:---------:|-----------|
| `nama` | `string` | ✅ | Min 3 karakter |
| `nomor_hp` | `string` | ✅ | Format telepon valid · ≠ nomor pasien |
| `hubungan` | `enum` | ✅ | 15 pilihan |
| `alamat` | `string nullable` | ❌ | Opsional |
| `is_primary` | `boolean` | auto | Tepat 1 primary per pasien |

### Enum Hubungan

```php
// Nilai enum di kolom `hubungan`:
suami | istri | ayah | ibu | anak | kakak | adik |
kakek | nenek | paman | bibi | keponakan |
teman | rekan_kerja | lainnya
```

### Aturan Bisnis

```
✓ Pasien bisa punya banyak kontak darurat
✓ Tepat 1 kontak harus is_primary = true
✓ Jika hanya 1 kontak → otomatis is_primary
✓ Jika kontak primary dihapus → kontak pertama tersisa jadi primary
✗ Nomor HP kontak tidak boleh sama dengan nomor HP pasien
```

---

## 6. Migration & Model

### 6.1 Update Tabel `pasien` (addendum dari setup_awal.md)

```php
// database/migrations/2026_01_04_000001_update_pasien_add_v2_fields.php

Schema::table('pasien', function (Blueprint $table) {
    $table->string('tempat_lahir')->after('nama');
    $table->enum('tipe_pasien', ['WNI', 'WNA'])->default('WNI')->after('jenis_kelamin');
    $table->string('no_paspor')->nullable()->unique()->after('nik');
    $table->string('negara_asal')->nullable()->after('no_paspor');
    $table->string('no_asuransi')->nullable()->after('no_bpjs');
    $table->boolean('is_active')->default(true)->after('no_asuransi');
    $table->softDeletes()->after('updated_at');

    // Ubah alamat & telepon menjadi NOT NULL
    // (jalankan UPDATE dahulu jika ada data existing)
    $table->string('alamat')->nullable(false)->change();
    $table->string('telepon')->nullable(false)->change();

    // Ubah golongan_darah ke enum
    $table->enum('golongan_darah', ['A','B','AB','O','tidak_diketahui'])
          ->nullable()->change();
});
```

### 6.2 Tabel Kontak Darurat (baru)

```php
// database/migrations/2026_01_04_000002_create_kontak_darurat_table.php

Schema::create('kontak_darurat', function (Blueprint $table) {
    $table->id();
    $table->foreignId('pasien_id')->constrained('pasien')->onDelete('cascade');
    $table->string('nama');
    $table->string('nomor_hp');
    $table->enum('hubungan', [
        'suami','istri','ayah','ibu','anak','kakak','adik',
        'kakek','nenek','paman','bibi','keponakan',
        'teman','rekan_kerja','lainnya',
    ]);
    $table->string('alamat')->nullable();
    $table->boolean('is_primary')->default(false);
    $table->timestamps();
});
```

### 6.3 Model Pasien (diupdate)

```php
// app/Models/Pasien.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pasien extends Model
{
    use SoftDeletes;

    protected $table = 'pasien';

    protected $fillable = [
        'user_id', 'nomor_rm', 'nik', 'no_paspor', 'negara_asal',
        'nama', 'tempat_lahir', 'tanggal_lahir',
        'jenis_kelamin', 'tipe_pasien',
        'alamat', 'telepon', 'email',
        'golongan_darah', 'alergi',
        'no_bpjs', 'no_asuransi', 'foto', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_lahir' => 'date',
            'is_active'     => 'boolean',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function kontakDarurat()
    {
        return $this->hasMany(KontakDarurat::class)
                    ->orderByDesc('is_primary')
                    ->orderBy('created_at');
    }

    public function kontakPrimary()
    {
        return $this->hasOne(KontakDarurat::class)->where('is_primary', true);
    }

    public function kunjungan()
    {
        return $this->hasMany(Kunjungan::class)->latest('tanggal');
    }

    // ── Scopes ──────────────────────────────────────────────

    public function scopeAktif($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('nama',     'like', "%{$term}%")
              ->orWhere('nomor_rm', 'like', "%{$term}%")
              ->orWhere('nik',      'like', "%{$term}%")
              ->orWhere('no_paspor','like', "%{$term}%")
              ->orWhere('telepon',  'like', "%{$term}%");
        });
    }

    // ── Helpers ──────────────────────────────────────────────

    public function getUmurAttribute(): int
    {
        return $this->tanggal_lahir->age;
    }

    public function getJenisKelaminLabelAttribute(): string
    {
        return $this->jenis_kelamin === 'L' ? 'Laki-laki' : 'Perempuan';
    }

    public static function getHubunganOptions(): array
    {
        return [
            'suami'       => 'Suami',
            'istri'       => 'Istri',
            'ayah'        => 'Ayah',
            'ibu'         => 'Ibu',
            'anak'        => 'Anak',
            'kakak'       => 'Kakak',
            'adik'        => 'Adik',
            'kakek'       => 'Kakek',
            'nenek'       => 'Nenek',
            'paman'       => 'Paman',
            'bibi'        => 'Bibi',
            'keponakan'   => 'Keponakan',
            'teman'       => 'Teman',
            'rekan_kerja' => 'Rekan Kerja',
            'lainnya'     => 'Lainnya',
        ];
    }
}
```

### 6.4 Model KontakDarurat (baru)

```php
// app/Models/KontakDarurat.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KontakDarurat extends Model
{
    protected $table = 'kontak_darurat';

    protected $fillable = [
        'pasien_id', 'nama', 'nomor_hp',
        'hubungan', 'alamat', 'is_primary',
    ];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }

    public function pasien()
    {
        return $this->belongsTo(Pasien::class);
    }
}
```

---

## 7. Repository Layer

```php
// app/Repositories/PasienRepository.php

namespace App\Repositories;

use App\Models\KontakDarurat;
use App\Models\Pasien;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class PasienRepository
{
    public function paginate(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        return Pasien::with('kontakPrimary')
            ->when($filters['search']      ?? null, fn ($q, $s) => $q->search($s))
            ->when($filters['tipe_pasien'] ?? null, fn ($q, $t) => $q->where('tipe_pasien', $t))
            ->when(isset($filters['is_active']),
                fn ($q) => $q->where('is_active', $filters['is_active']))
            ->orderBy($filters['sort_by']  ?? 'created_at', $filters['sort_dir'] ?? 'desc')
            ->paginate($perPage);
    }

    public function findById(int $id): ?Pasien
    {
        return Pasien::with([
            'kontakDarurat',
            'kunjungan' => fn ($q) => $q->with(['poli:id,nama', 'dokter.user:id,nama'])->take(5),
        ])->find($id);
    }

    public function findByNomorRM(string $nomorRM): ?Pasien
    {
        return Pasien::where('nomor_rm', $nomorRM)->first();
    }

    public function create(array $data, array $kontakList = []): Pasien
    {
        return DB::transaction(function () use ($data, $kontakList) {
            $pasien = Pasien::create($data);

            if (! empty($kontakList)) {
                // Jika hanya 1 kontak → auto primary
                if (count($kontakList) === 1) {
                    $kontakList[0]['is_primary'] = true;
                }
                $pasien->kontakDarurat()->createMany(
                    array_map(fn ($k) => array_merge($k, ['pasien_id' => $pasien->id]), $kontakList)
                );
            }

            return $pasien->load('kontakDarurat');
        });
    }

    public function update(int $id, array $data, ?array $kontakList = null): Pasien
    {
        return DB::transaction(function () use ($id, $data, $kontakList) {
            $pasien = Pasien::findOrFail($id);
            $pasien->update($data);

            if ($kontakList !== null) {
                $pasien->kontakDarurat()->delete();
                if (! empty($kontakList)) {
                    if (count($kontakList) === 1) {
                        $kontakList[0]['is_primary'] = true;
                    }
                    $pasien->kontakDarurat()->createMany($kontakList);
                }
            }

            return $pasien->fresh('kontakDarurat');
        });
    }

    public function toggleActive(int $id, bool $state): Pasien
    {
        $pasien = Pasien::findOrFail($id);
        $pasien->update(['is_active' => $state]);
        return $pasien;
    }

    // Kontak Darurat CRUD

    public function addKontak(int $pasienId, array $data): KontakDarurat
    {
        return DB::transaction(function () use ($pasienId, $data) {
            // Jika kontak baru is_primary, reset yang lama
            if ($data['is_primary'] ?? false) {
                KontakDarurat::where('pasien_id', $pasienId)
                              ->update(['is_primary' => false]);
            }
            // Jika belum ada kontak sama sekali → auto primary
            $count = KontakDarurat::where('pasien_id', $pasienId)->count();
            if ($count === 0) $data['is_primary'] = true;

            return KontakDarurat::create(array_merge($data, ['pasien_id' => $pasienId]));
        });
    }

    public function deleteKontak(int $kontakId): void
    {
        DB::transaction(function () use ($kontakId) {
            $kontak = KontakDarurat::findOrFail($kontakId);
            $wasPrimary = $kontak->is_primary;
            $pasienId   = $kontak->pasien_id;
            $kontak->delete();

            // Jika primary dihapus → set kontak pertama sebagai primary
            if ($wasPrimary) {
                KontakDarurat::where('pasien_id', $pasienId)
                              ->oldest()
                              ->first()
                             ?->update(['is_primary' => true]);
            }
        });
    }

    public function setPrimaryKontak(int $kontakId): void
    {
        $kontak = KontakDarurat::findOrFail($kontakId);
        KontakDarurat::where('pasien_id', $kontak->pasien_id)
                      ->update(['is_primary' => false]);
        $kontak->update(['is_primary' => true]);
    }
}
```

---

## 8. Service Layer

```php
// app/Services/PasienService.php

namespace App\Services;

use App\Models\Pasien;
use App\Repositories\PasienRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PasienService
{
    public function __construct(
        private readonly PasienRepository $repo
    ) {}

    public function paginate(array $filters, int $perPage = 10): LengthAwarePaginator
    {
        return $this->repo->paginate($filters, $perPage);
    }

    public function generateNomorRM(): string
    {
        return DB::transaction(function () {
            $last = Pasien::lockForUpdate()
                ->orderByDesc('nomor_rm')
                ->value('nomor_rm');

            $nextNum = 1;
            if ($last && preg_match('/^RM-(\d{6})$/', $last, $m)) {
                $nextNum = (int) $m[1] + 1;
            }

            if ($nextNum > 999_999) {
                throw new \RuntimeException('Nomor RM mencapai batas RM-999999.');
            }

            $nomor = 'RM-' . str_pad($nextNum, 6, '0', STR_PAD_LEFT);

            if (Pasien::where('nomor_rm', $nomor)->exists()) {
                $nomor = 'RM-' . str_pad($nextNum + 1, 6, '0', STR_PAD_LEFT);
            }

            return $nomor;
        });
    }

    public function create(array $data, array $kontakList = []): Pasien
    {
        // Cek NIK duplikat
        if (($data['tipe_pasien'] ?? '') === 'WNI' && ! empty($data['nik'])) {
            $dup = Pasien::where('nik', $data['nik'])->first();
            if ($dup) {
                throw ValidationException::withMessages([
                    'nik' => "NIK sudah terdaftar atas nama {$dup->nama} ({$dup->nomor_rm}).",
                ]);
            }
        }

        // Cek No. Paspor duplikat
        if (($data['tipe_pasien'] ?? '') === 'WNA' && ! empty($data['no_paspor'])) {
            $dup = Pasien::where('no_paspor', strtoupper($data['no_paspor']))->first();
            if ($dup) {
                throw ValidationException::withMessages([
                    'no_paspor' => "No. Paspor sudah terdaftar atas nama {$dup->nama} ({$dup->nomor_rm}).",
                ]);
            }
        }

        $data['nomor_rm']  = $this->generateNomorRM();
        $data['no_paspor'] = isset($data['no_paspor'])
            ? strtoupper($data['no_paspor']) : null;

        $pasien = $this->repo->create($data, $kontakList);

        activity('pasien')
            ->performedOn($pasien)
            ->causedBy(auth()->user())
            ->withProperties(['nomor_rm' => $pasien->nomor_rm])
            ->log('Pasien baru didaftarkan');

        return $pasien;
    }

    public function update(int $id, array $data, ?array $kontakList = null): Pasien
    {
        $existing = Pasien::findOrFail($id);

        // Cek NIK duplikat (kecuali diri sendiri)
        if (! empty($data['nik']) && $data['nik'] !== $existing->nik) {
            $dup = Pasien::where('nik', $data['nik'])->where('id', '!=', $id)->first();
            if ($dup) {
                throw ValidationException::withMessages([
                    'nik' => "NIK sudah digunakan pasien lain ({$dup->nomor_rm}).",
                ]);
            }
        }

        $pasien = $this->repo->update($id, $data, $kontakList);

        activity('pasien')
            ->performedOn($pasien)
            ->causedBy(auth()->user())
            ->log('Data pasien diupdate');

        return $pasien;
    }

    public function toggleActive(int $id, bool $state): Pasien
    {
        $pasien = $this->repo->toggleActive($id, $state);

        activity('pasien')
            ->performedOn($pasien)
            ->causedBy(auth()->user())
            ->log($state ? 'Pasien diaktifkan' : 'Pasien dinonaktifkan');

        return $pasien;
    }
}
```

---

## 9. Form Request Validation

### 9.1 StorePasienRequest

```php
// app/Http/Requests/Pasien/StorePasienRequest.php

namespace App\Http\Requests\Pasien;

use Illuminate\Foundation\Http\FormRequest;

class StorePasienRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('pasien.create');
    }

    public function rules(): array
    {
        return [
            // Identitas utama
            'nama'         => ['required', 'string', 'min:3', 'max:100',
                               'regex:/^[a-zA-Z\s\-\'.]+$/'],
            'tempat_lahir' => ['required', 'string', 'min:2', 'max:100'],
            'tanggal_lahir'=> ['required', 'date', 'before_or_equal:today', 'after:1874-12-31'],
            'jenis_kelamin'=> ['required', 'in:L,P'],
            'tipe_pasien'  => ['required', 'in:WNI,WNA'],

            // Kondisional WNI
            'nik'          => ['nullable', 'string', 'size:16', 'regex:/^\d{16}$/',
                               'unique:pasien,nik'],
            'no_bpjs'      => ['nullable', 'string', 'size:13', 'regex:/^\d{13}$/',
                               'unique:pasien,no_bpjs'],

            // Kondisional WNA
            'no_paspor'    => ['nullable', 'string', 'min:5', 'max:20',
                               'regex:/^[A-Za-z0-9]+$/', 'unique:pasien,no_paspor'],
            'negara_asal'  => ['nullable', 'string', 'max:100'],

            // Kontak mandatori
            'alamat'       => ['required', 'string', 'min:10', 'max:500'],
            'telepon'      => ['required', 'string', 'regex:/^(\+62|62|0)[0-9]{8,13}$/'],

            // Opsional
            'email'        => ['nullable', 'email', 'max:255'],
            'golongan_darah'=>['nullable', 'in:A,B,AB,O,tidak_diketahui'],
            'alergi'       => ['nullable', 'string', 'max:1000'],
            'no_asuransi'  => ['nullable', 'string', 'max:50'],

            // Kontak darurat
            'kontak.*'            => ['array'],
            'kontak.*.nama'       => ['required', 'string', 'min:3'],
            'kontak.*.nomor_hp'   => ['required', 'string',
                                      'regex:/^(\+62|62|0)[0-9]{8,13}$/'],
            'kontak.*.hubungan'   => ['required', 'in:suami,istri,ayah,ibu,anak,kakak,adik,kakek,nenek,paman,bibi,keponakan,teman,rekan_kerja,lainnya'],
            'kontak.*.alamat'     => ['nullable', 'string'],
            'kontak.*.is_primary' => ['boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $data = $this->all();
            $tipe = $data['tipe_pasien'] ?? '';

            // WNI → NIK wajib
            if ($tipe === 'WNI' && empty($data['nik'])) {
                $v->errors()->add('nik', 'NIK wajib diisi untuk pasien WNI.');
            }

            // WNA → Paspor + Negara wajib
            if ($tipe === 'WNA') {
                if (empty($data['no_paspor'])) {
                    $v->errors()->add('no_paspor', 'No. Paspor wajib untuk pasien WNA.');
                }
                if (empty($data['negara_asal'])) {
                    $v->errors()->add('negara_asal', 'Negara asal wajib untuk pasien WNA.');
                }
            }

            // Nomor HP kontak ≠ nomor HP pasien
            foreach ($data['kontak'] ?? [] as $i => $k) {
                if (isset($k['nomor_hp']) && $k['nomor_hp'] === ($data['telepon'] ?? '')) {
                    $v->errors()->add(
                        "kontak.{$i}.nomor_hp",
                        'Nomor HP kontak tidak boleh sama dengan nomor HP pasien.'
                    );
                }
            }

            // Max 1 primary kontak
            $primaries = collect($data['kontak'] ?? [])->where('is_primary', true)->count();
            if ($primaries > 1) {
                $v->errors()->add('kontak', 'Hanya boleh satu kontak utama (primary).');
            }
        });
    }

    public function messages(): array
    {
        return [
            'nama.regex'          => 'Nama hanya boleh huruf, spasi, dan tanda hubung.',
            'nik.size'            => 'NIK harus tepat 16 digit.',
            'nik.regex'           => 'NIK hanya boleh berisi angka.',
            'nik.unique'          => 'NIK sudah terdaftar.',
            'no_bpjs.size'        => 'Nomor BPJS harus tepat 13 digit.',
            'no_bpjs.unique'      => 'Nomor BPJS sudah terdaftar.',
            'no_paspor.regex'     => 'No. Paspor hanya boleh huruf dan angka.',
            'telepon.regex'       => 'Format telepon tidak valid (08xx / +62xx).',
            'tanggal_lahir.after' => 'Tanggal lahir tidak valid (terlalu lama).',
        ];
    }
}
```

### 9.2 UpdatePasienRequest

```php
// app/Http/Requests/Pasien/UpdatePasienRequest.php

namespace App\Http\Requests\Pasien;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePasienRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('pasien.edit');
    }

    public function rules(): array
    {
        $id = $this->input('pasien_id');

        return [
            'nama'         => ['required', 'string', 'min:3', 'max:100'],
            'tempat_lahir' => ['required', 'string', 'min:2', 'max:100'],
            'tanggal_lahir'=> ['required', 'date', 'before_or_equal:today'],
            'jenis_kelamin'=> ['required', 'in:L,P'],
            // tipe_pasien tidak bisa diubah

            'nik'          => ['nullable', 'string', 'size:16', 'regex:/^\d{16}$/',
                               Rule::unique('pasien', 'nik')->ignore($id)],
            'no_bpjs'      => ['nullable', 'string', 'size:13', 'regex:/^\d{13}$/',
                               Rule::unique('pasien', 'no_bpjs')->ignore($id)],
            'no_paspor'    => ['nullable', 'string', 'min:5', 'max:20',
                               Rule::unique('pasien', 'no_paspor')->ignore($id)],
            'negara_asal'  => ['nullable', 'string', 'max:100'],

            'alamat'       => ['required', 'string', 'min:10', 'max:500'],
            'telepon'      => ['required', 'string', 'regex:/^(\+62|62|0)[0-9]{8,13}$/'],
            'email'        => ['nullable', 'email'],
            'golongan_darah'=>['nullable', 'in:A,B,AB,O,tidak_diketahui'],
            'alergi'       => ['nullable', 'string', 'max:1000'],
            'no_asuransi'  => ['nullable', 'string', 'max:50'],
        ];
    }
}
```

---

## 10. Livewire Components

### 10.1 PasienTable — List & Search

```php
// app/Livewire/Pasien/PasienTable.php

namespace App\Livewire\Pasien;

use App\Services\PasienService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class PasienTable extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $filterTipe = '';

    #[Url]
    public string $sortBy  = 'created_at';

    #[Url]
    public string $sortDir = 'desc';

    public int $perPage = 10;

    public function updatingSearch(): void     { $this->resetPage(); }
    public function updatingFilterTipe(): void  { $this->resetPage(); }

    public function sort(string $col): void
    {
        $this->sortDir = ($this->sortBy === $col && $this->sortDir === 'asc') ? 'desc' : 'asc';
        $this->sortBy  = $col;
        $this->resetPage();
    }

    #[Computed]
    public function pasien()
    {
        return app(PasienService::class)->paginate([
            'search'      => $this->search      ?: null,
            'tipe_pasien' => $this->filterTipe  ?: null,
            'sort_by'     => $this->sortBy,
            'sort_dir'    => $this->sortDir,
        ], $this->perPage);
    }

    public function toggleActive(int $id, bool $state): void
    {
        $this->authorize('pasien.edit');
        app(PasienService::class)->toggleActive($id, $state);
        unset($this->pasien);
        $msg = $state ? 'Pasien berhasil diaktifkan.' : 'Pasien berhasil dinonaktifkan.';
        $this->dispatch('notify', type: 'success', message: $msg);
    }

    #[On('pasien-saved')]
    public function refresh(): void { unset($this->pasien); }

    public function render()
    {
        return view('livewire.pasien.pasien-table');
    }
}
```

### 10.2 PasienForm — Create/Edit dengan Kontak Darurat

```php
// app/Livewire/Pasien/PasienForm.php

namespace App\Livewire\Pasien;

use App\Models\Pasien;
use App\Services\PasienService;
use Livewire\Component;

class PasienForm extends Component
{
    public ?int  $pasienId   = null;
    public bool  $isEdit     = false;
    public string $nomorRM   = '';

    // Identitas
    public string $nama          = '';
    public string $tempat_lahir  = '';
    public string $tanggal_lahir = '';
    public string $jenis_kelamin = 'L';
    public string $tipe_pasien   = 'WNI';

    // Identifikasi
    public string $nik        = '';
    public string $no_paspor  = '';
    public string $negara_asal= '';
    public string $no_bpjs    = '';

    // Kontak
    public string $alamat   = '';
    public string $telepon  = '';
    public string $email    = '';

    // Medis
    public string $golongan_darah = '';
    public string $alergi        = '';
    public string $no_asuransi   = '';

    // Kontak darurat (array of arrays)
    public array $kontak = [];

    public function addKontak(): void
    {
        $this->kontak[] = [
            'nama'       => '',
            'nomor_hp'   => '',
            'hubungan'   => 'lainnya',
            'alamat'     => '',
            'is_primary' => count($this->kontak) === 0,
        ];
    }

    public function removeKontak(int $index): void
    {
        $wasPrimary = $this->kontak[$index]['is_primary'] ?? false;
        array_splice($this->kontak, $index, 1);

        // Jika primary dihapus → set kontak pertama sebagai primary
        if ($wasPrimary && count($this->kontak) > 0) {
            $this->kontak[0]['is_primary'] = true;
        }
    }

    public function setPrimaryKontak(int $index): void
    {
        foreach ($this->kontak as $i => $k) {
            $this->kontak[$i]['is_primary'] = ($i === $index);
        }
    }

    public function updatedTipePasien(): void
    {
        // Reset field yang tidak relevan saat ganti tipe
        if ($this->tipe_pasien === 'WNI') {
            $this->no_paspor   = '';
            $this->negara_asal = '';
        } else {
            $this->nik     = '';
            $this->no_bpjs = '';
        }
    }

    public function mountEdit(int $pasienId): void
    {
        $pasien = Pasien::with('kontakDarurat')->findOrFail($pasienId);
        $this->pasienId     = $pasienId;
        $this->isEdit       = true;
        $this->nomorRM      = $pasien->nomor_rm;
        $this->nama         = $pasien->nama;
        $this->tempat_lahir = $pasien->tempat_lahir;
        $this->tanggal_lahir= $pasien->tanggal_lahir->format('Y-m-d');
        $this->jenis_kelamin= $pasien->jenis_kelamin;
        $this->tipe_pasien  = $pasien->tipe_pasien;
        $this->nik          = $pasien->nik        ?? '';
        $this->no_paspor    = $pasien->no_paspor  ?? '';
        $this->negara_asal  = $pasien->negara_asal ?? '';
        $this->no_bpjs      = $pasien->no_bpjs    ?? '';
        $this->alamat       = $pasien->alamat;
        $this->telepon      = $pasien->telepon;
        $this->email        = $pasien->email       ?? '';
        $this->golongan_darah=$pasien->golongan_darah ?? '';
        $this->alergi       = $pasien->alergi     ?? '';
        $this->no_asuransi  = $pasien->no_asuransi ?? '';
        $this->kontak       = $pasien->kontakDarurat->map(fn ($k) => [
            'nama'       => $k->nama,
            'nomor_hp'   => $k->nomor_hp,
            'hubungan'   => $k->hubungan,
            'alamat'     => $k->alamat ?? '',
            'is_primary' => $k->is_primary,
        ])->toArray();
    }

    public function save(PasienService $service): void
    {
        $rules = $this->isEdit
            ? $this->getUpdateRules()
            : $this->getCreateRules();

        $this->validate($rules, $this->messages());

        $data = [
            'nama'          => $this->nama,
            'tempat_lahir'  => $this->tempat_lahir,
            'tanggal_lahir' => $this->tanggal_lahir,
            'jenis_kelamin' => $this->jenis_kelamin,
            'tipe_pasien'   => $this->tipe_pasien,
            'nik'           => $this->nik           ?: null,
            'no_paspor'     => $this->no_paspor     ?: null,
            'negara_asal'   => $this->negara_asal   ?: null,
            'no_bpjs'       => $this->no_bpjs       ?: null,
            'alamat'        => $this->alamat,
            'telepon'       => $this->telepon,
            'email'         => $this->email          ?: null,
            'golongan_darah'=> $this->golongan_darah ?: null,
            'alergi'        => $this->alergi         ?: null,
            'no_asuransi'   => $this->no_asuransi    ?: null,
        ];

        if ($this->isEdit) {
            $data['pasien_id'] = $this->pasienId;
            $service->update($this->pasienId, $data, $this->kontak ?: null);
            $this->dispatch('notify', type: 'success', message: 'Data pasien berhasil diupdate.');
        } else {
            $pasien = $service->create($data, $this->kontak);
            $this->dispatch('notify', type: 'success',
                message: "Pasien berhasil didaftarkan. No. RM: {$pasien->nomor_rm}");
            return redirect()->route('pasien.show', $pasien->id);
        }

        $this->dispatch('pasien-saved');
    }

    protected function getCreateRules(): array { /* lihat StorePasienRequest */ return []; }
    protected function getUpdateRules(): array { /* lihat UpdatePasienRequest */ return []; }
    protected function messages(): array       { return []; }

    public function getHubunganOptionsProperty(): array
    {
        return Pasien::getHubunganOptions();
    }

    public function render()
    {
        return view('livewire.pasien.pasien-form');
    }
}
```

---

## 11. Blade Views

### Halaman Index Pasien

```blade
{{-- resources/views/pasien/index.blade.php --}}
<x-app-layout>
    <x-slot name="title">Manajemen Pasien</x-slot>

    <div class="page-header">
        <div>
            <h2 class="page-title">Manajemen Pasien</h2>
            <p class="page-subtitle">Pencarian, registrasi, dan manajemen data demografi pasien</p>
        </div>
        @can('pasien.create')
        <a href="{{ route('pasien.create') }}" class="btn-primary">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Daftarkan Pasien
        </a>
        @endcan
    </div>

    <x-alert />
    <livewire:pasien.pasien-table />

</x-app-layout>
```

### Komponen Badge Status Pasien

```blade
{{-- resources/views/components/tipe-pasien.blade.php --}}
@props(['tipe'])

@if ($tipe === 'WNI')
    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium
                 bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300">
        🇮🇩 WNI
    </span>
@else
    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium
                 bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300">
        🌐 WNA
    </span>
@endif
```

### Tabel Pasien (Livewire View)

```blade
{{-- resources/views/livewire/pasien/pasien-table.blade.php --}}
<div>
    {{-- Toolbar --}}
    <div class="mb-4 flex flex-col sm:flex-row gap-3 justify-between">
        <div class="flex flex-wrap gap-2">
            <div class="relative">
                <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 pointer-events-none">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/>
                    </svg>
                </span>
                <input wire:model.live.debounce.400ms="search" type="text"
                       placeholder="Nama, No. RM, NIK, telepon..."
                       class="form-input pl-9 w-72 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
            </div>
            <select wire:model.live="filterTipe"
                    class="form-select w-32 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200">
                <option value="">Semua Tipe</option>
                <option value="WNI">WNI</option>
                <option value="WNA">WNA</option>
            </select>
        </div>
    </div>

    <div wire:loading.delay class="mb-2 text-sm text-gray-400 flex items-center gap-2">
        <div class="spinner"></div> Memuat...
    </div>

    <div class="table-wrapper">
        <table class="table">
            <thead>
                <tr>
                    <th>
                        <button wire:click="sort('nomor_rm')" class="table-sortable flex items-center gap-1">
                            No. RM @if($sortBy === 'nomor_rm') <span class="text-primary-600">{{ $sortDir==='asc'?'↑':'↓' }}</span> @endif
                        </button>
                    </th>
                    <th>
                        <button wire:click="sort('nama')" class="table-sortable flex items-center gap-1">
                            Nama Pasien @if($sortBy === 'nama') <span class="text-primary-600">{{ $sortDir==='asc'?'↑':'↓' }}</span> @endif
                        </button>
                    </th>
                    <th>Tipe / Identitas</th>
                    <th>Tgl. Lahir / Umur</th>
                    <th>Telepon</th>
                    <th>Kontak Darurat</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->pasien as $p)
                <tr wire:key="pasien-{{ $p->id }}">
                    <td class="font-mono text-xs font-semibold text-gray-700 dark:text-gray-300">
                        {{ $p->nomor_rm }}
                    </td>
                    <td>
                        <p class="font-medium text-gray-900 dark:text-gray-100">{{ $p->nama }}</p>
                        <p class="text-xs text-gray-400">{{ $p->jenis_kelamin_label }}</p>
                    </td>
                    <td>
                        <x-tipe-pasien :tipe="$p->tipe_pasien" />
                        <p class="text-xs text-gray-400 mt-1 font-mono">
                            {{ $p->tipe_pasien === 'WNI' ? ($p->nik ?? '-') : ($p->no_paspor ?? '-') }}
                        </p>
                    </td>
                    <td class="text-sm">
                        <p>{{ $p->tanggal_lahir->format('d/m/Y') }}</p>
                        <p class="text-xs text-gray-400">{{ $p->umur }} tahun</p>
                    </td>
                    <td class="text-sm text-gray-600 dark:text-gray-400">{{ $p->telepon }}</td>
                    <td class="text-xs text-gray-500">
                        @if ($p->kontakPrimary)
                            <p class="font-medium">{{ $p->kontakPrimary->nama }}</p>
                            <p class="text-gray-400">{{ $p->kontakPrimary->nomor_hp }}</p>
                        @else
                            <span class="text-gray-300">—</span>
                        @endif
                    </td>
                    <td>
                        @can('pasien.edit')
                        <button
                            wire:click="toggleActive({{ $p->id }}, {{ $p->is_active ? 'false' : 'true' }})"
                            wire:confirm="{{ $p->is_active ? 'Nonaktifkan' : 'Aktifkan' }} pasien {{ $p->nama }}?"
                            @class([
                                'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium',
                                'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300' => $p->is_active,
                                'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300'                => !$p->is_active,
                            ])>
                            <span class="h-1.5 w-1.5 rounded-full {{ $p->is_active ? 'bg-emerald-500' : 'bg-red-500' }}"></span>
                            {{ $p->is_active ? 'Aktif' : 'Nonaktif' }}
                        </button>
                        @endcan
                    </td>
                    <td>
                        <div class="flex items-center gap-1">
                            <a href="{{ route('pasien.show', $p) }}" class="btn-info btn-sm">Detail</a>
                            @can('pasien.edit')
                            <a href="{{ route('pasien.edit', $p) }}" class="btn-warning btn-sm">Edit</a>
                            @endcan
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8">
                        <div class="empty-state">
                            <svg class="empty-state-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                      d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            <p class="empty-state-text">Tidak ada data pasien ditemukan</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4 flex items-center justify-between text-sm text-gray-500 dark:text-gray-400">
        @if ($this->pasien->total() > 0)
        <span>
            Menampilkan {{ $this->pasien->firstItem() }}–{{ $this->pasien->lastItem() }}
            dari {{ $this->pasien->total() }} pasien
        </span>
        {{ $this->pasien->links() }}
        @endif
    </div>
</div>
```

---

## 12. Routes & RBAC

### Routes

```php
// Tambahkan ke routes/web.php

Route::middleware(['auth', 'active'])->group(function () {

    // Manajemen Pasien
    Route::prefix('pasien')->name('pasien.')->group(function () {

        Route::middleware('permission:pasien.view')->group(function () {
            Route::get('/', fn () => view('pasien.index'))->name('index');
            Route::get('/{pasien}', fn ($pasien) => view('pasien.show',
                ['pasien' => \App\Models\Pasien::with(['kontakDarurat', 'kunjungan'])
                    ->findOrFail($pasien)]))->name('show');
        });

        Route::middleware('permission:pasien.create')->group(function () {
            Route::get('/create', fn () => view('pasien.create'))->name('create');
        });

        Route::middleware('permission:pasien.edit')->group(function () {
            Route::get('/{pasien}/edit', fn ($pasien) =>
                view('pasien.edit', ['pasienId' => $pasien]))->name('edit');
        });
    });
});
```

### Update Sidebar (app.blade.php)

```blade
{{-- Di layouts/app.blade.php --}}
<x-sidebar-item route="pasien.index" icon="users" permission="pasien.view">
    Manajemen Pasien
</x-sidebar-item>
```

### Permission yang sudah ada (dari masterdata_v1.md)

```
pasien.view   → admin, dokter, perawat, apoteker, kasir, rekam_medis
pasien.create → admin, perawat
pasien.edit   → admin, perawat, rekam_medis
pasien.delete → admin (soft delete via is_active)
```

---

## 13. Struktur Folder

```
app/
├── Http/
│   └── Requests/
│       └── Pasien/
│           ├── StorePasienRequest.php
│           └── UpdatePasienRequest.php
│
├── Livewire/
│   └── Pasien/
│       ├── PasienTable.php          ← list + search + filter + sort + toggle aktif
│       ├── PasienForm.php           ← create/edit + kontak darurat dinamis
│       └── PasienDetail.php         ← view detail + riwayat kunjungan
│
├── Models/
│   ├── Pasien.php         (diupdate: tempat lahir, tipe WNI/WNA, dll)
│   └── KontakDarurat.php  (baru)
│
├── Repositories/
│   └── PasienRepository.php  (baru: query + kontak darurat CRUD)
│
└── Services/
    └── PasienService.php     (baru: generate NoRM, validasi duplikat, audit)

resources/views/
├── components/
│   └── tipe-pasien.blade.php        ← badge WNI / WNA
├── pasien/
│   ├── index.blade.php              ← halaman list
│   ├── create.blade.php             ← form registrasi baru
│   ├── edit.blade.php               ← form edit demografi
│   └── show.blade.php               ← detail + riwayat kunjungan
└── livewire/
    └── pasien/
        ├── pasien-table.blade.php
        ├── pasien-form.blade.php
        └── pasien-detail.blade.php

database/
└── migrations/
    ├── 2026_01_04_000001_update_pasien_add_v2_fields.php
    └── 2026_01_04_000002_create_kontak_darurat_table.php
```

---

## 14. User Stories & Business Rules

| ID | Persona | Skenario | Expected Behavior |
|----|---------|----------|-------------------|
| **US01** | Admin/Perawat | Daftar pasien WNI | Pilih WNI → NIK + BPJS muncul. Submit tanpa NIK → error validasi |
| **US02** | Admin/Perawat | Daftar pasien WNA | Pilih WNA → No. Paspor + Negara muncul, NIK & BPJS hilang |
| **US03** | Admin | Input NIK duplikat | Service error: "NIK sudah terdaftar atas nama Budi (RM-000001)" |
| **US04** | Admin | Simpan registrasi | NoRM auto `RM-000043` · redirect ke detail pasien |
| **US05** | Admin | Tambah 2 kontak, keduanya primary | Validasi error: "Hanya boleh satu kontak utama" |
| **US06** | Admin | Hapus kontak primary, masih ada kontak lain | Kontak tersisa otomatis menjadi primary |
| **US07** | Admin | No. HP kontak = No. HP pasien | Error: "Nomor HP kontak tidak boleh sama dengan nomor HP pasien" |
| **US08** | Dokter | Buka detail pasien | Tidak ada tombol Edit. Hanya lihat data & riwayat kunjungan |
| **US09** | Kasir | Cari pasien untuk billing | Nama, NoRM, telepon visible |
| **US10** | Admin | Nonaktifkan pasien | `is_active = false` · data tetap (soft) · activity log tercatat |
| **US11** | Admin | Scan QR NoRM dari kartu pasien | Quick search by `nomor_rm` → buka halaman detail |

---

## 15. Seed Data Awal

```php
// database/seeders/PasienSeeder.php

namespace Database\Seeders;

use App\Models\KontakDarurat;
use App\Models\Pasien;
use Illuminate\Database\Seeder;

class PasienSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            [
                'nomor_rm'      => 'RM-000001',
                'nama'          => 'Budi Santoso',
                'tempat_lahir'  => 'Jakarta',
                'tanggal_lahir' => '1985-03-15',
                'jenis_kelamin' => 'L',
                'tipe_pasien'   => 'WNI',
                'nik'           => '3174051503850001',
                'alamat'        => 'Jl. Merdeka No. 12, Gambir, Jakarta Pusat',
                'telepon'       => '08123456789',
                'email'         => 'budi.santoso@email.com',
                'golongan_darah'=> 'A',
                'no_bpjs'       => '0001234567890',
                'kontak'        => [
                    ['nama' => 'Siti Santoso', 'nomor_hp' => '08129876543',
                     'hubungan' => 'istri', 'is_primary' => true],
                ],
            ],
            [
                'nomor_rm'      => 'RM-000002',
                'nama'          => 'John Smith',
                'tempat_lahir'  => 'New York',
                'tanggal_lahir' => '1990-07-22',
                'jenis_kelamin' => 'L',
                'tipe_pasien'   => 'WNA',
                'no_paspor'     => 'US123456',
                'negara_asal'   => 'Amerika Serikat',
                'alamat'        => 'Jl. Sunset Road No. 88, Kuta, Bali',
                'telepon'       => '081398765432',
                'no_asuransi'   => 'INS-US-2024-001',
                'kontak'        => [
                    ['nama' => 'Jane Smith', 'nomor_hp' => '081312345678',
                     'hubungan' => 'istri', 'is_primary' => true],
                ],
            ],
            [
                'nomor_rm'      => 'RM-000003',
                'nama'          => 'Ni Luh Ayu Dewi',
                'tempat_lahir'  => 'Denpasar',
                'tanggal_lahir' => '1995-11-08',
                'jenis_kelamin' => 'P',
                'tipe_pasien'   => 'WNI',
                'nik'           => '5171086811950001',
                'alamat'        => 'Jl. Raya Kuta No. 45, Banjar Pande, Denpasar',
                'telepon'       => '082145678901',
                'golongan_darah'=> 'O',
                'kontak'        => [
                    ['nama' => 'I Wayan Dewi',  'nomor_hp' => '082198765432',
                     'hubungan' => 'ayah', 'is_primary' => true],
                    ['nama' => 'Ni Made Sari',  'nomor_hp' => '082187654321',
                     'hubungan' => 'ibu',  'is_primary' => false],
                ],
            ],
        ];

        foreach ($data as $d) {
            $kontak = $d['kontak'];
            unset($d['kontak']);

            $pasien = Pasien::firstOrCreate(
                ['nomor_rm' => $d['nomor_rm']],
                $d
            );

            foreach ($kontak as $k) {
                KontakDarurat::firstOrCreate(
                    ['pasien_id' => $pasien->id, 'nama' => $k['nama']],
                    array_merge($k, ['pasien_id' => $pasien->id])
                );
            }

            $this->command->info("✓ Pasien: {$d['nama']} [{$d['tipe_pasien']}] — {$d['nomor_rm']}");
        }

        $this->command->info('✅ PasienSeeder selesai (3 data).');
    }
}
```

Tambahkan ke `DatabaseSeeder.php`:

```php
$this->call([
    RolePermissionSeeder::class,
    UserSeeder::class,
    KlinikSeeder::class,
    PoliSeeder::class,
    ObatSeeder::class,
    MasterdataV2Seeder::class,
    DokterV3Seeder::class,
    PasienSeeder::class,   // ← tambahkan
]);
```

---

## Appendix — Diagram Relasi (Eloquent)

```
User (role = pasien, opsional)
    └── Pasien (1-to-1, nullable)
            ├── KontakDarurat[] (1-to-many)
            │     nama · nomor_hp · hubungan · is_primary
            │
            ├── Kunjungan[] (1-to-many)
            │     poli · dokter · status · tanggal
            │
            └── RawatInap[] (1-to-many)

Auto-Generate NoRM:
  DB::transaction → SELECT MAX(nomor_rm) FOR UPDATE
  → 'RM-' + str_pad(next, 6, '0', STR_PAD_LEFT)

Filter Form Pendaftaran Kunjungan:
  Pasien::aktif()->search($query)->paginate(10)
```

---

*manajemen_pasien.md v2.0.0 · Laravel 12 + Livewire 3 · Living document*

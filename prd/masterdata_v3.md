# Master Data V3 — Data Dokter
**Versi:** 3.0.1 (Laravel 12 + Livewire 3)
**Tech Stack:** Laravel 12 · Livewire 3 · Spatie Permission · Eloquent ORM · Tailwind CSS
**Depends On:** `setup_awal.md` · `masterdata_v1.md v2.0.0` · `masterdata_v2.md v2.3-r2`
**Changelog v3.0.1:** Rewrite penuh dari Next.js/Prisma/shadcn ke Laravel 12 + Livewire 3

---

## Daftar Isi

1. [Konteks & Posisi dalam Project](#1-konteks--posisi-dalam-project)
2. [Modul yang Dicakup](#2-modul-yang-dicakup)
3. [Business Rules](#3-business-rules)
4. [Migration & Model](#4-migration--model)
5. [Repository Layer](#5-repository-layer)
6. [Service Layer](#6-service-layer)
7. [Form Request Validation](#7-form-request-validation)
8. [Livewire Components](#8-livewire-components)
9. [Blade Views](#9-blade-views)
10. [Routes & RBAC](#10-routes--rbac)
11. [Struktur Folder](#11-struktur-folder)
12. [User Stories](#12-user-stories)
13. [Seed Data Awal](#13-seed-data-awal)

---

## 1. Konteks & Posisi dalam Project

```
setup_awal.md              → Tech stack, migration dasar, seeder awal
    ↓
masterdata_v1.md           → User management, RBAC, Livewire components
    ↓
masterdata_v2.md           → Poli, Tindakan, Lab, Radiologi, Peralatan Medis
    ↓
masterdata_v3.md (ini)     → Data Dokter:
                             - Profil klinis (NIK, SIP, Expired SIP, Spesialisasi)
                             - Mapping Dokter ↔ Poli (many-to-many)
                             - Sharing Fee per kategori (%)
                             - Jadwal Praktek per poli per hari
```

### Gap yang Diselesaikan V3

Model `Dokter` di `setup_awal.md` sangat minimal:

| Field / Fitur | Setup Awal (lama) | masterdata_v3 |
|---|---|---|
| `nip` | `String?` tidak terstruktur | ✅ NIK 16 digit, unik, tervalidasi |
| `sip` | `String?` teks bebas | ✅ Nomor SIP terstruktur |
| `tgl_expired_sip` | ❌ Belum ada | ✅ Mandatori, alert 30 hari sebelum expired |
| `spesialisasi` | `String?` | ✅ Dipertahankan |
| **Mapping ke Poli** | `poli_id` — satu poli saja | ✅ Many-to-many via `dokter_poli` |
| **Sharing Fee** | ❌ Belum ada | ✅ Model baru per kategori (%) |
| **Jadwal Praktek** | `jadwal_praktek JSON` tidak terstruktur | ✅ Tabel relasional `jadwal_praktek` |

---

## 2. Modul yang Dicakup

| # | Modul | Deskripsi |
|---|-------|-----------|
| 1 | **Profil Dokter** | NIK, No. SIP, Tgl Expired SIP, Spesialisasi |
| 2 | **Mapping Poli** | Satu dokter bisa dipetakan ke banyak poli (many-to-many) |
| 3 | **Sharing Fee** | Persentase fee per kategori: Tindakan, Lab, Radiologi, Peralatan |
| 4 | **Jadwal Praktek** | Jadwal per poli per hari, slot jam mulai–selesai, kuota pasien |

---

## 3. Business Rules

### 3.1 Profil Dokter

```
User (role = dokter)
    ↓
Dokter profile dibuat otomatis saat user dokter dibuat
Jika belum ada → Super Admin/Admin setup dari halaman "Data Dokter"

Aturan:
  ✓ Satu User dokter — satu Dokter profile (1-to-1, sudah ada di migration)
  ✓ Nama, email, telepon diambil dari User
  ✓ NIK, SIP, Expired SIP, Spesialisasi diinput di profil dokter
  ✗ NIK = unik di tabel dokter
  ✗ Nomor SIP = unik
```

### 3.2 SIP — Alert Expired

```
SIP expired ≤ 30 hari → Badge "Segera Expired" (kuning)
SIP sudah expired     → Badge "SIP Expired" (merah) + dokter tidak bisa terima kunjungan baru
SIP masih berlaku     → Badge "Aktif" (hijau)
```

### 3.3 Mapping Dokter ↔ Poli

```
Aturan:
  ✓ Dokter wajib mapping ke ≥ 1 poli sebelum bisa menerima kunjungan
  ✓ Admin bisa tambah/hapus mapping kapan saja
  ✓ Jadwal Praktek hanya bisa dibuat untuk poli yang sudah di-mapping
  ✗ Dokter tanpa mapping poli tidak muncul di dropdown form pendaftaran
```

### 3.4 Sharing Fee

```
4 kategori (sesuai masterdata_v2):
  ├── tindakan   : 0–100% (default 0%)
  ├── lab        : 0–100% (default 0%)
  ├── radiologi  : 0–100% (default 0%)
  └── peralatan  : 0–100% (default 0%)

Kalkulasi billing: fee = tarif_item × (persen / 100)
```

### 3.5 Jadwal Praktek

```
Aturan:
  ✓ Jadwal terikat pada kombinasi Dokter + Poli (via dokter_poli_id)
  ✓ Satu DokterPoli bisa punya banyak jadwal (beda hari)
  ✓ Satu hari bisa punya ≥ 1 slot (pagi & sore)
  ✗ Tidak boleh jadwal overlap jam pada DokterPoli yang sama di hari yang sama
  ✓ kuota_pasien default 20
  ✓ is_aktif bisa di-toggle tanpa hapus jadwal
```

---

## 4. Migration & Model

### 4.1 Update Tabel `dokter` (addendum dari setup_awal.md)

```php
// database/migrations/2026_01_03_000001_update_dokter_add_sip_fields.php

Schema::table('dokter', function (Blueprint $table) {
    $table->string('nik', 16)->nullable()->unique()->after('nip');
    $table->string('no_sip')->nullable()->unique()->after('nik');
    $table->date('tgl_expired_sip')->nullable()->after('no_sip');
    // kolom nip & spesialisasi sudah ada dari setup_awal
});
```

### 4.2 Tabel Pivot Dokter ↔ Poli

```php
// database/migrations/2026_01_03_000002_create_dokter_poli_table.php

Schema::create('dokter_poli', function (Blueprint $table) {
    $table->id();
    $table->foreignId('dokter_id')->constrained('dokter')->onDelete('cascade');
    $table->foreignId('poli_id')->constrained('poli')->onDelete('cascade');
    $table->boolean('is_aktif')->default(true);
    $table->unique(['dokter_id', 'poli_id']);
    $table->timestamps();
});
```

### 4.3 Tabel Sharing Fee

```php
// database/migrations/2026_01_03_000003_create_sharing_fee_table.php

Schema::create('sharing_fee', function (Blueprint $table) {
    $table->id();
    $table->foreignId('dokter_id')->constrained('dokter')->onDelete('cascade');
    $table->enum('kategori', ['tindakan', 'lab', 'radiologi', 'peralatan']);
    $table->decimal('persentase', 5, 2)->default(0); // 0.00 – 100.00
    $table->unique(['dokter_id', 'kategori']);
    $table->timestamps();
});
```

### 4.4 Tabel Jadwal Praktek

```php
// database/migrations/2026_01_03_000004_create_jadwal_praktek_table.php

Schema::create('jadwal_praktek', function (Blueprint $table) {
    $table->id();
    $table->foreignId('dokter_poli_id')
          ->constrained('dokter_poli')->onDelete('cascade');
    $table->enum('hari', ['senin','selasa','rabu','kamis','jumat','sabtu','minggu']);
    $table->time('jam_mulai');
    $table->time('jam_selesai');
    $table->unsignedSmallInteger('kuota_pasien')->default(20);
    $table->boolean('is_aktif')->default(true);
    $table->string('keterangan')->nullable();
    $table->timestamps();
});
```

### 4.5 Model Dokter (diupdate)

```php
// app/Models/Dokter.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Dokter extends Model
{
    protected $table = 'dokter';

    protected $fillable = [
        'user_id', 'poli_id', 'nip', 'nik', 'no_sip',
        'tgl_expired_sip', 'sip', 'spesialisasi', 'jadwal_praktek',
    ];

    protected function casts(): array
    {
        return [
            'tgl_expired_sip' => 'date',
            'jadwal_praktek'  => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function poliUtama()
    {
        return $this->belongsTo(Poli::class, 'poli_id');
    }

    // Many-to-many ke Poli via dokter_poli
    public function poli()
    {
        return $this->belongsToMany(Poli::class, 'dokter_poli')
                    ->withPivot(['is_aktif'])
                    ->withTimestamps();
    }

    public function dokterPoli()
    {
        return $this->hasMany(DokterPoli::class);
    }

    public function sharingFee()
    {
        return $this->hasMany(SharingFee::class);
    }

    public function kunjungan()
    {
        return $this->hasMany(Kunjungan::class);
    }

    // ── Helpers SIP Status ───────────────────────────────────

    public function getSipStatusAttribute(): string
    {
        if (! $this->tgl_expired_sip) return 'tidak_ada';
        $sisa = now()->diffInDays($this->tgl_expired_sip, false);
        if ($sisa < 0)   return 'expired';
        if ($sisa <= 30) return 'segera_expired';
        return 'aktif';
    }

    public function getSipSisaHariAttribute(): ?int
    {
        if (! $this->tgl_expired_sip) return null;
        return (int) now()->diffInDays($this->tgl_expired_sip, false);
    }

    public function isSipAktif(): bool
    {
        return $this->sip_status === 'aktif';
    }

    // Scope: dokter aktif dengan SIP valid
    public function scopeAktifDanSipValid($query)
    {
        return $query->whereHas('user', fn ($q) => $q->where('is_active', true))
                     ->where('tgl_expired_sip', '>=', now());
    }

    // Scope: dokter yang ada jadwal di hari tertentu
    public function scopeDenganJadwalHari($query, string $hari)
    {
        return $query->whereHas('dokterPoli.jadwalPraktek', fn ($q) =>
            $q->where('hari', $hari)->where('is_aktif', true)
        );
    }
}
```

### 4.6 Model DokterPoli

```php
// app/Models/DokterPoli.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DokterPoli extends Model
{
    protected $table = 'dokter_poli';

    protected $fillable = ['dokter_id', 'poli_id', 'is_aktif'];

    protected function casts(): array
    {
        return ['is_aktif' => 'boolean'];
    }

    public function dokter()
    {
        return $this->belongsTo(Dokter::class);
    }

    public function poli()
    {
        return $this->belongsTo(Poli::class);
    }

    public function jadwalPraktek()
    {
        return $this->hasMany(JadwalPraktek::class)
                    ->orderBy('hari')
                    ->orderBy('jam_mulai');
    }
}
```

### 4.7 Model SharingFee

```php
// app/Models/SharingFee.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SharingFee extends Model
{
    protected $table = 'sharing_fee';

    protected $fillable = ['dokter_id', 'kategori', 'persentase'];

    protected function casts(): array
    {
        return ['persentase' => 'decimal:2'];
    }

    public function dokter()
    {
        return $this->belongsTo(Dokter::class);
    }

    public static function getKategoriLabels(): array
    {
        return [
            'tindakan'  => 'Tindakan Medis',
            'lab'       => 'Laboratorium',
            'radiologi' => 'Radiologi',
            'peralatan' => 'Peralatan Medis',
        ];
    }
}
```

### 4.8 Model JadwalPraktek

```php
// app/Models/JadwalPraktek.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JadwalPraktek extends Model
{
    protected $table = 'jadwal_praktek';

    protected $fillable = [
        'dokter_poli_id', 'hari', 'jam_mulai',
        'jam_selesai', 'kuota_pasien', 'is_aktif', 'keterangan',
    ];

    protected function casts(): array
    {
        return ['is_aktif' => 'boolean'];
    }

    public function dokterPoli()
    {
        return $this->belongsTo(DokterPoli::class);
    }

    public static function getHariOptions(): array
    {
        return [
            'senin'   => 'Senin',
            'selasa'  => 'Selasa',
            'rabu'    => 'Rabu',
            'kamis'   => 'Kamis',
            'jumat'   => 'Jumat',
            'sabtu'   => 'Sabtu',
            'minggu'  => 'Minggu',
        ];
    }

    // Cek apakah jam baru overlap dengan jadwal yang ada
    public static function hasOverlap(
        int $dokterPoliId,
        string $hari,
        string $jamMulai,
        string $jamSelesai,
        ?int $excludeId = null
    ): bool {
        return static::where('dokter_poli_id', $dokterPoliId)
            ->where('hari', $hari)
            ->where('is_aktif', true)
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->get()
            ->contains(function ($jadwal) use ($jamMulai, $jamSelesai) {
                $newMulai   = strtotime($jamMulai);
                $newSelesai = strtotime($jamSelesai);
                $exMulai    = strtotime($jadwal->jam_mulai);
                $exSelesai  = strtotime($jadwal->jam_selesai);
                return $newMulai < $exSelesai && $newSelesai > $exMulai;
            });
    }
}
```

---

## 5. Repository Layer

```php
// app/Repositories/DokterRepository.php

namespace App\Repositories;

use App\Models\Dokter;
use App\Models\DokterPoli;
use App\Models\SharingFee;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class DokterRepository
{
    public function paginate(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return Dokter::with(['user:id,nama,email,telepon,is_active', 'poli:id,nama,kode', 'sharingFee'])
            ->whereHas('user', function ($q) use ($filters) {
                $q->where('is_active', true);
                if ($filters['search'] ?? null) {
                    $q->where('nama', 'like', "%{$filters['search']}%")
                      ->orWhere('email', 'like', "%{$filters['search']}%");
                }
            })
            ->orderBy('id')
            ->paginate($perPage);
    }

    public function findById(int $id): ?Dokter
    {
        return Dokter::with([
            'user:id,nama,email,telepon,nip,is_active',
            'dokterPoli.poli:id,nama,kode',
            'dokterPoli.jadwalPraktek',
            'sharingFee',
        ])->find($id);
    }

    public function findByUserId(int $userId): ?Dokter
    {
        return Dokter::with(['poli', 'sharingFee'])
            ->where('user_id', $userId)->first();
    }

    public function updateProfil(int $id, array $data): Dokter
    {
        $dokter = Dokter::findOrFail($id);
        $dokter->update($data);
        return $dokter;
    }

    public function upsertMappingPoli(int $dokterId, int $poliId): DokterPoli
    {
        return DokterPoli::updateOrCreate(
            ['dokter_id' => $dokterId, 'poli_id' => $poliId],
            ['is_aktif' => true]
        );
    }

    public function removeMappingPoli(int $dokterId, int $poliId): void
    {
        DokterPoli::where('dokter_id', $dokterId)
                  ->where('poli_id', $poliId)
                  ->update(['is_aktif' => false]);
    }

    public function upsertSharingFee(int $dokterId, array $fees): void
    {
        foreach ($fees as $kategori => $persentase) {
            SharingFee::updateOrCreate(
                ['dokter_id' => $dokterId, 'kategori' => $kategori],
                ['persentase' => $persentase]
            );
        }
    }

    public function getSharingFee(int $dokterId): Collection
    {
        return SharingFee::where('dokter_id', $dokterId)
            ->orderBy('kategori')
            ->get();
    }

    public function hitungSharingFee(int $dokterId, array $items): array
    {
        $feeMap = $this->getSharingFee($dokterId)
            ->pluck('persentase', 'kategori')
            ->toArray();

        return collect($items)->map(function ($item) use ($feeMap) {
            $persen = $feeMap[$item['kategori']] ?? 0;
            return [
                'kategori'   => $item['kategori'],
                'total_tarif'=> $item['total_tarif'],
                'persentase' => $persen,
                'nominal_fee'=> $item['total_tarif'] * ($persen / 100),
            ];
        })->toArray();
    }
}
```

---

## 6. Service Layer

```php
// app/Services/DokterService.php

namespace App\Services;

use App\Models\Dokter;
use App\Models\DokterPoli;
use App\Models\JadwalPraktek;
use App\Repositories\DokterRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DokterService
{
    public function __construct(
        private readonly DokterRepository $repo
    ) {}

    // ── Profil ────────────────────────────────────────────────

    public function updateProfil(int $dokterId, array $data): Dokter
    {
        // Cek NIK duplikat
        if (! empty($data['nik'])) {
            $dup = Dokter::where('nik', $data['nik'])
                         ->where('id', '!=', $dokterId)->first();
            if ($dup) {
                throw ValidationException::withMessages([
                    'nik' => "NIK sudah digunakan dokter lain: {$dup->user->nama}.",
                ]);
            }
        }

        // Cek SIP duplikat
        if (! empty($data['no_sip'])) {
            $dup = Dokter::where('no_sip', $data['no_sip'])
                         ->where('id', '!=', $dokterId)->first();
            if ($dup) {
                throw ValidationException::withMessages([
                    'no_sip' => "Nomor SIP sudah digunakan dokter lain: {$dup->user->nama}.",
                ]);
            }
        }

        $dokter = $this->repo->updateProfil($dokterId, $data);

        activity('dokter')
            ->performedOn($dokter)
            ->causedBy(auth()->user())
            ->log('Profil dokter diupdate');

        return $dokter;
    }

    // ── Mapping Poli ──────────────────────────────────────────

    public function addPoliMapping(int $dokterId, int $poliId): DokterPoli
    {
        $mapping = $this->repo->upsertMappingPoli($dokterId, $poliId);

        activity('dokter')
            ->causedBy(auth()->user())
            ->log("Mapping poli ditambahkan: dokter #{$dokterId} → poli #{$poliId}");

        return $mapping;
    }

    public function removePoliMapping(int $dokterId, int $poliId): void
    {
        // Cek jadwal aktif sebelum hapus mapping
        $mapping = DokterPoli::where('dokter_id', $dokterId)
                             ->where('poli_id', $poliId)
                             ->with('jadwalPraktek')
                             ->first();

        if ($mapping) {
            $jadwalAktif = $mapping->jadwalPraktek->where('is_aktif', true)->count();
            if ($jadwalAktif > 0) {
                throw ValidationException::withMessages([
                    'poli_id' => "Tidak bisa hapus mapping — masih ada {$jadwalAktif} jadwal aktif. Nonaktifkan jadwal terlebih dahulu.",
                ]);
            }
        }

        $this->repo->removeMappingPoli($dokterId, $poliId);
    }

    // ── Sharing Fee ───────────────────────────────────────────

    public function saveSharingFee(int $dokterId, array $fees): void
    {
        // Validasi semua persentase 0–100
        foreach ($fees as $kategori => $persentase) {
            if ($persentase < 0 || $persentase > 100) {
                throw ValidationException::withMessages([
                    "fees.{$kategori}" => "Persentase {$kategori} harus antara 0 dan 100.",
                ]);
            }
        }

        $this->repo->upsertSharingFee($dokterId, $fees);

        activity('dokter')
            ->causedBy(auth()->user())
            ->log("Sharing fee dokter #{$dokterId} diupdate");
    }

    public function hitungSharingFee(int $dokterId, array $items): array
    {
        return $this->repo->hitungSharingFee($dokterId, $items);
    }

    // ── Jadwal Praktek ────────────────────────────────────────

    public function createJadwal(array $data): JadwalPraktek
    {
        // Validasi overlap
        if (JadwalPraktek::hasOverlap(
            $data['dokter_poli_id'],
            $data['hari'],
            $data['jam_mulai'],
            $data['jam_selesai']
        )) {
            throw ValidationException::withMessages([
                'jam_mulai' => "Jadwal hari {$data['hari']} pukul {$data['jam_mulai']}–{$data['jam_selesai']} bertabrakan dengan jadwal yang sudah ada.",
            ]);
        }

        return JadwalPraktek::create($data);
    }

    public function updateJadwal(int $id, array $data): JadwalPraktek
    {
        $jadwal = JadwalPraktek::findOrFail($id);

        // Cek overlap jika ada perubahan jam/hari
        if (isset($data['jam_mulai']) || isset($data['jam_selesai']) || isset($data['hari'])) {
            if (JadwalPraktek::hasOverlap(
                $data['dokter_poli_id'] ?? $jadwal->dokter_poli_id,
                $data['hari']        ?? $jadwal->hari,
                $data['jam_mulai']   ?? $jadwal->jam_mulai,
                $data['jam_selesai'] ?? $jadwal->jam_selesai,
                $id
            )) {
                throw ValidationException::withMessages([
                    'jam_mulai' => 'Jadwal bertabrakan dengan jadwal lain di hari yang sama.',
                ]);
            }
        }

        $jadwal->update($data);
        return $jadwal;
    }

    public function toggleJadwal(int $id): JadwalPraktek
    {
        $jadwal = JadwalPraktek::findOrFail($id);
        $jadwal->update(['is_aktif' => ! $jadwal->is_aktif]);
        return $jadwal;
    }

    public function deleteJadwal(int $id): void
    {
        JadwalPraktek::findOrFail($id)->delete();
    }
}
```

---

## 7. Form Request Validation

### 7.1 UpdateDokterProfilRequest

```php
// app/Http/Requests/Dokter/UpdateDokterProfilRequest.php

namespace App\Http\Requests\Dokter;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDokterProfilRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('masterdata.edit');
    }

    public function rules(): array
    {
        $dokterId = $this->input('dokter_id');

        return [
            'nik'            => ['nullable', 'string', 'size:16', 'regex:/^\d{16}$/',
                                  Rule::unique('dokter', 'nik')->ignore($dokterId)],
            'no_sip'         => ['nullable', 'string', 'min:5', 'max:50',
                                  Rule::unique('dokter', 'no_sip')->ignore($dokterId)],
            'tgl_expired_sip'=> ['nullable', 'date'],
            'spesialisasi'   => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'nik.size'  => 'NIK harus tepat 16 digit.',
            'nik.regex' => 'NIK hanya boleh berisi angka.',
            'nik.unique'=> 'NIK sudah digunakan dokter lain.',
            'no_sip.unique' => 'Nomor SIP sudah digunakan dokter lain.',
        ];
    }
}
```

### 7.2 StoreSharingFeeRequest

```php
// app/Http/Requests/Dokter/StoreSharingFeeRequest.php

namespace App\Http\Requests\Dokter;

use Illuminate\Foundation\Http\FormRequest;

class StoreSharingFeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('masterdata.edit');
    }

    public function rules(): array
    {
        return [
            'fees'              => ['required', 'array'],
            'fees.tindakan'     => ['required', 'numeric', 'min:0', 'max:100'],
            'fees.lab'          => ['required', 'numeric', 'min:0', 'max:100'],
            'fees.radiologi'    => ['required', 'numeric', 'min:0', 'max:100'],
            'fees.peralatan'    => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'fees.*.min' => 'Persentase minimal 0%.',
            'fees.*.max' => 'Persentase maksimal 100%.',
        ];
    }
}
```

### 7.3 StoreJadwalPraktekRequest

```php
// app/Http/Requests/Dokter/StoreJadwalPraktekRequest.php

namespace App\Http\Requests\Dokter;

use Illuminate\Foundation\Http\FormRequest;

class StoreJadwalPraktekRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('masterdata.edit');
    }

    public function rules(): array
    {
        return [
            'dokter_poli_id' => ['required', 'integer', 'exists:dokter_poli,id'],
            'hari'           => ['required', 'in:senin,selasa,rabu,kamis,jumat,sabtu,minggu'],
            'jam_mulai'      => ['required', 'date_format:H:i'],
            'jam_selesai'    => ['required', 'date_format:H:i', 'after:jam_mulai'],
            'kuota_pasien'   => ['required', 'integer', 'min:1', 'max:200'],
            'keterangan'     => ['nullable', 'string', 'max:200'],
        ];
    }

    public function messages(): array
    {
        return [
            'jam_selesai.after' => 'Jam selesai harus setelah jam mulai.',
        ];
    }
}
```

---

## 8. Livewire Components

### 8.1 DokterTable — List & Search

```php
// app/Livewire/Pengaturan/Dokter/DokterTable.php

namespace App\Livewire\Pengaturan\Dokter;

use App\Models\Dokter;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class DokterTable extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $filterSip = ''; // 'aktif' | 'segera_expired' | 'expired'

    public function updatingSearch(): void  { $this->resetPage(); }
    public function updatingFilterSip(): void { $this->resetPage(); }

    #[Computed]
    public function dokter()
    {
        return Dokter::with(['user:id,nama,email,is_active', 'poli:id,nama,kode'])
            ->whereHas('user', function ($q) {
                $q->where('is_active', true);
                if ($this->search) {
                    $q->where('nama', 'like', "%{$this->search}%")
                      ->orWhere('email', 'like', "%{$this->search}%");
                }
            })
            ->when($this->filterSip === 'expired',
                fn ($q) => $q->whereNotNull('tgl_expired_sip')
                             ->where('tgl_expired_sip', '<', now()))
            ->when($this->filterSip === 'segera_expired',
                fn ($q) => $q->whereNotNull('tgl_expired_sip')
                             ->whereBetween('tgl_expired_sip', [now(), now()->addDays(30)]))
            ->when($this->filterSip === 'aktif',
                fn ($q) => $q->where('tgl_expired_sip', '>=', now()))
            ->orderBy('id')
            ->paginate(15);
    }

    #[On('dokter-saved')]
    public function refresh(): void { unset($this->dokter); }

    public function render()
    {
        return view('livewire.pengaturan.dokter.dokter-table');
    }
}
```

### 8.2 DokterProfilForm — Edit Profil Klinis

```php
// app/Livewire/Pengaturan/Dokter/DokterProfilForm.php

namespace App\Livewire\Pengaturan\Dokter;

use App\Models\Dokter;
use App\Services\DokterService;
use Illuminate\Validation\Rule;
use Livewire\Component;

class DokterProfilForm extends Component
{
    public bool   $showModal = false;
    public ?int   $dokterId  = null;
    public string $namaUser  = '';

    public string $nik             = '';
    public string $no_sip          = '';
    public string $tgl_expired_sip = '';
    public string $spesialisasi    = '';

    public function getRules(): array
    {
        return [
            'nik'            => ['nullable', 'string', 'size:16', 'regex:/^\d{16}$/',
                                  Rule::unique('dokter', 'nik')->ignore($this->dokterId)],
            'no_sip'         => ['nullable', 'string', 'min:5',
                                  Rule::unique('dokter', 'no_sip')->ignore($this->dokterId)],
            'tgl_expired_sip'=> ['nullable', 'date'],
            'spesialisasi'   => ['nullable', 'string', 'max:100'],
        ];
    }

    public function getMessages(): array
    {
        return [
            'nik.size'  => 'NIK harus tepat 16 digit.',
            'nik.regex' => 'NIK hanya boleh berisi angka.',
            'nik.unique'=> 'NIK sudah digunakan dokter lain.',
            'no_sip.unique' => 'Nomor SIP sudah digunakan.',
        ];
    }

    public function open(int $dokterId): void
    {
        $this->authorize('masterdata.edit');
        $dokter = Dokter::with('user')->findOrFail($dokterId);

        $this->dokterId        = $dokterId;
        $this->namaUser        = $dokter->user->nama;
        $this->nik             = $dokter->nik       ?? '';
        $this->no_sip          = $dokter->no_sip    ?? '';
        $this->tgl_expired_sip = $dokter->tgl_expired_sip
            ? $dokter->tgl_expired_sip->format('Y-m-d') : '';
        $this->spesialisasi    = $dokter->spesialisasi ?? '';
        $this->showModal       = true;
        $this->resetValidation();
    }

    public function save(DokterService $service): void
    {
        $this->validate($this->getRules(), $this->getMessages());

        $service->updateProfil($this->dokterId, [
            'nik'             => $this->nik             ?: null,
            'no_sip'          => $this->no_sip           ?: null,
            'tgl_expired_sip' => $this->tgl_expired_sip ?: null,
            'spesialisasi'    => $this->spesialisasi     ?: null,
        ]);

        $this->showModal = false;
        $this->dispatch('dokter-saved');
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Profil dokter berhasil disimpan.']);
    }

    public function render()
    {
        return view('livewire.pengaturan.dokter.dokter-profil-form');
    }
}
```

### 8.3 DokterPoliMapping — Kelola Mapping Poli

```php
// app/Livewire/Pengaturan/Dokter/DokterPoliMapping.php

namespace App\Livewire\Pengaturan\Dokter;

use App\Models\Dokter;
use App\Models\Poli;
use App\Services\DokterService;
use Livewire\Attributes\Computed;
use Livewire\Component;

class DokterPoliMapping extends Component
{
    public int    $dokterId = 0;
    public string $namaUser = '';
    public int    $addPoliId = 0;

    public function mount(int $dokterId): void
    {
        $this->dokterId = $dokterId;
        $dokter = Dokter::with('user')->findOrFail($dokterId);
        $this->namaUser = $dokter->user->nama;
    }

    #[Computed]
    public function mappedPoli()
    {
        return Dokter::with('dokterPoli.poli:id,nama,kode')
            ->findOrFail($this->dokterId)
            ->dokterPoli()
            ->with('poli:id,nama,kode')
            ->get();
    }

    #[Computed]
    public function availablePoli()
    {
        $mappedIds = collect($this->mappedPoli)
            ->where('is_aktif', true)
            ->pluck('poli_id');

        return Poli::aktif()
            ->whereNotIn('id', $mappedIds)
            ->orderBy('nama')
            ->get(['id', 'nama', 'kode']);
    }

    public function addMapping(DokterService $service): void
    {
        $this->authorize('masterdata.edit');
        $this->validate(['addPoliId' => 'required|integer|exists:poli,id']);

        $service->addPoliMapping($this->dokterId, $this->addPoliId);
        $this->addPoliId = 0;
        unset($this->mappedPoli, $this->availablePoli);
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Poli berhasil ditambahkan.']);
    }

    public function removeMapping(int $poliId, DokterService $service): void
    {
        $this->authorize('masterdata.edit');
        $service->removePoliMapping($this->dokterId, $poliId);
        unset($this->mappedPoli, $this->availablePoli);
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Mapping poli dinonaktifkan.']);
    }

    public function render()
    {
        return view('livewire.pengaturan.dokter.dokter-poli-mapping');
    }
}
```

### 8.4 SharingFeeForm — Setup Fee per Kategori

```php
// app/Livewire/Pengaturan/Dokter/SharingFeeForm.php

namespace App\Livewire\Pengaturan\Dokter;

use App\Models\Dokter;
use App\Models\SharingFee;
use App\Services\DokterService;
use Livewire\Component;

class SharingFeeForm extends Component
{
    public int    $dokterId = 0;
    public string $namaUser = '';

    public string $fee_tindakan  = '0';
    public string $fee_lab       = '0';
    public string $fee_radiologi = '0';
    public string $fee_peralatan = '0';

    public function mount(int $dokterId): void
    {
        $this->dokterId = $dokterId;
        $dokter = Dokter::with(['user', 'sharingFee'])->findOrFail($dokterId);
        $this->namaUser = $dokter->user->nama;

        $feeMap = $dokter->sharingFee->pluck('persentase', 'kategori')->toArray();
        $this->fee_tindakan  = (string) ($feeMap['tindakan']  ?? 0);
        $this->fee_lab       = (string) ($feeMap['lab']       ?? 0);
        $this->fee_radiologi = (string) ($feeMap['radiologi'] ?? 0);
        $this->fee_peralatan = (string) ($feeMap['peralatan'] ?? 0);
    }

    public function save(DokterService $service): void
    {
        $this->authorize('masterdata.edit');
        $this->validate([
            'fee_tindakan'  => 'required|numeric|min:0|max:100',
            'fee_lab'       => 'required|numeric|min:0|max:100',
            'fee_radiologi' => 'required|numeric|min:0|max:100',
            'fee_peralatan' => 'required|numeric|min:0|max:100',
        ]);

        $service->saveSharingFee($this->dokterId, [
            'tindakan'  => (float) $this->fee_tindakan,
            'lab'       => (float) $this->fee_lab,
            'radiologi' => (float) $this->fee_radiologi,
            'peralatan' => (float) $this->fee_peralatan,
        ]);

        $this->dispatch('notify', ['type' => 'success', 'message' => 'Sharing fee berhasil disimpan.']);
    }

    public function render()
    {
        return view('livewire.pengaturan.dokter.sharing-fee-form');
    }
}
```

### 8.5 JadwalPraktekManager — Kelola Jadwal

```php
// app/Livewire/Pengaturan/Dokter/JadwalPraktekManager.php

namespace App\Livewire\Pengaturan\Dokter;

use App\Models\Dokter;
use App\Models\JadwalPraktek;
use App\Services\DokterService;
use Livewire\Attributes\Computed;
use Livewire\Component;

class JadwalPraktekManager extends Component
{
    public int    $dokterId       = 0;
    public bool   $showForm       = false;
    public ?int   $jadwalEditId   = null;

    // Form fields
    public int    $dokter_poli_id = 0;
    public string $hari           = '';
    public string $jam_mulai      = '';
    public string $jam_selesai    = '';
    public int    $kuota_pasien   = 20;
    public string $keterangan     = '';

    public function mount(int $dokterId): void
    {
        $this->dokterId = $dokterId;
    }

    #[Computed]
    public function dokterPoliList()
    {
        return Dokter::with('dokterPoli.poli:id,nama,kode')
            ->findOrFail($this->dokterId)
            ->dokterPoli()
            ->where('is_aktif', true)
            ->with('poli:id,nama,kode')
            ->get();
    }

    #[Computed]
    public function jadwalPerPoli()
    {
        return $this->dokterPoliList->mapWithKeys(fn ($dp) => [
            $dp->id => [
                'poli'   => $dp->poli,
                'jadwal' => $dp->jadwalPraktek,
            ],
        ]);
    }

    public function openCreate(int $dokterPoliId = 0): void
    {
        $this->authorize('masterdata.edit');
        $this->reset(['jadwalEditId','hari','jam_mulai','jam_selesai','keterangan']);
        $this->dokter_poli_id = $dokterPoliId;
        $this->kuota_pasien   = 20;
        $this->showForm       = true;
        $this->resetValidation();
    }

    public function openEdit(int $jadwalId): void
    {
        $this->authorize('masterdata.edit');
        $jadwal = JadwalPraktek::findOrFail($jadwalId);
        $this->jadwalEditId   = $jadwalId;
        $this->dokter_poli_id = $jadwal->dokter_poli_id;
        $this->hari           = $jadwal->hari;
        $this->jam_mulai      = $jadwal->jam_mulai;
        $this->jam_selesai    = $jadwal->jam_selesai;
        $this->kuota_pasien   = $jadwal->kuota_pasien;
        $this->keterangan     = $jadwal->keterangan ?? '';
        $this->showForm       = true;
        $this->resetValidation();
    }

    public function save(DokterService $service): void
    {
        $this->validate([
            'dokter_poli_id' => 'required|integer|exists:dokter_poli,id',
            'hari'           => 'required|in:senin,selasa,rabu,kamis,jumat,sabtu,minggu',
            'jam_mulai'      => 'required|date_format:H:i',
            'jam_selesai'    => 'required|date_format:H:i|after:jam_mulai',
            'kuota_pasien'   => 'required|integer|min:1|max:200',
        ], [
            'jam_selesai.after' => 'Jam selesai harus setelah jam mulai.',
        ]);

        $data = [
            'dokter_poli_id' => $this->dokter_poli_id,
            'hari'           => $this->hari,
            'jam_mulai'      => $this->jam_mulai,
            'jam_selesai'    => $this->jam_selesai,
            'kuota_pasien'   => $this->kuota_pasien,
            'keterangan'     => $this->keterangan ?: null,
        ];

        $this->jadwalEditId
            ? $service->updateJadwal($this->jadwalEditId, $data)
            : $service->createJadwal($data);

        $this->showForm = false;
        unset($this->jadwalPerPoli);
        $msg = $this->jadwalEditId ? 'Jadwal diupdate.' : 'Jadwal ditambahkan.';
        $this->dispatch('notify', ['type' => 'success', 'message' => $msg]);
    }

    public function toggle(int $jadwalId, DokterService $service): void
    {
        $this->authorize('masterdata.edit');
        $service->toggleJadwal($jadwalId);
        unset($this->jadwalPerPoli);
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Status jadwal diupdate.']);
    }

    public function delete(int $jadwalId, DokterService $service): void
    {
        $this->authorize('masterdata.edit');
        $service->deleteJadwal($jadwalId);
        unset($this->jadwalPerPoli);
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Jadwal dihapus.']);
    }

    public function render()
    {
        return view('livewire.pengaturan.dokter.jadwal-praktek-manager');
    }
}
```

---

## 9. Blade Views

### Halaman Index Dokter

```blade
{{-- resources/views/pengaturan/dokter/index.blade.php --}}
<x-app-layout>
    <x-slot name="title">Data Dokter</x-slot>

    <div class="page-header">
        <div>
            <h2 class="page-title">Data Dokter</h2>
            <p class="page-subtitle">Profil klinis, mapping poli, sharing fee, dan jadwal praktek</p>
        </div>
    </div>

    <x-alert />
    <livewire:pengaturan.dokter.dokter-table />
    <livewire:pengaturan.dokter.dokter-profil-form />

    {{-- Toast --}}
    <div x-data="{ show:false,type:'success',message:'' }"
         x-on:notify.window="show=true;type=$event.detail.type;message=$event.detail.message;setTimeout(()=>show=false,3500)"
         x-show="show" x-transition
         class="fixed bottom-5 right-5 z-50">
        <div :class="{ 'bg-emerald-50 border-emerald-400 text-emerald-800': type==='success',
                        'bg-red-50 border-red-400 text-red-800': type==='error' }"
             class="flex items-center gap-3 rounded-xl border px-4 py-3 shadow-lg text-sm font-medium min-w-72">
            <span x-text="message"></span>
        </div>
    </div>
</x-app-layout>
```

### Halaman Detail Dokter (Tab Layout)

```blade
{{-- resources/views/pengaturan/dokter/show.blade.php --}}
<x-app-layout>
    <x-slot name="title">Detail Dokter — {{ $dokter->user->nama }}</x-slot>

    @php $tab = request()->query('tab', 'profil'); @endphp

    <div class="page-header">
        <div>
            <h2 class="page-title">{{ $dokter->user->nama }}</h2>
            <p class="page-subtitle">{{ $dokter->spesialisasi ?? 'Dokter Umum' }}</p>
        </div>
        <a href="{{ route('pengaturan.dokter') }}" class="btn-secondary">← Kembali</a>
    </div>

    {{-- Tab Nav --}}
    <div class="border-b border-gray-200 dark:border-gray-700 mb-6">
        <nav class="flex gap-0 -mb-px">
            @foreach ([
                'profil'  => 'Profil Klinis',
                'poli'    => 'Mapping Poli',
                'fee'     => 'Sharing Fee',
                'jadwal'  => 'Jadwal Praktek',
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
        @case('profil')
            <livewire:pengaturan.dokter.dokter-profil-form :dokter-id="$dokter->id" :inline="true" />
            @break
        @case('poli')
            <livewire:pengaturan.dokter.dokter-poli-mapping :dokter-id="$dokter->id" />
            @break
        @case('fee')
            <livewire:pengaturan.dokter.sharing-fee-form :dokter-id="$dokter->id" />
            @break
        @case('jadwal')
            <livewire:pengaturan.dokter.jadwal-praktek-manager :dokter-id="$dokter->id" />
            @break
    @endswitch

    {{-- Toast --}}
    <div x-data="{ show:false,type:'success',message:'' }"
         x-on:notify.window="show=true;type=$event.detail.type;message=$event.detail.message;setTimeout(()=>show=false,3500)"
         x-show="show" x-transition class="fixed bottom-5 right-5 z-50">
        <div :class="{ 'bg-emerald-50 border-emerald-400 text-emerald-800': type==='success',
                        'bg-red-50 border-red-400 text-red-800': type==='error' }"
             class="flex items-center gap-3 rounded-xl border px-4 py-3 shadow-lg text-sm font-medium min-w-72">
            <span x-text="message"></span>
        </div>
    </div>
</x-app-layout>
```

### Blade Component — SIP Status Badge

```blade
{{-- resources/views/components/sip-status.blade.php --}}
@props(['dokter'])

@php
    $status   = $dokter->sip_status;
    $sisaHari = $dokter->sip_sisa_hari;
@endphp

@if ($status === 'aktif')
    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium
                 bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">
        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
        SIP Aktif ({{ $sisaHari }} hari)
    </span>
@elseif ($status === 'segera_expired')
    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium
                 bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">
        <span class="h-1.5 w-1.5 rounded-full bg-amber-500 animate-pulse"></span>
        Segera Expired ({{ $sisaHari }} hari)
    </span>
@elseif ($status === 'expired')
    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium
                 bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300">
        <span class="h-1.5 w-1.5 rounded-full bg-red-500"></span>
        SIP Expired
    </span>
@else
    <span class="badge-gray">Belum diisi</span>
@endif
```

---

## 10. Routes & RBAC

### Routes

```php
// Tambahkan ke routes/web.php

Route::prefix('pengaturan')->name('pengaturan.')->group(function () {

    // ... route existing ...

    // Data Dokter
    Route::middleware('permission:masterdata.view')->group(function () {
        Route::get('/dokter', fn () => view('pengaturan.dokter.index'))
             ->name('dokter');

        Route::get('/dokter/{dokter}', function (\App\Models\Dokter $dokter) {
            return view('pengaturan.dokter.show', compact('dokter'));
        })->name('dokter.show');
    });
});
```

### Update Sidebar

```blade
{{-- Tambahkan di resources/views/layouts/app.blade.php --}}

<x-sidebar-item route="pengaturan.dokter" icon="users" permission="masterdata.view">
    Data Dokter
</x-sidebar-item>
```

### Tambah Permission ke RolePermissionSeeder

```php
// Tambahkan di database/seeders/RolePermissionSeeder.php

// Permissions existing untuk masterdata sudah mencakup dokter
// Tidak perlu tambahan permission baru karena menggunakan masterdata.* yang sudah ada

// Update role dokter — boleh lihat profil sendiri
'dokter' => [
    // ... existing ...
    'masterdata.view',  // sudah ada dari v2
],
```

---

## 11. Struktur Folder

```
app/
├── Http/
│   └── Requests/
│       └── Dokter/
│           ├── UpdateDokterProfilRequest.php
│           ├── StoreSharingFeeRequest.php
│           └── StoreJadwalPraktekRequest.php
│
├── Livewire/
│   └── Pengaturan/
│       └── Dokter/
│           ├── DokterTable.php           ← list + filter SIP status
│           ├── DokterProfilForm.php      ← form NIK, SIP, Expired, Spesialisasi
│           ├── DokterPoliMapping.php     ← tambah/hapus mapping poli
│           ├── SharingFeeForm.php        ← 4 kategori fee + preview
│           └── JadwalPraktekManager.php  ← CRUD jadwal + overlap check
│
├── Models/
│   ├── Dokter.php        (diupdate: NIK, SIP, many-to-many)
│   ├── DokterPoli.php    (baru)
│   ├── SharingFee.php    (baru)
│   └── JadwalPraktek.php (baru)
│
└── Services/
    └── DokterService.php (baru)

app/Repositories/
└── DokterRepository.php  (baru)

resources/views/
├── components/
│   └── sip-status.blade.php              ← badge SIP status
├── pengaturan/
│   └── dokter/
│       ├── index.blade.php               ← list dokter
│       └── show.blade.php                ← detail + tab nav
└── livewire/
    └── pengaturan/
        └── dokter/
            ├── dokter-table.blade.php
            ├── dokter-profil-form.blade.php
            ├── dokter-poli-mapping.blade.php
            ├── sharing-fee-form.blade.php
            └── jadwal-praktek-manager.blade.php

database/
└── migrations/
    ├── 2026_01_03_000001_update_dokter_add_sip_fields.php
    ├── 2026_01_03_000002_create_dokter_poli_table.php
    ├── 2026_01_03_000003_create_sharing_fee_table.php
    └── 2026_01_03_000004_create_jadwal_praktek_table.php
```

---

## 12. User Stories

| ID | Persona | Skenario | Expected Behavior |
|----|---------|----------|-------------------|
| **US01** | Super Admin | Buka halaman Data Dokter | List semua user role dokter + badge SIP status |
| **US02** | Super Admin | Ada dokter SIP expired | Badge merah "SIP Expired" |
| **US03** | Super Admin | Input NIK duplikat | Service error: "NIK sudah digunakan dokter lain" |
| **US04** | Super Admin | Mapping dokter ke Poli Mata + Poli Umum | 2 record `dokter_poli` dibuat |
| **US05** | Super Admin | Hapus mapping poli yang masih ada jadwal aktif | Error: "Masih ada X jadwal aktif. Nonaktifkan dulu." |
| **US06** | Super Admin | Setup sharing fee: Tindakan 15%, Lab 10% | 4 record `sharing_fee` tersimpan |
| **US07** | Super Admin | Tambah jadwal Senin 08:00–12:00 di Poli Umum | Tersimpan, kuota default 20 |
| **US08** | Super Admin | Tambah jadwal Senin 10:00–14:00 (overlap) | Error: "Jadwal bertabrakan dengan 08:00–12:00" |
| **US09** | Super Admin | Toggle nonaktif jadwal | `is_aktif = false`, bisa diaktifkan lagi |
| **US10** | Admission | Form daftar kunjungan | Dropdown hanya dokter SIP aktif + punya jadwal hari ini |
| **US11** | Kasir | Proses billing | Auto-hitung: `fee = tarif × (persentase / 100)` |

---

## 13. Seed Data Awal

```php
// database/seeders/DokterV3Seeder.php

namespace Database\Seeders;

use App\Models\Dokter;
use App\Models\DokterPoli;
use App\Models\JadwalPraktek;
use App\Models\Poli;
use App\Models\SharingFee;
use App\Models\User;
use Illuminate\Database\Seeder;

class DokterV3Seeder extends Seeder
{
    public function run(): void
    {
        $dokterUser = User::where('email', 'dokter@emr.app')->first();
        if (! $dokterUser) {
            $this->command->warn('⚠ User dokter tidak ditemukan.');
            return;
        }

        $poliUmum = Poli::where('kode', 'UMUM')->first();
        $poliMata = Poli::where('kode', 'MATA')->first();

        if (! $poliUmum) {
            $this->command->warn('⚠ Poli tidak ditemukan. Jalankan PoliSeeder + MasterdataV2Seeder dulu.');
            return;
        }

        // ── 1. Update profil dokter ──────────────────────────
        $dokter = Dokter::where('user_id', $dokterUser->id)->first();
        if (! $dokter) {
            $this->command->warn('⚠ Record dokter tidak ditemukan.');
            return;
        }

        $dokter->update([
            'nik'             => '3201011501850001',
            'no_sip'          => '446/SIP-DU/2024',
            'tgl_expired_sip' => '2026-12-31',
            'spesialisasi'    => 'Umum',
        ]);
        $this->command->info("✓ Profil dokter: {$dokterUser->nama}");

        // ── 2. Mapping Poli ──────────────────────────────────
        $mappingUmum = DokterPoli::firstOrCreate(
            ['dokter_id' => $dokter->id, 'poli_id' => $poliUmum->id],
            ['is_aktif' => true]
        );

        $mappingMata = null;
        if ($poliMata) {
            $mappingMata = DokterPoli::firstOrCreate(
                ['dokter_id' => $dokter->id, 'poli_id' => $poliMata->id],
                ['is_aktif' => true]
            );
        }
        $this->command->info('✓ Mapping Poli: Umum' . ($poliMata ? ' + Mata' : ''));

        // ── 3. Sharing Fee ───────────────────────────────────
        $fees = [
            'tindakan'  => 15,
            'lab'       => 10,
            'radiologi' => 10,
            'peralatan' => 0,
        ];

        foreach ($fees as $kategori => $persen) {
            SharingFee::updateOrCreate(
                ['dokter_id' => $dokter->id, 'kategori' => $kategori],
                ['persentase' => $persen]
            );
        }
        $this->command->info('✓ Sharing Fee: Tindakan 15% · Lab 10% · Rad 10% · Peralatan 0%');

        // ── 4. Jadwal Praktek ────────────────────────────────
        $jadwal = [
            // Poli Umum
            ['dokter_poli_id' => $mappingUmum->id, 'hari' => 'senin',   'jam_mulai' => '08:00', 'jam_selesai' => '12:00', 'kuota_pasien' => 20],
            ['dokter_poli_id' => $mappingUmum->id, 'hari' => 'selasa',  'jam_mulai' => '08:00', 'jam_selesai' => '12:00', 'kuota_pasien' => 20],
            ['dokter_poli_id' => $mappingUmum->id, 'hari' => 'rabu',    'jam_mulai' => '08:00', 'jam_selesai' => '12:00', 'kuota_pasien' => 20],
            ['dokter_poli_id' => $mappingUmum->id, 'hari' => 'kamis',   'jam_mulai' => '13:00', 'jam_selesai' => '17:00', 'kuota_pasien' => 15, 'keterangan' => 'Sesi Sore'],
            ['dokter_poli_id' => $mappingUmum->id, 'hari' => 'jumat',   'jam_mulai' => '08:00', 'jam_selesai' => '11:00', 'kuota_pasien' => 12],
        ];

        if ($mappingMata) {
            $jadwal = array_merge($jadwal, [
                ['dokter_poli_id' => $mappingMata->id, 'hari' => 'senin',  'jam_mulai' => '13:00', 'jam_selesai' => '16:00', 'kuota_pasien' => 10],
                ['dokter_poli_id' => $mappingMata->id, 'hari' => 'kamis',  'jam_mulai' => '08:00', 'jam_selesai' => '11:00', 'kuota_pasien' => 10],
                ['dokter_poli_id' => $mappingMata->id, 'hari' => 'sabtu',  'jam_mulai' => '08:00', 'jam_selesai' => '12:00', 'kuota_pasien' => 15],
            ]);
        }

        foreach ($jadwal as $j) {
            JadwalPraktek::firstOrCreate(
                ['dokter_poli_id' => $j['dokter_poli_id'], 'hari' => $j['hari'], 'jam_mulai' => $j['jam_mulai']],
                $j
            );
        }

        $this->command->info('✓ Jadwal Praktek: ' . count($jadwal) . ' slot');
        $this->command->info('✅ DokterV3Seeder selesai.');
    }
}
```

---

## Appendix — Diagram Relasi (Eloquent)

```
User (role = dokter)
    └── Dokter (1-to-1)
            ├── SharingFee[] (1-to-many, 4 kategori)
            │     tindakan · lab · radiologi · peralatan (%)
            │
            └── DokterPoli[] (many-to-many ke Poli)
                    ├── Poli (Umum / Mata / Bedah / ...)
                    └── JadwalPraktek[]
                            hari · jam_mulai · jam_selesai
                            kuota_pasien · is_aktif

Kalkulasi Billing (via DokterService::hitungSharingFee):
  MasterTindakan.tarif × (SharingFee['tindakan'].persentase / 100) = fee dokter
  ItemPenunjang.tarif  × (SharingFee['lab'].persentase       / 100) = fee dokter

Filter Form Pendaftaran Kunjungan:
  Dokter::aktifDanSipValid()
        ->denganJadwalHari(now()->locale('id')->dayName)
        ->get()
```

---

*masterdata_v3.md v3.0.1 · Laravel 12 + Livewire 3 · Living document*

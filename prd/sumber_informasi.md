# Product Requirements Document (PRD)
# Sumber Informasi Pasien (Patient Acquisition Source)

| Info | Detail |
|:-----|:-------|
| **Versi** | 1.0.0 |
| **Tanggal** | Mei 2026 |
| **Status** | Draft |
| **Depends On** | `PRD_EMR_Laravel.md` · `setup_pasien.md` · `PRD_Laporan_v1.md` |
| **Tech Stack** | Laravel 12 · Livewire 3 · MySQL · Tailwind CSS |
| **Scope** | Pencatatan sumber informasi pasien baru saat registrasi oleh Front Office (FO) |

---

## Daftar Isi

1. [Ringkasan Eksekutif](#1-ringkasan-eksekutif)
2. [Tujuan & Sasaran](#2-tujuan--sasaran)
3. [Role & Hak Akses](#3-role--hak-akses)
4. [Alur Proses](#4-alur-proses)
5. [Daftar Sumber Informasi](#5-daftar-sumber-informasi)
6. [Skema Database & Migration](#6-skema-database--migration)
7. [Model Eloquent](#7-model-eloquent)
8. [Integrasi ke Form Registrasi Pasien](#8-integrasi-ke-form-registrasi-pasien)
9. [Service Layer](#9-service-layer)
10. [Modul Master Sumber Informasi](#10-modul-master-sumber-informasi)
11. [Laporan Sumber Informasi](#11-laporan-sumber-informasi)
12. [Seeder Data Awal](#12-seeder-data-awal)
13. [User Stories & Business Rules](#13-user-stories--business-rules)
14. [Struktur Folder](#14-struktur-folder)

---

## 1. Ringkasan Eksekutif

Modul Sumber Informasi memungkinkan Front Office (FO) mencatat dari mana pasien baru mengetahui klinik/rumah sakit saat proses registrasi pertama kali. Data ini menjadi dasar analisis efektivitas channel marketing (Google, Facebook, Instagram, TikTok, dan lainnya).

```
┌──────────────────────────────────────────────────────────────┐
│         ALUR SUMBER INFORMASI PASIEN BARU                    │
│                                                              │
│  Pasien Baru Datang                                          │
│       │                                                      │
│       ▼                                                      │
│  FO Registrasi Pasien                                        │
│       │                                                      │
│       ├──► [WAJIB] "Dapat informasi dari mana?"             │
│       │         ├── Google                                   │
│       │         ├── Facebook                                 │
│       │         ├── Instagram                                │
│       │         ├── TikTok                                   │
│       │         ├── Referensi Teman/Keluarga                 │
│       │         ├── Spanduk/Brosur                           │
│       │         └── Lainnya (isi keterangan)                 │
│       │                                                      │
│       ▼                                                      │
│  Tersimpan di pasien.sumber_informasi_id                     │
│       │                                                      │
│       ▼                                                      │
│  Laporan Analisis Channel Marketing                          │
│   (per periode, breakdown per sumber)                        │
└──────────────────────────────────────────────────────────────┘
```

---

## 2. Tujuan & Sasaran

### Tujuan Utama
- Mengetahui channel marketing mana yang paling efektif menarik pasien baru
- Mendukung pengambilan keputusan alokasi budget marketing
- Tracking ROI dari kampanye digital (Google Ads, Meta Ads, TikTok Ads)

### Sasaran Teknis
- Field sumber informasi **wajib diisi** saat registrasi pasien baru
- Sumber informasi dikelola sebagai **master data** (bisa ditambah/edit oleh admin)
- Mendukung opsi "Lainnya" dengan keterangan bebas
- Terintegrasi dengan modul laporan untuk analisis periodik

### KPI
- 100% pasien baru memiliki data sumber informasi
- Laporan tersedia per periode (bulanan, triwulan, dst)
- Zero pasien baru tersimpan tanpa sumber informasi

---

## 3. Role & Hak Akses

| Aksi | super_admin | admin | front_office | perawat | dokter |
|------|:-----------:|:-----:|:------------:|:-------:|:------:|
| Input sumber saat registrasi | ✅ | ✅ | ✅ | ✅ | ❌ |
| Lihat sumber di detail pasien | ✅ | ✅ | ✅ | ✅ | 👁 |
| Edit sumber pasien | ✅ | ✅ | ✅ | ❌ | ❌ |
| Kelola master sumber | ✅ | ✅ | ❌ | ❌ | ❌ |
| Lihat laporan sumber | ✅ | ✅ | 👁 | ❌ | ❌ |

> **front_office** adalah role yang melakukan registrasi (dapat dipetakan ke role `admin` atau `rekam_medis` yang sudah ada, atau ditambahkan sebagai role baru di `RolePermissionSeeder`).

### Permissions (Spatie)

```php
$permissions = [
    'sumber_informasi.view',
    'sumber_informasi.manage',     // kelola master data
    'laporan.sumber_informasi.view',
];
```

---

## 4. Alur Proses

```
1. Pasien baru datang ke Front Office
        │
2. FO mulai mengisi form registrasi pasien (PasienForm)
        │
3. Pada section terakhir, muncul pertanyaan WAJIB:
   "Dari mana Anda mengetahui klinik kami?"
        │
        ├── FO memilih salah satu opsi (single select)
        │
        ├── Jika pilih "Lainnya" → muncul field keterangan
        │
4. FO menyelesaikan registrasi
        │
5. Sistem menyimpan:
   - pasien.sumber_informasi_id (FK ke master)
   - pasien.sumber_informasi_keterangan (jika "Lainnya")
        │
6. Data tersedia untuk laporan analisis marketing
```

**Aturan penting:**
- Pertanyaan sumber informasi **hanya muncul saat registrasi pasien BARU** (create mode)
- Saat edit pasien lama, field bisa ditampilkan namun tidak wajib diubah
- Validasi: `sumber_informasi_id` wajib diisi untuk pasien baru

---

## 5. Daftar Sumber Informasi

Default master data yang di-seed:

| Kode | Nama | Kategori | Icon | Aktif |
|------|------|----------|------|:-----:|
| `google` | Google / Pencarian Web | digital | 🔍 | ✅ |
| `facebook` | Facebook | sosial_media | 📘 | ✅ |
| `instagram` | Instagram | sosial_media | 📷 | ✅ |
| `tiktok` | TikTok | sosial_media | 🎵 | ✅ |
| `referensi` | Referensi Teman/Keluarga | word_of_mouth | 👥 | ✅ |
| `spanduk` | Spanduk / Brosur | offline | 📋 | ✅ |
| `whatsapp` | WhatsApp / Broadcast | digital | 💬 | ✅ |
| `lainnya` | Lainnya | lainnya | ➕ | ✅ |

> Opsi "Lainnya" memicu field keterangan bebas. Admin dapat menambah sumber baru (misal: YouTube, papan reklame, event) via master data.

---

## 6. Skema Database & Migration

### 6.1 Tabel `sumber_informasi`

```php
// database/migrations/2026_01_01_000300_create_sumber_informasi_table.php

Schema::create('sumber_informasi', function (Blueprint $table) {
    $table->id();
    $table->string('kode', 30)->unique();           // google, facebook, dll
    $table->string('nama', 100);                     // label tampil
    $table->enum('kategori', [
        'digital',
        'sosial_media',
        'word_of_mouth',
        'offline',
        'lainnya',
    ])->default('lainnya');
    $table->string('icon', 10)->nullable();          // emoji icon
    $table->boolean('butuh_keterangan')->default(false); // true untuk "Lainnya"
    $table->unsignedSmallInteger('urutan')->default(0);  // urutan tampil di form
    $table->boolean('is_active')->default(true);
    $table->timestamps();

    $table->index(['is_active', 'urutan']);
});
```

### 6.2 Update Tabel `pasien` (Tambah Kolom)

```php
// database/migrations/2026_01_01_000301_update_pasien_add_sumber_informasi.php

Schema::table('pasien', function (Blueprint $table) {
    $table->foreignId('sumber_informasi_id')->nullable()
          ->after('is_active')
          ->constrained('sumber_informasi')->nullOnDelete();
    $table->string('sumber_informasi_keterangan', 200)->nullable()
          ->after('sumber_informasi_id'); // diisi jika sumber = "Lainnya"
});
```

> **Catatan migrasi data lama:** Pasien yang sudah ada sebelum modul ini di-deploy akan memiliki `sumber_informasi_id = NULL`. Tidak perlu di-backfill; laporan akan mengelompokkannya sebagai "Tidak Tercatat".

---

## 7. Model Eloquent

### 7.1 Model `SumberInformasi`

```php
// app/Models/SumberInformasi.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SumberInformasi extends Model
{
    protected $table    = 'sumber_informasi';
    protected $fillable = [
        'kode', 'nama', 'kategori', 'icon',
        'butuh_keterangan', 'urutan', 'is_active',
    ];
    protected $casts = [
        'butuh_keterangan' => 'boolean',
        'is_active'        => 'boolean',
    ];

    public function pasien(): HasMany
    {
        return $this->hasMany(Pasien::class, 'sumber_informasi_id');
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true)->orderBy('urutan');
    }

    public function getLabelAttribute(): string
    {
        return trim(($this->icon ? $this->icon . ' ' : '') . $this->nama);
    }
}
```

### 7.2 Update Model `Pasien`

```php
// app/Models/Pasien.php — tambahkan:

use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Tambah ke $fillable:
//   'sumber_informasi_id', 'sumber_informasi_keterangan',

public function sumberInformasi(): BelongsTo
{
    return $this->belongsTo(SumberInformasi::class, 'sumber_informasi_id');
}

// Accessor untuk label sumber (gabung keterangan jika "Lainnya")
public function getSumberInformasiLabelAttribute(): string
{
    if (!$this->sumberInformasi) {
        return 'Tidak Tercatat';
    }

    $label = $this->sumberInformasi->nama;
    if ($this->sumberInformasi->butuh_keterangan && $this->sumber_informasi_keterangan) {
        $label .= ' (' . $this->sumber_informasi_keterangan . ')';
    }

    return $label;
}
```

---

## 8. Integrasi ke Form Registrasi Pasien

### 8.1 Update Livewire `PasienForm`

Menambahkan properti dan logika ke `PasienForm` yang sudah ada di `setup_pasien.md`.

```php
// app/Livewire/Pasien/PasienForm.php — TAMBAHAN

// ── Properti baru ────────────────────────────────
public ?int   $sumberInformasiId = null;
public string $sumberKeterangan  = '';
public bool   $sumberButuhKeterangan = false;

// ── Tambahkan ke rules() ─────────────────────────
protected function rules(): array
{
    $rules = [ /* ... rules existing ... */ ];

    // Sumber informasi WAJIB hanya saat create (pasien baru)
    if (!$this->isEdit) {
        $rules['sumberInformasiId'] = ['required', 'exists:sumber_informasi,id'];

        // Keterangan wajib jika sumber butuh keterangan (Lainnya)
        if ($this->sumberButuhKeterangan) {
            $rules['sumberKeterangan'] = ['required', 'string', 'min:3', 'max:200'];
        }
    }

    return $rules;
}

// ── Tambahkan messages ───────────────────────────
// 'sumberInformasiId.required' => 'Mohon pilih sumber informasi pasien',
// 'sumberKeterangan.required'  => 'Mohon isi keterangan sumber informasi',

// ── Reactive: deteksi pilihan "Lainnya" ──────────
public function updatedSumberInformasiId($value): void
{
    if ($value) {
        $sumber = \App\Models\SumberInformasi::find($value);
        $this->sumberButuhKeterangan = $sumber?->butuh_keterangan ?? false;

        if (!$this->sumberButuhKeterangan) {
            $this->sumberKeterangan = '';
        }
    }
}

// ── Tambahkan ke method save() (bagian array $data) ──
// 'sumber_informasi_id'          => $this->isEdit ? $this->sumberInformasiId : $this->sumberInformasiId,
// 'sumber_informasi_keterangan'  => $this->sumberButuhKeterangan ? $this->sumberKeterangan : null,

// ── Tambahkan ke mount() untuk mode edit ─────────
// if ($pasien?->exists) {
//     $this->sumberInformasiId = $pasien->sumber_informasi_id;
//     $this->sumberKeterangan  = $pasien->sumber_informasi_keterangan ?? '';
//     $this->sumberButuhKeterangan = $pasien->sumberInformasi?->butuh_keterangan ?? false;
// }
```

### 8.2 Blade — Section Sumber Informasi

Ditambahkan sebagai section baru di form registrasi pasien (muncul di bagian akhir, sebelum tombol submit).

```blade
{{-- resources/views/livewire/pasien/pasien-form.blade.php — TAMBAHAN SECTION --}}

{{-- ── Section: Sumber Informasi (hanya tampil saat create) ── --}}
@if(!$isEdit)
<div class="card border-l-4 border-primary-400">
    <div class="card-header">
        <h3 class="text-sm font-semibold text-gray-700">
            📣 Sumber Informasi
            <span class="text-red-500">*</span>
        </h3>
    </div>
    <div class="card-body space-y-4">
        <p class="text-sm text-gray-500">
            Dari mana pasien mengetahui klinik kami?
        </p>

        {{-- Grid pilihan sumber --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
            @foreach($daftarSumber as $sumber)
            <button type="button"
                wire:click="$set('sumberInformasiId', {{ $sumber->id }})"
                class="py-3 px-3 rounded-lg border text-sm font-medium transition-all text-center
                    {{ $sumberInformasiId == $sumber->id
                        ? 'bg-primary-50 border-primary-500 text-primary-700 ring-1 ring-primary-500'
                        : 'border-gray-200 hover:bg-gray-50 text-gray-700' }}">
                <div class="text-lg mb-1">{{ $sumber->icon }}</div>
                {{ $sumber->nama }}
            </button>
            @endforeach
        </div>
        @error('sumberInformasiId') <p class="form-error">{{ $message }}</p> @enderror

        {{-- Field keterangan jika "Lainnya" --}}
        @if($sumberButuhKeterangan)
        <div class="form-group animate-fade-in">
            <label class="form-label">
                Sebutkan sumber lainnya <span class="text-red-500">*</span>
            </label>
            <input type="text" wire:model.blur="sumberKeterangan"
                class="form-input @error('sumberKeterangan') border-red-400 @enderror"
                placeholder="Contoh: YouTube, papan reklame jalan, event kesehatan, dll" />
            @error('sumberKeterangan') <p class="form-error">{{ $message }}</p> @enderror
        </div>
        @endif
    </div>
</div>
@endif
```

```php
// Tambahkan ke method render() di PasienForm:
public function render()
{
    return view('livewire.pasien.pasien-form', [
        'daftarSumber' => \App\Models\SumberInformasi::active()->get(),
    ]);
}
```

### 8.3 Tampilan di Detail Pasien

```blade
{{-- resources/views/livewire/pasien/pasien-detail.blade.php — TAMBAHAN --}}
<div class="flex items-center gap-2 text-sm">
    <span class="text-gray-500">Sumber Informasi:</span>
    <span class="badge badge-primary">
        {{ $pasien->sumberInformasi?->icon }}
        {{ $pasien->sumber_informasi_label }}
    </span>
</div>
```

---

## 9. Service Layer

```php
// app/Services/SumberInformasiService.php

namespace App\Services;

use App\Models\SumberInformasi;

class SumberInformasiService
{
    public function getActiveOptions()
    {
        return SumberInformasi::active()->get();
    }

    public function create(array $data): SumberInformasi
    {
        // Auto-generate kode dari nama jika tidak diisi
        if (empty($data['kode'])) {
            $data['kode'] = \Illuminate\Support\Str::slug($data['nama'], '_');
        }

        return SumberInformasi::create($data);
    }

    public function update(SumberInformasi $sumber, array $data): SumberInformasi
    {
        $sumber->update($data);
        return $sumber->fresh();
    }

    public function toggleActive(SumberInformasi $sumber): SumberInformasi
    {
        // Cegah nonaktifkan "Lainnya" (fallback wajib ada)
        if ($sumber->kode === 'lainnya' && $sumber->is_active) {
            throw new \RuntimeException('Sumber "Lainnya" tidak dapat dinonaktifkan.');
        }

        $sumber->update(['is_active' => !$sumber->is_active]);
        return $sumber;
    }
}
```

---

## 10. Modul Master Sumber Informasi

### 10.1 Livewire — Master Table & Form

```php
// app/Livewire/Pengaturan/SumberInformasi/SumberInformasiTable.php

namespace App\Livewire\Pengaturan\SumberInformasi;

use Livewire\Component;
use App\Models\SumberInformasi;
use App\Services\SumberInformasiService;

class SumberInformasiTable extends Component
{
    public bool   $showForm = false;
    public ?int   $editId   = null;

    // Form fields
    public string $kode     = '';
    public string $nama     = '';
    public string $kategori = 'lainnya';
    public string $icon     = '';
    public bool   $butuhKeterangan = false;
    public int    $urutan   = 0;
    public bool   $isActive = true;

    protected function rules(): array
    {
        return [
            'nama'     => ['required', 'string', 'max:100'],
            'kategori' => ['required', 'in:digital,sosial_media,word_of_mouth,offline,lainnya'],
            'icon'     => ['nullable', 'string', 'max:10'],
            'urutan'   => ['integer', 'min:0'],
        ];
    }

    public function buatBaru(): void
    {
        $this->reset(['editId','kode','nama','kategori','icon','butuhKeterangan','urutan']);
        $this->isActive = true;
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $s = SumberInformasi::findOrFail($id);
        $this->editId          = $s->id;
        $this->kode            = $s->kode;
        $this->nama            = $s->nama;
        $this->kategori        = $s->kategori;
        $this->icon            = $s->icon ?? '';
        $this->butuhKeterangan = $s->butuh_keterangan;
        $this->urutan          = $s->urutan;
        $this->isActive        = $s->is_active;
        $this->showForm        = true;
    }

    public function simpan(SumberInformasiService $service): void
    {
        $this->validate();

        $data = [
            'nama'             => $this->nama,
            'kategori'         => $this->kategori,
            'icon'             => $this->icon ?: null,
            'butuh_keterangan' => $this->butuhKeterangan,
            'urutan'           => $this->urutan,
            'is_active'        => $this->isActive,
        ];

        if ($this->editId) {
            $service->update(SumberInformasi::find($this->editId), $data);
            session()->flash('success', 'Sumber informasi diperbarui.');
        } else {
            $data['kode'] = $this->kode ?: \Illuminate\Support\Str::slug($this->nama, '_');
            $service->create($data);
            session()->flash('success', 'Sumber informasi ditambahkan.');
        }

        $this->showForm = false;
    }

    public function toggleActive(int $id, SumberInformasiService $service): void
    {
        try {
            $service->toggleActive(SumberInformasi::findOrFail($id));
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.pengaturan.sumber-informasi.sumber-informasi-table', [
            'daftar' => SumberInformasi::orderBy('urutan')->get(),
        ]);
    }
}
```

### 10.2 Blade — Master Table

```blade
{{-- resources/views/livewire/pengaturan/sumber-informasi/sumber-informasi-table.blade.php --}}
<div>
    <div class="page-header">
        <div>
            <h1 class="page-title">Master Sumber Informasi</h1>
            <p class="page-subtitle">Kelola pilihan sumber informasi pasien baru</p>
        </div>
        <button type="button" wire:click="buatBaru" class="btn-primary">+ Tambah Sumber</button>
    </div>

    <div class="table-wrapper">
        <table class="table">
            <thead>
                <tr>
                    <th class="w-12">Urutan</th>
                    <th>Icon</th>
                    <th>Nama</th>
                    <th>Kategori</th>
                    <th>Butuh Keterangan</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                @foreach($daftar as $s)
                <tr>
                    <td class="text-center text-gray-400">{{ $s->urutan }}</td>
                    <td class="text-lg">{{ $s->icon }}</td>
                    <td class="font-medium text-gray-900">{{ $s->nama }}</td>
                    <td><span class="badge badge-gray">{{ ucfirst(str_replace('_',' ',$s->kategori)) }}</span></td>
                    <td>{{ $s->butuh_keterangan ? '✓' : '—' }}</td>
                    <td>
                        @if($s->is_active)
                            <span class="badge badge-success">Aktif</span>
                        @else
                            <span class="badge badge-gray">Nonaktif</span>
                        @endif
                    </td>
                    <td>
                        <div class="flex gap-1">
                            <button wire:click="edit({{ $s->id }})" class="btn-warning btn-sm">Edit</button>
                            <button wire:click="toggleActive({{ $s->id }})" class="btn-secondary btn-sm">
                                {{ $s->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                            </button>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Modal Form --}}
    @if($showForm)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
         x-data @click.self="$wire.set('showForm', false)">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-md mx-4">
            <div class="modal-header">
                <h3 class="modal-title">{{ $editId ? 'Edit' : 'Tambah' }} Sumber Informasi</h3>
                <button wire:click="$set('showForm', false)" class="text-gray-400">✕</button>
            </div>
            <div class="modal-body space-y-4">
                <div class="grid grid-cols-3 gap-3">
                    <div class="form-group">
                        <label class="form-label">Icon</label>
                        <input type="text" wire:model="icon" class="form-input text-center" placeholder="🔍" maxlength="2" />
                    </div>
                    <div class="form-group col-span-2">
                        <label class="form-label">Nama <span class="text-red-500">*</span></label>
                        <input type="text" wire:model="nama" class="form-input @error('nama') border-red-400 @enderror" />
                        @error('nama') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Kategori</label>
                    <select wire:model="kategori" class="form-select">
                        <option value="digital">Digital</option>
                        <option value="sosial_media">Sosial Media</option>
                        <option value="word_of_mouth">Word of Mouth</option>
                        <option value="offline">Offline</option>
                        <option value="lainnya">Lainnya</option>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="form-group">
                        <label class="form-label">Urutan</label>
                        <input type="number" wire:model="urutan" class="form-input" min="0" />
                    </div>
                    <div class="form-group flex items-end">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" wire:model="butuhKeterangan" class="form-checkbox" />
                            <span class="text-sm">Butuh keterangan</span>
                        </label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button wire:click="$set('showForm', false)" class="btn-secondary">Batal</button>
                <button wire:click="simpan" class="btn-primary">Simpan</button>
            </div>
        </div>
    </div>
    @endif
</div>
```

---

## 11. Laporan Sumber Informasi

Mengikuti pola `BaseLaporanComponent` dari `PRD_Laporan_v1.md`. Ditambahkan sebagai sub-laporan di kategori **Registrasi**.

### 11.1 Service

```php
// app/Services/Laporan/RegistrasiLaporanService.php — TAMBAHAN method

public function sumberInformasi(\Carbon\Carbon $mulai, \Carbon\Carbon $akhir): array
{
    // Pasien baru yang dibuat dalam periode
    $pasien = \App\Models\Pasien::whereBetween('created_at', [$mulai, $akhir])
        ->with('sumberInformasi')
        ->get();

    $total = $pasien->count();

    // Group per sumber
    $perSumber = $pasien->groupBy(fn($p) => $p->sumberInformasi?->nama ?? 'Tidak Tercatat')
        ->map(fn($g) => [
            'jumlah'    => $g->count(),
            'persen'    => $total > 0 ? round($g->count() / $total * 100, 1) : 0,
            'icon'      => $g->first()->sumberInformasi?->icon ?? '❓',
            'kategori'  => $g->first()->sumberInformasi?->kategori ?? 'lainnya',
        ])
        ->sortByDesc('jumlah');

    // Group per kategori
    $perKategori = $pasien->groupBy(fn($p) => $p->sumberInformasi?->kategori ?? 'lainnya')
        ->map->count()
        ->sortDesc();

    // Detail keterangan "Lainnya"
    $detailLainnya = $pasien
        ->filter(fn($p) => $p->sumberInformasi?->butuh_keterangan && $p->sumber_informasi_keterangan)
        ->groupBy('sumber_informasi_keterangan')
        ->map->count()
        ->sortDesc();

    return [
        'total_pasien_baru' => $total,
        'per_sumber'        => $perSumber,
        'per_kategori'      => $perKategori,
        'detail_lainnya'    => $detailLainnya,
        'tidak_tercatat'    => $pasien->whereNull('sumber_informasi_id')->count(),
    ];
}
```

### 11.2 Livewire Report Component

```php
// app/Livewire/Laporan/Registrasi/SumberInformasiReport.php

namespace App\Livewire\Laporan\Registrasi;

use App\Livewire\Laporan\BaseLaporanComponent;
use App\Services\Laporan\RegistrasiLaporanService;

class SumberInformasiReport extends BaseLaporanComponent
{
    public function mount(): void
    {
        $this->mountPeriode();
    }

    public function generate(): void
    {
        [$mulai, $akhir] = $this->periodeRange;
        $this->hasil = app(RegistrasiLaporanService::class)
            ->sumberInformasi($mulai, $akhir);
    }

    public function render()
    {
        return view('livewire.laporan.registrasi.sumber-informasi-report');
    }
}
```

### 11.3 Blade — Laporan dengan Visualisasi

```blade
{{-- resources/views/livewire/laporan/registrasi/sumber-informasi-report.blade.php --}}
<div class="space-y-5">
    <div class="page-header">
        <div>
            <h1 class="page-title">Laporan Sumber Informasi Pasien</h1>
            <p class="page-subtitle">Analisis channel akuisisi pasien baru</p>
        </div>
    </div>

    <x-laporan.filter-periode />

    @if($hasil)
    <div wire:loading.remove wire:target="generate">

        {{-- Summary --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-5">
            <div class="stat-card">
                <div class="stat-icon bg-blue-50">📊</div>
                <div>
                    <p class="stat-value">{{ number_format($hasil['total_pasien_baru']) }}</p>
                    <p class="stat-label">Total Pasien Baru</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon bg-emerald-50">🏆</div>
                <div>
                    <p class="stat-value">{{ $hasil['per_sumber']->keys()->first() ?? '-' }}</p>
                    <p class="stat-label">Sumber Teratas</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon bg-amber-50">❓</div>
                <div>
                    <p class="stat-value">{{ number_format($hasil['tidak_tercatat']) }}</p>
                    <p class="stat-label">Tidak Tercatat</p>
                </div>
            </div>
        </div>

        {{-- Breakdown per Sumber dengan progress bar --}}
        <div class="card mb-5">
            <div class="card-header"><h3 class="text-sm font-semibold text-gray-700">Distribusi per Sumber</h3></div>
            <div class="card-body space-y-3">
                @foreach($hasil['per_sumber'] as $nama => $data)
                <div>
                    <div class="flex items-center justify-between text-sm mb-1">
                        <span class="font-medium text-gray-700">{{ $data['icon'] }} {{ $nama }}</span>
                        <span class="text-gray-500">{{ $data['jumlah'] }} ({{ $data['persen'] }}%)</span>
                    </div>
                    <div class="w-full bg-gray-100 rounded-full h-2">
                        <div class="bg-primary-500 h-2 rounded-full transition-all"
                             style="width: {{ $data['persen'] }}%"></div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Detail keterangan "Lainnya" --}}
        @if($hasil['detail_lainnya']->count() > 0)
        <div class="card">
            <div class="card-header"><h3 class="text-sm font-semibold text-gray-700">Rincian Sumber "Lainnya"</h3></div>
            <div class="card-body p-0">
                <table class="table">
                    <thead><tr><th>Keterangan</th><th class="text-right">Jumlah</th></tr></thead>
                    <tbody>
                        @foreach($hasil['detail_lainnya'] as $ket => $jml)
                        <tr>
                            <td>{{ $ket }}</td>
                            <td class="text-right font-medium">{{ $jml }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif
    </div>
    @else
    <div class="card">
        <div class="card-body">
            <div class="empty-state">
                <p class="empty-state-text">Pilih periode dan klik "Tampilkan"</p>
            </div>
        </div>
    </div>
    @endif
</div>
```

### 11.4 Route Laporan

```php
// routes/web.php — tambahkan ke group laporan.registrasi
Route::get('/sumber-informasi', [LaporanRegistrasiController::class, 'sumberInformasi'])
     ->name('sumber-informasi')
     ->middleware('permission:laporan.sumber_informasi.view');
```

---

## 12. Seeder Data Awal

```php
// database/seeders/SumberInformasiSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SumberInformasi;

class SumberInformasiSeeder extends Seeder
{
    public function run(): void
    {
        $sumber = [
            ['kode' => 'google',    'nama' => 'Google / Pencarian Web', 'kategori' => 'digital',       'icon' => '🔍', 'urutan' => 1],
            ['kode' => 'facebook',  'nama' => 'Facebook',               'kategori' => 'sosial_media',  'icon' => '📘', 'urutan' => 2],
            ['kode' => 'instagram', 'nama' => 'Instagram',              'kategori' => 'sosial_media',  'icon' => '📷', 'urutan' => 3],
            ['kode' => 'tiktok',    'nama' => 'TikTok',                 'kategori' => 'sosial_media',  'icon' => '🎵', 'urutan' => 4],
            ['kode' => 'referensi', 'nama' => 'Referensi Teman/Keluarga','kategori'=> 'word_of_mouth', 'icon' => '👥', 'urutan' => 5],
            ['kode' => 'spanduk',   'nama' => 'Spanduk / Brosur',       'kategori' => 'offline',       'icon' => '📋', 'urutan' => 6],
            ['kode' => 'whatsapp',  'nama' => 'WhatsApp / Broadcast',   'kategori' => 'digital',       'icon' => '💬', 'urutan' => 7],
            ['kode' => 'lainnya',   'nama' => 'Lainnya',                'kategori' => 'lainnya',       'icon' => '➕', 'urutan' => 99,
             'butuh_keterangan' => true],
        ];

        foreach ($sumber as $s) {
            SumberInformasi::updateOrCreate(['kode' => $s['kode']], $s);
            $this->command->info("✓ Sumber: {$s['icon']} {$s['nama']}");
        }
    }
}
```

```php
// database/seeders/DatabaseSeeder.php — tambahkan:
$this->call(SumberInformasiSeeder::class);
```

```bash
php artisan migrate
php artisan db:seed --class=SumberInformasiSeeder
```

---

## 13. User Stories & Business Rules

| ID | Persona | Skenario | Expected Behavior |
|----|---------|----------|-------------------|
| US01 | FO | Registrasi pasien baru | Section sumber informasi muncul wajib di akhir form |
| US02 | FO | Submit tanpa pilih sumber | Error: "Mohon pilih sumber informasi pasien" |
| US03 | FO | Pilih "Instagram" | Tersimpan `sumber_informasi_id` ke pasien |
| US04 | FO | Pilih "Lainnya" | Field keterangan muncul, wajib diisi min 3 karakter |
| US05 | FO | Pilih "Lainnya" tanpa isi keterangan | Error: "Mohon isi keterangan sumber informasi" |
| US06 | FO | Edit pasien lama | Field sumber tampil tapi tidak wajib (sudah terisi/null) |
| US07 | Admin | Tambah sumber baru "YouTube" | Sumber baru muncul di form registrasi berikutnya |
| US08 | Admin | Nonaktifkan sumber "Spanduk" | Tidak muncul lagi di form registrasi, data lama tetap |
| US09 | Admin | Coba nonaktifkan "Lainnya" | Error: "Sumber Lainnya tidak dapat dinonaktifkan" |
| US10 | Admin | Lihat laporan sumber periode bulanan | Distribusi per sumber dengan progress bar & persentase |
| US11 | Admin | Lihat rincian "Lainnya" di laporan | Breakdown keterangan bebas yang diinput FO |
| US12 | Admin | Pasien lama tanpa sumber di laporan | Dikelompokkan sebagai "Tidak Tercatat" |

---

## 14. Struktur Folder

```
app/
├── Models/
│   ├── SumberInformasi.php          # BARU
│   └── Pasien.php                   # + relasi sumberInformasi
│
├── Livewire/
│   ├── Pasien/
│   │   └── PasienForm.php           # + section sumber informasi
│   ├── Pengaturan/SumberInformasi/
│   │   └── SumberInformasiTable.php # BARU — master CRUD
│   └── Laporan/Registrasi/
│       └── SumberInformasiReport.php # BARU — laporan
│
├── Services/
│   ├── SumberInformasiService.php   # BARU
│   └── Laporan/
│       └── RegistrasiLaporanService.php  # + method sumberInformasi()
│
database/
├── migrations/
│   ├── 2026_01_01_000300_create_sumber_informasi_table.php
│   └── 2026_01_01_000301_update_pasien_add_sumber_informasi.php
└── seeders/
    └── SumberInformasiSeeder.php

resources/views/
├── livewire/
│   ├── pasien/pasien-form.blade.php          # + section sumber
│   ├── pengaturan/sumber-informasi/
│   │   └── sumber-informasi-table.blade.php  # BARU
│   └── laporan/registrasi/
│       └── sumber-informasi-report.blade.php # BARU
```

---

*PRD_Sumber_Informasi.md v1.0.0*  
*Konsisten dengan PRD_EMR_Laravel.md · setup_pasien.md · PRD_Laporan_v1.md*  
*(Laravel 12 · Livewire 3 · MySQL · Tailwind CSS)*
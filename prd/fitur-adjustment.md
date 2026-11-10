# PRD: Fitur Adjustment Stok Barang (fitur-adjustment.md)

| Info | Detail |
|:-----|:-------|
| **Versi** | 1.0.0 |
| **Tanggal** | November 2026 |
| **Status** | Planning |
| **Tech Stack** | Laravel 12 · Livewire 3 · MySQL · Tailwind CSS |
| **Depends On** | `kartu_stok.md` (tabel `mutasi_stok`) · `manajemen_inventory.md` (tabel `barang`) |
| **Scope** | Menu Adjustment untuk manual add/reduce stok dengan mandatory notes & audit trail |
| **Lokasi Menu** | Inventory → Adjustment Stok |

---

## Daftar Isi

1. [Ringkasan Eksekutif](#1-ringkasan-eksekutif)
2. [Tujuan & Sasaran](#2-tujuan--sasaran)
3. [Perbedaan: Adjustment vs Stock Opname](#3-perbedaan-adjustment-vs-stock-opname)
4. [Fitur Utama](#4-fitur-utama)
5. [Skema Data](#5-skema-data)
6. [Business Rules & Validasi](#6-business-rules--validasi)
7. [Service Layer](#7-service-layer)
8. [Livewire Components](#8-livewire-components)
9. [Blade Views](#9-blade-views)
10. [Routes & RBAC](#10-routes--rbac)
11. [Database & Migrations](#11-database--migrations)
12. [Testing Strategy](#12-testing-strategy)
13. [User Stories](#13-user-stories)
14. [Monitoring & Reporting](#14-monitoring--reporting)

---

## 1. Ringkasan Eksekutif

**Fitur Adjustment** adalah menu untuk melakukan penyesuaian stok manual (tambah/kurangi) pada barang tertentu tanpa menjalani proses stock opname lengkap. Digunakan untuk:
- Koreksi stok ketika ditemukan perbedaan kecil saat penerimaan barang
- Penambahan stok karena barang ditemukan kembali
- Pengurangan stok karena barang rusak/hilang/expired tanpa harus full opname
- Adjustment pembulatan stok akibat isu teknis/conversion satuan

Setiap adjustment **wajib disertai catatan lengkap** (alasan, bukti referensi, dll) untuk keperluan audit dan monitoring, dan **otomatis membuat record di kartu stok** (MutasiStok) dengan tipe `penyesuaian_masuk` atau `penyesuaian_keluar`.

---

## 2. Tujuan & Sasaran

### Tujuan Bisnis
- Memberikan fleksibilitas koreksi stok tanpa perlu full stock opname
- Mempertahankan integritas audit trail untuk setiap perubahan stok
- Mempercepat proses koreksi inventory minor issues
- Meningkatkan akurasi stok dengan mencatat alasan setiap perubahan

### Sasaran Teknis
- **Create operation** pada MutasiStok dengan tipe `penyesuaian_masuk/keluar`
- **Mandatory catatan/notes** untuk setiap adjustment
- **Audit trail lengkap**: user, waktu, barang, jumlah, alasan, bukti referensi
- **Kartu stok otomatis terupdate** (MutasiStok terbaca di KartuStokIndex)
- **Dashboard monitoring** untuk lihat adjustment history & filter by user/tanggal/barang
- **Permission-based** menggunakan role `apoteker` atau permission `obat.edit`

---

## 3. Perbedaan: Adjustment vs Stock Opname

### Stock Opname (`StokOpname`)
- **Jenis**: Planned, periodic activity
- **Scope**: Full physical count dari SEMUA barang aktif
- **Workflow**: Draft → Input Fisik → Menunggu Verifikasi → Selesai
- **User**: Min 2 orang (pembuat & verifikator)
- **Output**: Multiple MutasiStok records (satu per barang yang berbeda)
- **Audit**: Ada tahap verifikasi terpisah

### Adjustment Stok (`AdjustmentStok` - fitur baru)
- **Jenis**: Reactive, ad-hoc corrective action
- **Scope**: Barang tertentu saja (1 hingga beberapa)
- **Workflow**: Single-step form → Create & Submit
- **User**: 1 orang (apoteker/gudang)
- **Output**: 1 MutasiStok record per adjustment
- **Audit**: Mandatory notes + auto-recorded timestamp & user

### Tabel Perbandingan

| Aspek | Stock Opname | Adjustment |
|-------|--------------|-----------|
| Trigger | Periode (bulanan/tahunan) | Ad-hoc / Ditemukan selisih |
| Scope | Semua barang | Barang tertentu |
| Mandatory Notes | Opsional | **Wajib** |
| Verifikasi Terpisah | Ya (2-step) | Tidak (1-step) |
| Terikat Nomor Dokumen | OPN-2026-01-0001 | ADJ-2026-11-0001 |
| Interval Pembuat/Verifikator | Ada jeda waktu | Instant |

---

## 4. Fitur Utama

### 4.1 Form Adjustment (Create Page)

**Input Fields**:
- **Barang** (required, searchable dropdown)
  - Tampil: nama, kode, satuan, stok saat ini, HPR
  - Search by: nama, kode, barcode
  
- **Tipe Adjustment** (required, radio/select)
  - ◉ Tambah Stok (masuk)
  - ◉ Kurangi Stok (keluar)
  
- **Jumlah** (required, number > 0)
  - Validasi: tidak boleh negatif, tidak boleh zero
  - Max sesuai max int / capacity gudang
  
- **Catatan** (required, textarea, min 10 karakter)
  - Placeholder: "Contoh: Ditemukan saat stock opname rutin, bukti foto ada"
  - Wajib detailed untuk audit trail
  
- **Bukti Referensi** (optional)
  - Field untuk nomor PO, GR, atau nomor dokumen pendukung
  - Tujuan: traceability & validasi audit
  
- **Attachment** (optional, single file < 5MB)
  - Tipe: PDF, JPG, PNG (foto/dokumen bukti)
  - Penyimpanan: di storage (tidak di DB)

### 4.2 Hasil & Preview
- Sebelum submit, tampilkan preview:
  - Barang: [nama] ([kode])
  - Stok saat ini: X unit
  - Setelah adjustment: Y unit
  - Perubahan: +/- Z unit

### 4.3 History & Monitoring
- **Adjustment List Page**: Tabel daftar semua adjustment
  - Columns: Tanggal, Barang, Tipe (Tambah/Kurangi), Jumlah, Catatan (preview), User, Aksi
  - Filter: by tanggal range, barang, tipe, user
  - Sort: by tanggal (desc)
  - Pagination: 25 per page
  
- **Detail View**: Klik baris untuk lihat detail lengkap
  - Semua field + attachment (jika ada)
  - Timestamp exact (created_at)
  - User yang buat (dengan link ke profile)
  - Meta info: IP address (opsional), device (opsional)

### 4.4 Integration dengan Kartu Stok
- Adjustment otomatis muncul di KartuStokIndex saat filter `tipe_mutasi = penyesuaian_masuk/penyesuaian_keluar`
- Tidak ada aksi khusus, hanya tampil sebagai baris mutasi biasa
- Catatan adjustment tampil di kolom "Keterangan" kartu stok

---

## 5. Skema Data

### Model Baru: `AdjustmentStok`

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AdjustmentStok extends Model
{
    use SoftDeletes;
    
    protected $table = 'adjustment_stok';
    protected $fillable = [
        'nomor_adjustment', // ADJ-2026-11-0001
        'barang_id',        // FK → barang
        'user_id',          // FK → users (pembuat)
        'tipe',             // penyesuaian_masuk | penyesuaian_keluar
        'jumlah',           // qty yang ditambah/dikurangi
        'catatan',          // mandatory notes
        'bukti_referensi',  // optional: nomor PO, GR, dll
        'file_attachment',  // nullable path to storage
        'mutasi_stok_id',   // FK → mutasi_stok (1:1)
        'status',           // 'submitted' (hanya satu status)
        'created_at',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    // Relationships
    public function barang() {
        return $this->belongsTo(Barang::class);
    }

    public function user() {
        return $this->belongsTo(User::class);
    }

    public function mutasiStok() {
        return $this->belongsTo(MutasiStok::class);
    }

    // Helpers
    public static function getTipeLabels(): array {
        return [
            'penyesuaian_masuk'  => 'Adjustment — Tambah',
            'penyesuaian_keluar' => 'Adjustment — Kurangi',
        ];
    }
}
```

### Tabel: `adjustment_stok`

```sql
CREATE TABLE adjustment_stok (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    nomor_adjustment VARCHAR(32) UNIQUE NOT NULL COMMENT 'ADJ-2026-11-0001',
    barang_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    tipe ENUM('penyesuaian_masuk', 'penyesuaian_keluar') NOT NULL,
    jumlah INT UNSIGNED NOT NULL,
    catatan TEXT NOT NULL COMMENT 'Min 10 karakter, alasan detail',
    bukti_referensi VARCHAR(255) NULLABLE,
    file_attachment VARCHAR(255) NULLABLE COMMENT 'path ke storage',
    mutasi_stok_id BIGINT UNSIGNED NULLABLE UNIQUE COMMENT '1:1 dengan mutasi_stok',
    status VARCHAR(32) DEFAULT 'submitted',
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (barang_id) REFERENCES barang(id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (mutasi_stok_id) REFERENCES mutasi_stok(id),
    
    INDEX idx_barang_id (barang_id),
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at),
    INDEX idx_tipe (tipe),
);
```

### Relasi dengan MutasiStok

Saat adjustment dibuat, akan generate record `MutasiStok`:
- `barang_id`: dari adjustment
- `user_id`: dari adjustment
- `tipe`: 'penyesuaian_masuk' atau 'penyesuaian_keluar'
- `jumlah`: dari adjustment
- `stok_sebelum`: stok barang saat itu
- `stok_sesudah`: stok setelah adjustment
- `hpr_sebelum`: hpr barang saat itu
- `hpr_sesudah`: sama dengan hpr_sebelum (adjustment tidak ubah HPR)
- `referensi_tipe`: 'adjustment_stok'
- `referensi_id`: id adjustment
- `keterangan`: "[ADJ-2026-11-0001] " + catatan adjustment

---

## 6. Business Rules & Validasi

### Input Validation

```
Barang
  └─ required
  └─ must exist in barang table
  └─ must be active (is_active = true)

Tipe
  └─ required
  └─ enum: penyesuaian_masuk | penyesuaian_keluar

Jumlah
  └─ required
  └─ integer
  └─ > 0
  └─ <= 999999 (max capacity)
  └─ JIKA tipe = penyesuaian_keluar:
      └─ <= stok barang saat ini (tidak boleh negative)

Catatan
  └─ required
  └─ string
  └─ min 10 karakter
  └─ max 2000 karakter
  └─ tidak boleh copy-paste default text

File Attachment (optional)
  └─ max 5 MB
  └─ allowed: pdf, jpg, jpeg, png
  └─ disimpan di: storage/adjustment/{year}/{month}/{filename}
```

### Business Logic

```
1. Cek permission: user harus punya role 'apoteker' atau permission 'obat.edit'

2. Cek barang:
   - exist & active
   - tampilkan stok_sebelum dari DB

3. Generate nomor_adjustment:
   - Pattern: ADJ-{YYYY}-{MM}-{NNNN}
   - NNNN adalah sequence counter per bulan, leading zero

4. Create transaction:
   - Insert record adjustment_stok
   - Hitung stok_sesudah = stok_sebelum + jumlah (if masuk) 
                         = stok_sebelum - jumlah (if keluar)
   - Validasi stok_sesudah tidak negatif (backup check)
   - Update barang.stok = stok_sesudah
   - Create MutasiStok record dengan referensi ke adjustment
   - Commit atau rollback jika ada error

5. Post-create actions:
   - Fire event AdjustmentCreated (untuk log activity, dll)
   - Return success message: "Adjustment ADJ-2026-11-0001 berhasil dibuat"
```

### Permission & Authorization

```
Role         | Can Create | Can View List | Can View Detail | Can Delete
-------------|-----------|---------------|-----------------|----------
Apoteker     | ✓ (obat.edit) | ✓ (obat.view) | ✓ (obat.view) | ✗
Admin/Manajer| ✓ (admin)  | ✓             | ✓              | ✓ (soft delete)
Gudang Staff | ~ (opsional, dengan obat.edit) | ✓ | ✓ | ✗
Kasir        | ✗          | ✓ (view only) | ✓ (view only)  | ✗
```

**Note**: Delete hanya soft-delete, tidak hard-delete, untuk audit trail.

---

## 7. Service Layer

### `AdjustmentStokService`

```php
// app/Services/Inventory/AdjustmentStokService.php

namespace App\Services\Inventory;

use App\Models\{Barang, AdjustmentStok, MutasiStok};
use Illuminate\Support\Facades\DB;
use Illuminate\Http\UploadedFile;

class AdjustmentStokService
{
    /**
     * Buat adjustment stok baru (1-step, langsung submitted)
     */
    public function buat(array $data, int $userId, ?UploadedFile $file = null): AdjustmentStok
    {
        return DB::transaction(function () use ($data, $userId, $file) {
            // Validasi barang exist & active
            $barang = Barang::where('is_active', true)->findOrFail($data['barang_id']);
            
            $stokSebelum = $barang->stok;
            $isMasuk = $data['tipe'] === 'penyesuaian_masuk';
            $stokSesudah = $isMasuk 
                ? $stokSebelum + $data['jumlah']
                : max(0, $stokSebelum - $data['jumlah']);
            
            // Validasi stok tidak negatif (jika kurangi)
            if (!$isMasuk && $stokSesudah < 0) {
                throw new \DomainException(
                    "Stok tidak boleh negatif. Stok saat ini: {$stokSebelum}, "
                    . "yang dikurangi: {$data['jumlah']}"
                );
            }
            
            // Handle file upload
            $filePath = null;
            if ($file) {
                $filePath = $file->storeAs(
                    'adjustment/' . now()->format('Y/m'),
                    uniqid() . '.' . $file->getClientOriginalExtension(),
                    'private'
                );
            }
            
            // Create adjustment record
            $adjustment = AdjustmentStok::create([
                'nomor_adjustment' => $this->generateNomor(),
                'barang_id'        => $barang->id,
                'user_id'          => $userId,
                'tipe'             => $data['tipe'],
                'jumlah'           => $data['jumlah'],
                'catatan'          => $data['catatan'],
                'bukti_referensi'  => $data['bukti_referensi'] ?? null,
                'file_attachment'  => $filePath,
                'status'           => 'submitted',
            ]);
            
            // Update stok barang
            $barang->update(['stok' => $stokSesudah]);
            
            // Create MutasiStok record
            $mutasi = MutasiStok::create([
                'barang_id'      => $barang->id,
                'user_id'        => $userId,
                'tipe'           => $data['tipe'],
                'jumlah'         => $data['jumlah'],
                'stok_sebelum'   => $stokSebelum,
                'stok_sesudah'   => $stokSesudah,
                'hpr_sebelum'    => $barang->harga_pokok,
                'hpr_sesudah'    => $barang->harga_pokok,
                'referensi_tipe' => 'adjustment_stok',
                'referensi_id'   => $adjustment->id,
                'keterangan'     => "[{$adjustment->nomor_adjustment}] {$adjustment->catatan}",
            ]);
            
            // Link adjustment ke mutasi
            $adjustment->update(['mutasi_stok_id' => $mutasi->id]);
            
            return $adjustment;
        });
    }
    
    /**
     * Get adjustment dengan relasi
     */
    public function getById(int $id): AdjustmentStok
    {
        return AdjustmentStok::with(['barang', 'user', 'mutasiStok'])
            ->findOrFail($id);
    }
    
    /**
     * Soft delete adjustment (tidak hard delete)
     */
    public function delete(AdjustmentStok $adjustment): void
    {
        // Implementasi soft delete - revert stok jika diperlukan
        DB::transaction(function () use ($adjustment) {
            // Opsi 1: Revert barang stok ke stok_sebelum
            $mutasi = $adjustment->mutasiStok;
            if ($mutasi) {
                $barang = $adjustment->barang;
                $barang->update(['stok' => $mutasi->stok_sebelum]);
                $mutasi->delete(); // soft delete mutasi juga
            }
            $adjustment->delete(); // soft delete adjustment
        });
    }
    
    /**
     * Generate nomor adjustment sequential
     * Format: ADJ-2026-11-0001
     */
    private function generateNomor(): string
    {
        $prefix = 'ADJ-' . now()->format('Y-m-');
        $last = AdjustmentStok::where('nomor_adjustment', 'like', $prefix . '%')
            ->orderByDesc('nomor_adjustment')
            ->value('nomor_adjustment');
        
        $seq = $last ? (int) substr($last, -4) + 1 : 1;
        return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }
}
```

---

## 8. Livewire Components

### `AdjustmentCreate` — Form Input

```php
// app/Livewire/Inventory/Adjustment/AdjustmentCreate.php

namespace App\Livewire\Inventory\Adjustment;

use App\Models\Barang;
use App\Services\Inventory\AdjustmentStokService;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Validation\ValidationException;

class AdjustmentCreate extends Component
{
    use WithFileUploads;
    
    public ?int $barangId = null;
    public string $tipe = 'penyesuaian_masuk';
    public ?int $jumlah = null;
    public string $catatan = '';
    public string $buktiReferensi = '';
    public ?object $fileAttachment = null;
    
    // Preview data
    public ?object $barangPreview = null;
    public ?int $stokSebelum = null;
    public ?int $stokSesudah = null;
    
    // Search
    public string $searchBarang = '';
    
    public function authorize(): bool
    {
        return auth()->user()->hasPermissionTo('obat.edit') || 
               auth()->user()->hasRole('apoteker');
    }
    
    #[Computed]
    public function barangSuggestions()
    {
        if (strlen($this->searchBarang) < 2) return collect();
        
        return Barang::where('is_active', true)
            ->where(fn ($q) =>
                $q->where('nama', 'like', "%{$this->searchBarang}%")
                  ->orWhere('kode', 'like', "%{$this->searchBarang}%"))
            ->select('id', 'kode', 'nama', 'satuan', 'stok', 'harga_pokok')
            ->limit(10)
            ->get();
    }
    
    public function selectBarang(int $id): void
    {
        $barang = Barang::findOrFail($id);
        $this->barangId = $id;
        $this->searchBarang = $barang->nama;
        $this->barangPreview = $barang;
        $this->stokSebelum = $barang->stok;
        $this->hitung();
    }
    
    public function resetBarang(): void
    {
        $this->barangId = null;
        $this->searchBarang = '';
        $this->barangPreview = null;
        $this->stokSebelum = null;
        $this->stokSesudah = null;
    }
    
    public function hitung(): void
    {
        if (!$this->stokSebelum || !$this->jumlah) {
            $this->stokSesudah = null;
            return;
        }
        
        $this->stokSesudah = $this->tipe === 'penyesuaian_masuk'
            ? $this->stokSebelum + $this->jumlah
            : max(0, $this->stokSebelum - $this->jumlah);
    }
    
    #[Computed]
    public function isValid(): bool
    {
        return $this->barangId !== null && 
               $this->jumlah > 0 && 
               strlen($this->catatan) >= 10;
    }
    
    public function submit(AdjustmentStokService $service): void
    {
        $this->validate([
            'barangId'      => 'required|integer|exists:barang,id',
            'tipe'          => 'required|in:penyesuaian_masuk,penyesuaian_keluar',
            'jumlah'        => 'required|integer|min:1|max:999999',
            'catatan'       => 'required|string|min:10|max:2000',
            'buktiReferensi'=> 'nullable|string|max:255',
            'fileAttachment'=> 'nullable|file|max:5120|mimes:pdf,jpg,jpeg,png',
        ], [
            'jumlah.min' => 'Jumlah harus minimal 1 unit.',
            'catatan.min' => 'Catatan minimal 10 karakter.',
        ]);
        
        try {
            $service->buat(
                [
                    'barang_id'      => $this->barangId,
                    'tipe'           => $this->tipe,
                    'jumlah'         => $this->jumlah,
                    'catatan'        => $this->catatan,
                    'bukti_referensi'=> $this->buktiReferensi ?: null,
                ],
                auth()->id(),
                $this->fileAttachment
            );
            
            session()->flash('success', 'Adjustment berhasil dibuat!');
            $this->resetForm();
            redirect()->route('inventory.adjustment.index');
        } catch (\DomainException $e) {
            throw ValidationException::withMessages(['jumlah' => $e->getMessage()]);
        }
    }
    
    private function resetForm(): void
    {
        $this->reset(['barangId', 'tipe', 'jumlah', 'catatan', 
                     'buktiReferensi', 'fileAttachment', 'searchBarang',
                     'barangPreview', 'stokSebelum', 'stokSesudah']);
    }
    
    public function render()
    {
        return view('livewire.inventory.adjustment.adjustment-create');
    }
}
```

### `AdjustmentIndex` — List & Monitoring

```php
// app/Livewire/Inventory/Adjustment/AdjustmentIndex.php

namespace App\Livewire\Inventory\Adjustment;

use App\Models\AdjustmentStok;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class AdjustmentIndex extends Component
{
    use WithPagination;
    
    #[Url]
    public string $tanggalMulai = '';
    #[Url]
    public string $tanggalAkhir = '';
    #[Url]
    public string $tipe = '';
    #[Url]
    public string $search = '';
    
    protected $paginationTheme = 'tailwind';
    
    public function mount(): void
    {
        $this->tanggalMulai = now()->startOfMonth()->format('Y-m-d');
        $this->tanggalAkhir = now()->format('Y-m-d');
    }
    
    #[Computed]
    public function adjustments()
    {
        return AdjustmentStok::with(['barang', 'user'])
            ->whereBetween('created_at', [
                $this->tanggalMulai . ' 00:00:00',
                $this->tanggalAkhir . ' 23:59:59'
            ])
            ->when($this->tipe, fn ($q) => $q->where('tipe', $this->tipe))
            ->when($this->search, fn ($q) =>
                $q->whereHas('barang', fn ($b) =>
                    $b->where('nama', 'like', "%{$this->search}%")
                      ->orWhere('kode', 'like', "%{$this->search}%")
                )
            )
            ->orderByDesc('created_at')
            ->paginate(25);
    }
    
    public function render()
    {
        return view('livewire.inventory.adjustment.adjustment-index');
    }
}
```

### `AdjustmentDetail` — View Detail

```php
// app/Livewire/Inventory/Adjustment/AdjustmentDetail.php

namespace App\Livewire\Inventory\Adjustment;

use App\Models\AdjustmentStok;
use Livewire\Component;

class AdjustmentDetail extends Component
{
    public ?AdjustmentStok $adjustment = null;
    
    public function mount(int $id): void
    {
        $this->adjustment = AdjustmentStok::with(['barang', 'user', 'mutasiStok'])
            ->findOrFail($id);
    }
    
    public function render()
    {
        return view('livewire.inventory.adjustment.adjustment-detail');
    }
}
```

---

## 9. Blade Views

### Layout: `resources/views/inventory/adjustment.blade.php`

```blade
<x-app-layout>
    <x-slot name="title">Adjustment Stok Barang</x-slot>
    
    <div class="page-header">
        <div>
            <h2 class="page-title">Adjustment Stok Barang</h2>
            <p class="page-subtitle">
                Penyesuaian stok manual dengan catatan wajib untuk keperluan audit
            </p>
        </div>
        <div>
            <a href="{{ route('inventory.adjustment.create') }}" class="btn-primary">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                          d="M12 4v16m8-8H4"/>
                </svg>
                <span>Adjustment Baru</span>
            </a>
        </div>
    </div>
    
    @php $tab = request()->query('tab', 'list'); @endphp
    
    <div class="border-b border-gray-200 dark:border-gray-700 mb-6">
        <nav class="flex gap-0 -mb-px">
            @foreach ([
                'list'   => 'Daftar Adjustment',
                'create' => 'Buat Adjustment',
            ] as $key => $label)
            <a href="?tab={{ $key }}"
               @class(['px-5 py-3 text-sm font-medium border-b-2 whitespace-nowrap transition-colors',
                   'border-[#0a3d62] text-[#0a3d62]' => $tab === $key,
                   'border-transparent text-gray-500 hover:text-gray-700' => $tab !== $key])>
                {{ $label }}
            </a>
            @endforeach
        </nav>
    </div>
    
    @switch($tab)
        @case('list')
            <livewire:inventory.adjustment.adjustment-index />
            @break
        @case('create')
            <livewire:inventory.adjustment.adjustment-create />
            @break
    @endswitch
</x-app-layout>
```

### Component: `resources/views/livewire/inventory/adjustment/adjustment-create.blade.php`

```blade
<div class="space-y-6">
    
    {{-- Info Box --}}
    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 
                rounded-lg p-4 text-sm text-blue-800 dark:text-blue-300">
        <p class="font-medium mb-2">ℹ️ Panduan Adjustment Stok</p>
        <ul class="list-disc list-inside space-y-1 text-xs">
            <li>Gunakan hanya untuk koreksi stok minor (barang hilang, rusak, atau ditemukan)</li>
            <li>Untuk full inventory recount, gunakan menu <strong>Stock Opname</strong></li>
            <li>Catatan wajib disi lengkap untuk keperluan audit trail</li>
        </ul>
    </div>
    
    {{-- Form --}}
    <form wire:submit="submit" class="space-y-5">
        
        {{-- Barang Selection --}}
        <div class="card">
            <div class="card-header">
                <h3 class="text-sm font-semibold dark:text-white">1. Pilih Barang</h3>
            </div>
            <div class="card-body space-y-4">
                
                <div class="form-group relative">
                    <label class="form-label dark:text-gray-300">Barang <span class="text-red-500">*</span></label>
                    <input wire:model.live.debounce.400ms="searchBarang" type="text"
                           placeholder="Cari nama atau kode barang..."
                           class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
                    @error('barangId') <p class="form-error">{{ $message }}</p> @enderror
                    
                    {{-- Suggestions --}}
                    @if ($this->barangSuggestions->isNotEmpty() && !$barangId)
                    <div class="absolute z-20 top-full left-0 right-0 mt-1 bg-white dark:bg-gray-800 
                                rounded-xl border border-gray-200 dark:border-gray-600 shadow-lg 
                                max-h-60 overflow-y-auto">
                        @foreach ($this->barangSuggestions as $b)
                        <button type="button" wire:click="selectBarang({{ $b->id }})"
                                class="w-full text-left px-4 py-3 hover:bg-blue-50 dark:hover:bg-gray-700 
                                       border-b border-gray-100 dark:border-gray-700 last:border-0 
                                       transition-colors">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="font-medium text-sm text-gray-900 dark:text-gray-100">{{ $b->nama }}</p>
                                    <p class="text-xs text-gray-500 font-mono">{{ $b->kode }}</p>
                                </div>
                                <div class="flex items-center gap-2 text-xs">
                                    <span class="text-gray-500">{{ $b->satuan }}</span>
                                    <span class="font-semibold text-gray-700 dark:text-gray-300">
                                        Stok: {{ $b->stok }}
                                    </span>
                                </div>
                            </div>
                        </button>
                        @endforeach
                    </div>
                    @endif
                    
                    {{-- Barang Dipilih --}}
                    @if ($barangPreview)
                    <div class="mt-3 rounded-lg bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 
                                dark:border-emerald-700 p-4">
                        <div class="flex items-start justify-between mb-3">
                            <div>
                                <p class="font-semibold text-emerald-900 dark:text-emerald-100">
                                    ✓ {{ $barangPreview->nama }}
                                </p>
                                <p class="text-xs text-emerald-700 dark:text-emerald-300 font-mono">
                                    {{ $barangPreview->kode }}
                                </p>
                            </div>
                            <button type="button" wire:click="resetBarang" 
                                    class="text-xs text-red-500 hover:text-red-700 font-medium">
                                Ganti
                            </button>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-3 text-sm">
                            <div class="bg-white dark:bg-gray-700 rounded p-2">
                                <p class="text-xs text-gray-600 dark:text-gray-400 mb-1">Satuan</p>
                                <p class="font-semibold text-gray-900 dark:text-gray-100">
                                    {{ $barangPreview->satuan }}
                                </p>
                            </div>
                            <div class="bg-white dark:bg-gray-700 rounded p-2">
                                <p class="text-xs text-gray-600 dark:text-gray-400 mb-1">Stok Saat Ini</p>
                                <p class="font-bold text-lg text-gray-900 dark:text-gray-100">
                                    {{ $stokSebelum }}
                                </p>
                            </div>
                        </div>
                    </div>
                    @endif
                    
                </div>
            </div>
        </div>
        
        {{-- Tipe & Jumlah --}}
        <div class="card">
            <div class="card-header">
                <h3 class="text-sm font-semibold dark:text-white">2. Tipe & Jumlah Adjustment</h3>
            </div>
            <div class="card-body space-y-4">
                
                <div class="form-group">
                    <label class="form-label dark:text-gray-300">Tipe Adjustment <span class="text-red-500">*</span></label>
                    <div class="flex gap-4">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" wire:model.live="tipe" value="penyesuaian_masuk" 
                                   class="w-4 h-4">
                            <span class="text-sm text-gray-700 dark:text-gray-300">
                                ➕ Tambah Stok (Masuk)
                            </span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" wire:model.live="tipe" value="penyesuaian_keluar" 
                                   class="w-4 h-4">
                            <span class="text-sm text-gray-700 dark:text-gray-300">
                                ➖ Kurangi Stok (Keluar)
                            </span>
                        </label>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label dark:text-gray-300">Jumlah <span class="text-red-500">*</span></label>
                    <input wire:model.live="jumlah" type="number" min="1" max="999999"
                           placeholder="Berapa unit yang ditambah/dikurangi?"
                           class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
                    @error('jumlah') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                
                {{-- Preview --}}
                @if ($stokSebelum !== null && $jumlah)
                <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg border border-gray-200 
                            dark:border-gray-600 p-4 text-sm">
                    <div class="grid grid-cols-3 gap-4 text-center">
                        <div>
                            <p class="text-xs text-gray-600 dark:text-gray-400 mb-1">Stok Sebelum</p>
                            <p class="text-xl font-bold text-gray-900 dark:text-gray-100">
                                {{ $stokSebelum }}
                            </p>
                        </div>
                        <div class="flex items-center justify-center">
                            <p class="text-2xl font-bold text-gray-400">
                                {{ $tipe === 'penyesuaian_masuk' ? '+' : '−' }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-600 dark:text-gray-400 mb-1">Stok Sesudah</p>
                            <p class="text-xl font-bold {{ $stokSesudah === 0 ? 'text-red-600' : 'text-emerald-600' }}">
                                {{ $stokSesudah ?? '—' }}
                            </p>
                        </div>
                    </div>
                    @if ($tipe === 'penyesuaian_keluar' && $stokSesudah === 0)
                    <p class="text-xs text-amber-600 dark:text-amber-400 mt-3">
                        ⚠️ Perhatian: Stok barang akan habis setelah adjustment ini
                    </p>
                    @endif
                </div>
                @endif
            </div>
        </div>
        
        {{-- Catatan & Referensi --}}
        <div class="card">
            <div class="card-header">
                <h3 class="text-sm font-semibold dark:text-white">3. Catatan & Bukti</h3>
            </div>
            <div class="card-body space-y-4">
                
                <div class="form-group">
                    <label class="form-label dark:text-gray-300">
                        Catatan Detail <span class="text-red-500">*</span>
                        <span class="text-xs text-gray-500 font-normal">(minimal 10 karakter)</span>
                    </label>
                    <textarea wire:model="catatan" rows="4"
                              placeholder="Jelaskan alasan adjustment ini. Contoh: Ditemukan saat stock opname rutin di rak gudang belakang, kondisi barang masih bagus."
                              class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"></textarea>
                    @error('catatan') <p class="form-error">{{ $message }}</p> @enderror
                    <p class="text-xs text-gray-500 mt-2">
                        Karakter: {{ strlen($catatan) }} / 2000
                    </p>
                </div>
                
                <div class="form-group">
                    <label class="form-label dark:text-gray-300">
                        Bukti Referensi (opsional)
                    </label>
                    <input wire:model="buktiReferensi" type="text"
                           placeholder="Nomor GR, PO, atau referensi dokumen lainnya"
                           class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
                    @error('buktiReferensi') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                
                <div class="form-group">
                    <label class="form-label dark:text-gray-300">
                        Attachment (opsional)
                    </label>
                    <input wire:model="fileAttachment" type="file"
                           accept=".pdf,.jpg,.jpeg,.png"
                           class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
                    @error('fileAttachment') <p class="form-error">{{ $message }}</p> @enderror
                    <p class="text-xs text-gray-500 mt-2">
                        Format: PDF, JPG, PNG | Max: 5 MB
                    </p>
                    @if ($fileAttachment)
                    <p class="text-xs text-emerald-600 mt-2">
                        ✓ File dipilih: {{ $fileAttachment->getClientOriginalName() }}
                    </p>
                    @endif
                </div>
            </div>
        </div>
        
        {{-- Tombol Submit --}}
        <div class="flex justify-between gap-3">
            <a href="{{ route('inventory.adjustment.index') }}" class="btn btn-secondary">
                Batal
            </a>
            <button type="submit" 
                    @disabled(!$this->isValid)
                    class="btn btn-primary disabled:opacity-50 disabled:cursor-not-allowed"
                    wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="submit">
                    Buat Adjustment
                </span>
                <span wire:loading wire:target="submit" class="flex items-center gap-2">
                    <div class="spinner h-4 w-4 border-white border-t-transparent"></div>
                    Memproses...
                </span>
            </button>
        </div>
    </form>
</div>
```

### Component: `resources/views/livewire/inventory/adjustment/adjustment-index.blade.php`

```blade
<div class="space-y-5">
    
    {{-- Filter Panel --}}
    <div class="card">
        <div class="card-body space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                <div class="form-group">
                    <label class="form-label text-xs dark:text-gray-300">Dari Tanggal</label>
                    <input wire:model.live="tanggalMulai" type="date"
                           class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
                </div>
                <div class="form-group">
                    <label class="form-label text-xs dark:text-gray-300">Sampai Tanggal</label>
                    <input wire:model.live="tanggalAkhir" type="date"
                           class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
                </div>
                <div class="form-group">
                    <label class="form-label text-xs dark:text-gray-300">Tipe</label>
                    <select wire:model.live="tipe"
                            class="form-select dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200">
                        <option value="">Semua Tipe</option>
                        <option value="penyesuaian_masuk">Tambah Stok</option>
                        <option value="penyesuaian_keluar">Kurangi Stok</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label text-xs dark:text-gray-300">Cari Barang</label>
                    <input wire:model.live.debounce.400ms="search" type="text"
                           placeholder="Nama / kode barang..."
                           class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
                </div>
            </div>
        </div>
    </div>
    
    {{-- Tabel --}}
    <div class="table-wrapper">
        <table class="table">
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Nomor</th>
                    <th>Barang</th>
                    <th>Tipe</th>
                    <th class="text-center">Jumlah</th>
                    <th>Catatan</th>
                    <th>User</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->adjustments as $adj)
                <tr wire:key="adj-{{ $adj->id }}">
                    <td class="text-sm text-gray-600 dark:text-gray-400">
                        {{ $adj->created_at->format('d/m/Y H:i') }}
                    </td>
                    <td class="font-mono text-xs text-gray-500">
                        {{ $adj->nomor_adjustment }}
                    </td>
                    <td class="font-medium text-gray-900 dark:text-gray-100">
                        {{ $adj->barang->nama }}
                    </td>
                    <td>
                        @php
                            $isMasuk = $adj->tipe === 'penyesuaian_masuk';
                        @endphp
                        <span @class([
                            'badge',
                            'badge-success' => $isMasuk,
                            'badge-danger' => !$isMasuk,
                        ])>
                            {{ $isMasuk ? '➕ Tambah' : '➖ Kurangi' }}
                        </span>
                    </td>
                    <td class="text-center font-semibold text-gray-900 dark:text-gray-100">
                        {{ $adj->jumlah }} {{ $adj->barang->satuan }}
                    </td>
                    <td class="text-sm text-gray-600 dark:text-gray-400 max-w-xs truncate">
                        {{ substr($adj->catatan, 0, 50) }}{{ strlen($adj->catatan) > 50 ? '...' : '' }}
                    </td>
                    <td class="text-sm text-gray-600 dark:text-gray-400">
                        {{ $adj->user->nama }}
                    </td>
                    <td class="text-center">
                        <a href="{{ route('inventory.adjustment.show', $adj->id) }}"
                           class="btn-info btn-sm">
                            Lihat
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8">
                        <div class="empty-state py-10">
                            <p class="empty-state-text">Tidak ada adjustment dalam periode ini</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    
    {{-- Pagination --}}
    <div class="flex justify-center">
        {{ $this->adjustments->links() }}
    </div>
</div>
```

---

## 10. Routes & RBAC

### Routes

```php
// routes/web.php — tambah ke group inventory

Route::prefix('inventory')->name('inventory.')->middleware(['auth','active','permission:obat.view'])->group(function () {
    
    // ... existing routes ...
    
    // Adjustment Stok
    Route::prefix('adjustment')->name('adjustment.')->group(function () {
        Route::get('/', fn () => view('inventory.adjustment'))->name('index');
        
        Route::middleware('permission:obat.edit')->group(function () {
            Route::get('/create', fn () => view('inventory.adjustment', ['tab' => 'create']))
                 ->name('create');
        });
        
        Route::get('/{adjustment}', [\App\Http\Controllers\Inventory\AdjustmentController::class, 'show'])
             ->name('show');
    });
});
```

### Permission Requirement

Tambah permission ke `database/seeders/RolePermissionSeeder.php`:

```php
// Permission sudah ada (obat.create, obat.edit), tidak perlu tambah baru
// Cek: obat.view (untuk list), obat.edit (untuk create)

// Roles yang dapat create adjustment:
// - apoteker: punya obat.edit
// - admin: punya semua permission
```

---

## 11. Database & Migrations

### Migration File

```php
// database/migrations/2026_11_10_000001_create_adjustment_stok_table.php

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('adjustment_stok', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_adjustment', 32)->unique();
            $table->foreignId('barang_id')->constrained();
            $table->foreignId('user_id')->constrained('users');
            $table->enum('tipe', ['penyesuaian_masuk', 'penyesuaian_keluar']);
            $table->unsignedInteger('jumlah');
            $table->text('catatan');
            $table->string('bukti_referensi')->nullable();
            $table->string('file_attachment')->nullable();
            $table->foreignId('mutasi_stok_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('status')->default('submitted');
            $table->timestamps();
            $table->softDeletes(); // untuk audit trail; lihat catatan di bawah
            
            $table->index('barang_id');
            $table->index('user_id');
            $table->index('created_at');
            $table->index('tipe');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adjustment_stok');
    }
};
```

**PENTING**: 
- `MutasiStok` model tidak punya `SoftDeletes` trait. Jika delete adjustment, hard-delete `MutasiStok` yang direferensikan (bukan soft-delete).
- Update method `delete()` di `AdjustmentStokService` untuk `$mutasi->forceDelete()` daripada `$mutasi->delete()`.
- Soft-delete adjustment sendiri tetap berfungsi untuk audit trail.

```php
// Revisi AdjustmentStokService->delete()
public function delete(AdjustmentStok $adjustment): void
{
    DB::transaction(function () use ($adjustment) {
        $mutasi = $adjustment->mutasiStok;
        if ($mutasi) {
            $barang = $adjustment->barang;
            $barang->update(['stok' => $mutasi->stok_sebelum]);
            $mutasi->forceDelete(); // Hard delete, karena MutasiStok tidak punya SoftDeletes
        }
        $adjustment->delete(); // Soft delete adjustment
    });
}
```

---

## 12. Testing Strategy

### Unit Tests

```php
// tests/Unit/Services/AdjustmentStokServiceTest.php

namespace Tests\Unit\Services;

use App\Models\{AdjustmentStok, Barang, MutasiStok, User};
use App\Services\Inventory\AdjustmentStokService;
use Tests\TestCase;

class AdjustmentStokServiceTest extends TestCase
{
    private AdjustmentStokService $service;
    private Barang $barang;
    private User $user;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AdjustmentStokService::class);
        // NOTE: Barang model tidak punya factory, gunakan create() langsung
        $this->barang = Barang::create([
            'kode' => 'TEST-' . uniqid(),
            'nama' => 'Barang Test',
            'satuan' => 'Tablet',
            'stok' => 100,
            'harga_pokok' => 5000,
            'is_active' => true,
        ]);
        $this->user = User::factory()->create();
    }
    
    /** @test */
    public function buat_penyesuaian_masuk(): void
    {
        $adj = $this->service->buat([
            'barang_id' => $this->barang->id,
            'tipe'      => 'penyesuaian_masuk',
            'jumlah'    => 20,
            'catatan'   => 'Ditemukan barang di gudang belakang',
        ], $this->user->id);
        
        $this->assertDatabaseHas('adjustment_stok', [
            'nomor_adjustment' => $adj->nomor_adjustment,
            'barang_id'        => $this->barang->id,
            'tipe'             => 'penyesuaian_masuk',
            'jumlah'           => 20,
        ]);
        
        $this->assertEquals(120, $this->barang->fresh()->stok);
        
        $this->assertDatabaseHas('mutasi_stok', [
            'barang_id'      => $this->barang->id,
            'tipe'           => 'penyesuaian_masuk',
            'jumlah'         => 20,
            'stok_sebelum'   => 100,
            'stok_sesudah'   => 120,
            'referensi_tipe' => 'adjustment_stok',
            'referensi_id'   => $adj->id,
        ]);
    }
    
    /** @test */
    public function buat_penyesuaian_keluar(): void
    {
        $adj = $this->service->buat([
            'barang_id' => $this->barang->id,
            'tipe'      => 'penyesuaian_keluar',
            'jumlah'    => 15,
            'catatan'   => 'Barang rusak saat pengiriman',
        ], $this->user->id);
        
        $this->assertEquals(85, $this->barang->fresh()->stok);
    }
    
    /** @test */
    public function validasi_stok_tidak_negatif(): void
    {
        $this->expectException(\DomainException::class);
        
        $this->service->buat([
            'barang_id' => $this->barang->id,
            'tipe'      => 'penyesuaian_keluar',
            'jumlah'    => 150, // Lebih besar dari stok (100)
            'catatan'   => 'Test',
        ], $this->user->id);
    }
}
```

### Feature Tests

```php
// tests/Feature/AdjustmentStokFeatureTest.php

namespace Tests\Feature;

use App\Livewire\Inventory\Adjustment\AdjustmentCreate;
use App\Models\{Barang, User};
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class AdjustmentStokFeatureTest extends TestCase
{
    use DatabaseTransactions;
    
    /** @test */
    public function user_dengan_obat_edit_bisa_create_adjustment(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('obat.edit');
        // NOTE: Barang tidak punya factory
        $barang = Barang::create([
            'kode' => 'TEST-' . uniqid(),
            'nama' => 'Barang Test',
            'satuan' => 'Tablet',
            'stok' => 100,
            'harga_pokok' => 5000,
            'is_active' => true,
        ]);
        
        $this->actingAs($user);
        
        Livewire::test(AdjustmentCreate::class)
            ->call('selectBarang', $barang->id)
            ->set('tipe', 'penyesuaian_masuk')
            ->set('jumlah', 20)
            ->set('catatan', 'Ditemukan di gudang belakang lengkap')
            ->call('submit')
            ->assertHasNoErrors();
        
        $this->assertDatabaseHas('adjustment_stok', [
            'barang_id' => $barang->id,
            'jumlah'    => 20,
        ]);
    }
    
    /** @test */
    public function user_tanpa_permission_ditolak(): void
    {
        $user = User::factory()->create();
        $barang = Barang::factory()->create();
        
        $this->actingAs($user);
        
        $response = $this->get(route('inventory.adjustment.create'));
        $response->assertForbidden();
    }
}
```

---

## 13. User Stories

| ID | Persona | Skenario | Expected Behavior |
|----|---------|----------|-------------------|
| **US01** | Apoteker | Temukan stok Paracetamol bertambah 10 tablet saat penerimaan barang | Buat adjustment masuk + catatan bukti & kondisi barang |
| **US02** | Gudang | Barang Amoxicillin rusak 5 blister | Kurangi stok via adjustment + foto kerusakan attachment |
| **US03** | Admin | Cek siapa saja yang pernah adjust stok Insulin bulan ini | Filter list adjustment by barang + tanggal, lihat nama user |
| **US04** | Apoteker | Adjustment pembulatan stok karena conversion satuan PO | Catatan jelas + referensi PO di bukti_referensi |
| **US05** | Admin | Kartu stok per barang harus include adjustment history | Adjustment muncul di KartuStokIndex with tipe penyesuaian |
| **US06** | Gudang | Tidak boleh kurangi stok melebihi yang tersedia | Submit ditolak + pesan error jelas |
| **US07** | Apoteker | List adjustment menampilkan 3 bulan terakhir default | Default tanggalMulai = 3 bulan lalu, tanggalAkhir = hari ini |

---

## 14. Monitoring & Reporting

### Dashboard Metrics (Future Enhancement)

```
- Total adjustment per bulan (grafik)
- Top 10 barang paling sering di-adjust
- Adjustment masuk vs keluar ratio
- User dengan aktivitas terbanyak
```

### Accounting Integration (Future Enhancement)

Setelah dasar adjustment stok selesai, implementasi fase 2 adalah extend `InventoriJurnalService`:
- Tambah method `catatAdjustment(AdjustmentStok $adj)` mirip `catatStokOpname()`
- Gunakan akun `selisih_opname` (8-1100) seperti opname
- Entry akan: Dr. Persediaan/Selisih Opname | Cr. Persediaan/Selisih Opname
- Trigger automatic saat adjustment dibuat (event listener)

### Audit Trail Requirements

✓ Setiap adjustment harus tercatat:
  - User (who)
  - Tanggal & waktu exact (when)
  - Barang & jumlah (what)
  - Alasan/catatan (why)
  - Bukti referensi (evidence)

✓ Integration dengan kartu stok:
  - MutasiStok otomatis tercipta
  - Catatan adjustment tampil di kartu stok
  - Bisa filter by tipe = penyesuaian_masuk/keluar

✓ Soft delete:
  - Adjustment tidak pernah hard-delete
  - Jika delete, revert stok & soft-delete MutasiStok
  - Audit log tetap tercatat

---

## Summary

**Fitur Adjustment Stok** adalah tool standalone (bukan bagian StokOpname) yang memungkinkan koreksi stok ad-hoc dengan:
- ✓ Single-step workflow (form → submit → selesai)
- ✓ Mandatory detailed notes untuk audit trail
- ✓ Automatic MutasiStok creation (tipe penyesuaian_masuk/keluar)
- ✓ Integration seamless dengan KartuStok (read-only lookup)
- ✓ Permission-based access (obat.edit)
- ✓ Full monitoring & traceability (user, waktu, alasan, bukti)

**Tech Stack**: Laravel 12 + Livewire 3 + Tailwind CSS

**Status**: Ready for implementation

---

*fitur-adjustment.md v1.0.0 · November 2026 · Depends on kartu_stok.md & manajemen_inventory.md*

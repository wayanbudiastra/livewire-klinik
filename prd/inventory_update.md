# Product Requirements Document (PRD)
# Inventory Update — v2.0

| Info | Detail |
|:-----|:-------|
| **Versi** | 2.0.0 |
| **Tanggal** | Mei 2026 |
| **Status** | Draft |
| **Depends On** | `manajemen_inventory.md` · `kartu_stok.md` · `PRD_Akuntansi.md` (tahap lanjut) |
| **Tech Stack** | Laravel 12 · Livewire 3 · MySQL · Tailwind CSS · DomPDF · Maatwebsite Excel |
| **Scope** | PO Mapping Supplier · Validasi Stok Real · Pemakaian BHP · Stok Opname · Fondasi Akuntansi Inventori |

---

## Daftar Isi

1. [Ringkasan Eksekutif](#1-ringkasan-eksekutif)
2. [Kondisi Saat Ini & Gap Analysis](#2-kondisi-saat-ini--gap-analysis)
3. [Fitur 1 — PO Mapping Barang per Supplier](#3-fitur-1--po-mapping-barang-per-supplier)
4. [Fitur 2 — Validasi Stok Real di Kartu Stok](#4-fitur-2--validasi-stok-real-di-kartu-stok)
5. [Fitur 3 — Pemakaian Bahan Habis Pakai (BHP)](#5-fitur-3--pemakaian-bahan-habis-pakai-bhp)
6. [Fitur 4 — Stok Opname](#6-fitur-4--stok-opname)
7. [Fitur 5 — Fondasi Akuntansi Inventori](#7-fitur-5--fondasi-akuntansi-inventori)
8. [Skema Database & Migration](#8-skema-database--migration)
9. [Model Eloquent](#9-model-eloquent)
10. [Service Layer](#10-service-layer)
11. [Livewire Components](#11-livewire-components)
12. [Route & Controller](#12-route--controller)
13. [Role & Hak Akses](#13-role--hak-akses)
14. [User Stories & Business Rules](#14-user-stories--business-rules)
15. [Seeder Data Awal](#15-seeder-data-awal)
16. [Urutan Implementasi](#16-urutan-implementasi)

---

## 1. Ringkasan Eksekutif

Modul ini merupakan peningkatan (update) terhadap sistem inventori yang sudah berjalan. Empat fitur baru ditambahkan dan satu fitur (kartu stok) diperbaiki:

```
┌─────────────────────────────────────────────────────────────────────┐
│              ALUR INVENTORI SETELAH UPDATE                          │
│                                                                     │
│  Master Barang ──► Mapping Barang-Supplier ──► PO Efisien          │
│       │                                           │                 │
│       ▼                                           ▼                 │
│  Stok Real (tervalidasi) ◄──── GR Terverifikasi ◄── PO             │
│       │                                                             │
│       ├──── Keluar Resep (tervalidasi stok)                        │
│       ├──── Keluar Tindakan (tervalidasi stok)                     │
│       ├──── Keluar BHP ──────────────────────┐                     │
│       └──── Stok Opname (rekonsiliasi fisik) │                     │
│                                               │                     │
│       Semua transaksi ──► Mutasi Stok ────────┘                    │
│                               │                                     │
│                               ▼                                     │
│                    Hook Akuntansi (jurnal siap posting)             │
└─────────────────────────────────────────────────────────────────────┘
```

---

## 2. Kondisi Saat Ini & Gap Analysis

### ✅ Sudah Ada (tidak diubah)
| Fitur | Status |
|-------|--------|
| Supplier CRUD | ✅ Production-ready |
| Barang Master CRUD | ✅ Production-ready |
| Tabel `supplier_barang` (mapping M:M) | ✅ Ada, tapi kurang dimanfaatkan di form PO |
| Purchase Order (PO) — buat & approve | ✅ Production-ready |
| Goods Receipt (GR) — terima & verifikasi | ✅ Production-ready |
| HPR Moving Average otomatis saat GR verifikasi | ✅ Production-ready |
| Mutasi Stok audit trail (7 tipe) | ✅ Production-ready |
| Kartu Stok report | ✅ Production-ready |
| Alert Stok Kritis | ✅ Production-ready |

### ❌ Gap Yang Diperbaiki di PRD Ini

| Gap | Dampak | Prioritas |
|-----|--------|-----------|
| Form PO cari barang satu-per-satu, tidak ada tampilan daftar barang terikat supplier | Input PO lambat, rawan salah pilih barang | 🔴 Tinggi |
| Tidak ada validasi stok minimum saat dispensing resep/tindakan | Stok bisa negatif (`barang.stok < 0`) | 🔴 Tinggi |
| Tidak ada modul Pemakaian BHP | Konsumsi bahan habis pakai tidak tercatat | 🟠 Sedang |
| Tidak ada Stok Opname | Tidak bisa rekonsiliasi stok fisik vs sistem | 🟠 Sedang |
| Transaksi inventori tidak terhubung ke hook akuntansi | Modul akuntansi tidak bisa membaca jurnal inventori | 🟡 Rendah (infrastruktur) |

---

## 3. Fitur 1 — PO Mapping Barang per Supplier

### 3.1 Masalah

Saat ini di form PO (`PoForm.php`), user mencari barang satu per satu lewat input text. Ini lambat dan tidak memanfaatkan tabel `supplier_barang` yang sudah ada (yang menyimpan daftar barang per supplier beserta kode & harga terakhir dari supplier tersebut).

### 3.2 Solusi — PO Mapping Grid

Setelah supplier dipilih, tampilkan grid daftar barang yang sudah di-mapping ke supplier tersebut via `supplier_barang`. User bisa:
- **Check/uncheck** barang yang ingin dipesan
- **Input jumlah** langsung di grid (inline edit)
- **Harga otomatis** dari `supplier_barang.harga_terakhir`
- **Tetap bisa tambah barang di luar mapping** jika diperlukan

### 3.3 Alur UX

```
1. Pilih Supplier
        ↓
2. Grid muncul: daftar barang terikat supplier (dari supplier_barang)
   Kolom: [ ] Nama Barang | Kode Supplier | Stok Saat Ini | Harga Terakhir | Jumlah Pesan | Harga Satuan | Diskon% | Subtotal
        ↓
3. User centang + isi jumlah pesan
        ↓
4. Klik "Tambahkan ke PO" → item masuk ke tabel draft PO
        ↓
5. Opsional: tambah barang lain via search (di luar mapping)
        ↓
6. Submit PO
```

### 3.4 Livewire Component — Update `PoForm.php`

```php
// app/Livewire/Inventory/Po/PoForm.php — TAMBAHAN

// State baru
public array  $barangMapping    = [];   // daftar barang dari supplier_barang
public array  $selectedMapping  = [];   // [barang_id => ['jumlah' => 0, 'harga' => 0, 'diskon' => 0]]
public bool   $showMappingGrid  = false;

// Dipanggil saat supplier dipilih
public function updatedSupplierId($value): void
{
    $this->showMappingGrid = false;
    $this->selectedMapping = [];

    if (!$value) {
        $this->barangMapping = [];
        return;
    }

    // Load daftar barang mapping dari supplier ini
    $this->barangMapping = \App\Models\SupplierBarang::with('barang:id,kode,nama,satuan,stok,stok_minimum')
        ->where('supplier_id', $value)
        ->get()
        ->map(fn ($sb) => [
            'barang_id'            => $sb->barang_id,
            'kode'                 => $sb->barang->kode,
            'nama'                 => $sb->barang->nama,
            'satuan'               => $sb->barang->satuan,
            'stok_saat_ini'        => $sb->barang->stok,
            'stok_minimum'         => $sb->barang->stok_minimum,
            'kode_barang_supplier' => $sb->kode_barang_supplier,
            'harga_terakhir'       => $sb->harga_terakhir ?? 0,
        ])
        ->values()
        ->toArray();

    $this->showMappingGrid = !empty($this->barangMapping);
}

// Toggle pilih/hapus barang dari mapping grid
public function toggleMappingItem(int $barangId): void
{
    if (isset($this->selectedMapping[$barangId])) {
        unset($this->selectedMapping[$barangId]);
    } else {
        // Temukan default harga dari barangMapping
        $item = collect($this->barangMapping)->firstWhere('barang_id', $barangId);
        $this->selectedMapping[$barangId] = [
            'jumlah' => 1,
            'harga'  => $item['harga_terakhir'],
            'diskon' => 0,
        ];
    }
}

// Tambahkan item terpilih dari mapping ke draft PO items
public function tambahDariMapping(): void
{
    foreach ($this->selectedMapping as $barangId => $detail) {
        if ($detail['jumlah'] <= 0) continue;

        // Hindari duplikat
        $exists = collect($this->items)->firstWhere('barang_id', $barangId);
        if ($exists) continue;

        $barang = \App\Models\Barang::find($barangId);
        $this->items[] = [
            'barang_id'    => $barangId,
            'nama_barang'  => $barang->nama,
            'satuan'       => $barang->satuan,
            'jumlah_pesan' => $detail['jumlah'],
            'harga_satuan' => $detail['harga'],
            'diskon_persen'=> $detail['diskon'],
            'subtotal'     => $detail['jumlah'] * $detail['harga'] * (1 - $detail['diskon'] / 100),
        ];
    }

    $this->selectedMapping  = [];
    $this->hitungTotal();
    $this->showMappingGrid  = false;
}
```

### 3.5 Blade — Mapping Grid

```blade
{{-- Tampil setelah supplier dipilih --}}
@if($showMappingGrid && !empty($barangMapping))
<div class="card mt-4">
    <div class="card-header">
        <h3 class="text-sm font-semibold text-gray-700">
            Barang Terikat Supplier
            <span class="badge badge-primary ml-1">{{ count($barangMapping) }} item</span>
        </h3>
        <button type="button" wire:click="tambahDariMapping" class="btn-primary btn-sm"
            @disabled(empty($selectedMapping))>
            + Tambahkan ke PO ({{ count($selectedMapping) }})
        </button>
    </div>
    <div class="card-body p-0 max-h-96 overflow-y-auto">
        <table class="table text-sm">
            <thead class="sticky top-0 bg-white">
                <tr>
                    <th class="w-10"></th>
                    <th>Barang</th>
                    <th class="text-center">Stok</th>
                    <th class="text-right">Harga Terakhir</th>
                    <th class="w-28">Jumlah Pesan</th>
                    <th class="w-28">Harga Satuan</th>
                    <th class="w-16">Diskon%</th>
                </tr>
            </thead>
            <tbody>
                @foreach($barangMapping as $item)
                @php $dipilih = isset($selectedMapping[$item['barang_id']]); @endphp
                <tr class="{{ $dipilih ? 'bg-primary-50' : '' }}">
                    <td class="text-center">
                        <input type="checkbox"
                            wire:click="toggleMappingItem({{ $item['barang_id'] }})"
                            @checked($dipilih)
                            class="form-checkbox" />
                    </td>
                    <td>
                        <p class="font-medium">{{ $item['nama'] }}</p>
                        @if($item['kode_barang_supplier'])
                            <p class="text-xs text-gray-400">Kode: {{ $item['kode_barang_supplier'] }}</p>
                        @endif
                    </td>
                    <td class="text-center">
                        <span class="{{ $item['stok_saat_ini'] <= $item['stok_minimum'] ? 'text-red-500 font-medium' : 'text-gray-700' }}">
                            {{ $item['stok_saat_ini'] }} {{ $item['satuan'] }}
                        </span>
                    </td>
                    <td class="text-right text-gray-600">
                        Rp {{ number_format($item['harga_terakhir'], 0, ',', '.') }}
                    </td>
                    <td>
                        @if($dipilih)
                        <input type="number"
                            wire:model="selectedMapping.{{ $item['barang_id'] }}.jumlah"
                            class="form-input text-sm py-1 px-2 w-full"
                            min="1" step="1" />
                        @endif
                    </td>
                    <td>
                        @if($dipilih)
                        <input type="number"
                            wire:model="selectedMapping.{{ $item['barang_id'] }}.harga"
                            class="form-input text-sm py-1 px-2 w-full"
                            min="0" step="0.01" />
                        @endif
                    </td>
                    <td>
                        @if($dipilih)
                        <input type="number"
                            wire:model="selectedMapping.{{ $item['barang_id'] }}.diskon"
                            class="form-input text-sm py-1 px-2 w-full"
                            min="0" max="100" step="0.1" />
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif
```

### 3.6 Business Rules

```
✓ Grid mapping HANYA tampil setelah supplier dipilih dan ada mapping-nya
✓ Barang yang stok ≤ stok_minimum ditandai merah (prioritas order)
✓ Harga default = harga_terakhir dari supplier_barang (bisa diubah user)
✓ Barang dari mapping bisa tidak dipilih (tidak wajib pesan semua)
✓ Setelah "Tambahkan ke PO", item ditambahkan ke draft items — tidak boleh duplikat
✓ User TETAP BISA tambah barang di luar mapping via search (existing behavior)
✓ Saat GR terverifikasi, supplier_barang.harga_terakhir diperbarui (existing logic, tidak berubah)
```

---

## 4. Fitur 2 — Validasi Stok Real di Kartu Stok

### 4.1 Masalah

`barang.stok` bisa menjadi negatif karena tidak ada pengecekan stok sebelum pengeluaran di:
- Dispensing resep (`keluar_resep`)
- Pemakaian tindakan (`keluar_tindakan`)
- Pemakaian BHP (fitur baru)

### 4.2 Solusi — Guard di Service Layer

Tambahkan static method `Barang::pastikanCukup()` dan panggil di setiap titik pengeluaran stok.

```php
// app/Models/Barang.php — TAMBAHAN

/**
 * Memastikan stok mencukupi sebelum transaksi keluar.
 * Gunakan lockForUpdate() untuk mencegah race condition.
 *
 * @throws \DomainException jika stok tidak cukup
 */
public static function pastikanCukup(int $barangId, float $jumlah): self
{
    $barang = static::lockForUpdate()->findOrFail($barangId);

    if ($barang->stok < $jumlah) {
        throw new \DomainException(
            "Stok {$barang->nama} tidak mencukupi. " .
            "Tersedia: {$barang->stok} {$barang->satuan}, " .
            "Diminta: {$jumlah} {$barang->satuan}."
        );
    }

    return $barang;
}
```

### 4.3 Titik Integrasi

| Lokasi | Tipe Mutasi | Action |
|--------|-------------|--------|
| `FarmasiService::dispenseResep()` atau yang setara | `keluar_resep` | Panggil `Barang::pastikanCukup()` dalam DB::transaction |
| `KunjunganService` / tindakan recording | `keluar_tindakan` | Panggil `Barang::pastikanCukup()` dalam DB::transaction |
| `PemakaianBhpService::verifikasi()` (fitur baru) | `keluar_bhp` | Sudah dibuatkan di Fitur 3 |

```php
// Contoh integrasi di service dispensing resep:
DB::transaction(function () use ($resepItem) {
    $barang = Barang::pastikanCukup($resepItem->barang_id, $resepItem->jumlah);

    $stokBefore = $barang->stok;
    $hprBefore  = $barang->harga_pokok;

    $barang->decrement('stok', $resepItem->jumlah);

    MutasiStok::create([
        'barang_id'     => $barang->id,
        'user_id'       => auth()->id(),
        'tipe'          => 'keluar_resep',
        'jumlah'        => $resepItem->jumlah,
        'stok_sebelum'  => $stokBefore,
        'stok_sesudah'  => $stokBefore - $resepItem->jumlah,
        'hpr_sebelum'   => $hprBefore,
        'hpr_sesudah'   => $hprBefore,  // HPR tidak berubah saat keluar
        'referensi_tipe'=> 'resep',
        'referensi_id'  => $resepItem->resep_id,
        'keterangan'    => 'Dispensing resep No. ' . $resepItem->resep->nomor_resep,
    ]);
});
```

### 4.4 Enum Tambahan di `mutasi_stok.tipe`

```php
// Migration tambahan tipe enum
// Tambah: 'keluar_bhp' ke enum tipe di mutasi_stok

Schema::table('mutasi_stok', function (Blueprint $table) {
    DB::statement("ALTER TABLE mutasi_stok MODIFY COLUMN tipe ENUM(
        'masuk_pembelian',
        'keluar_resep',
        'keluar_tindakan',
        'keluar_bhp',          -- BARU
        'penyesuaian_masuk',
        'penyesuaian_keluar',
        'retur_ke_supplier',
        'expired'
    ) NOT NULL");
});
```

### 4.5 UI Feedback

Saat stok tidak cukup, tampilkan error yang informatif di form:

```blade
{{-- Contoh di ResepForm --}}
@error('stok_tidak_cukup')
<div class="alert alert-danger mt-2">
    <p class="font-medium">Stok Tidak Mencukupi</p>
    <p class="text-sm">{{ $message }}</p>
</div>
@enderror
```

### 4.6 Business Rules

```
✓ Stok TIDAK BOLEH negatif — throw DomainException sebelum decrement
✓ Validasi menggunakan lockForUpdate() untuk mencegah concurrent race condition
✓ Error ditampilkan di UI dengan nama barang + stok tersedia + jumlah diminta
✓ Semua transaksi pengeluaran wajib di dalam DB::transaction()
✓ MutasiStok wajib dibuat untuk SETIAP pengeluaran stok
✓ Kartu Stok otomatis akurat karena stok tidak bisa negatif
```

---

## 5. Fitur 3 — Pemakaian Bahan Habis Pakai (BHP)

### 5.1 Konsep

Pemakaian Bahan Habis Pakai adalah pengeluaran barang (jenis `bahan_habis_pakai`) dari gudang untuk kebutuhan operasional klinik — **bukan** untuk pasien tertentu. Contoh: sarung tangan, masker, alkohol, kasa, dll.

```
Gudang ──► Pemakaian BHP ──► Mutasi Stok (keluar_bhp) ──► HPP Operasional
                                      │
                                      └──► Hook Akuntansi
                                           Dr. Biaya BHP
                                           Cr. Persediaan BHP
```

### 5.2 Alur Proses

```
1. Staf membuat dokumen Pemakaian BHP (tanggal, catatan)
   Status: draft
        ↓
2. Tambahkan item BHP yang dipakai (barang, jumlah, keterangan)
   → Sistem cek stok mencukupi (real-time)
        ↓
3. Verifikasi / Submit dokumen
   → Stok barang berkurang otomatis
   → Mutasi Stok tipe keluar_bhp dibuat
   → Hook akuntansi dipanggil
   Status: selesai
        ↓
4. Tidak ada pembatalan setelah verifikasi
   (jika salah, buat Stok Opname untuk koreksi)
```

### 5.3 Database Schema

```php
// 2026_05_27_200001_create_pemakaian_bhp_table.php

Schema::create('pemakaian_bhp', function (Blueprint $table) {
    $table->id();
    $table->string('nomor_bhp', 30)->unique();       // BHP-2026-05-0001
    $table->foreignId('dicatat_oleh')
          ->constrained('users')->onDelete('restrict');
    $table->foreignId('diverifikasi_oleh')->nullable()
          ->constrained('users')->onDelete('restrict');
    $table->date('tanggal_pemakaian');
    $table->enum('status', ['draft', 'selesai', 'dibatalkan'])->default('draft');
    $table->text('catatan')->nullable();
    $table->timestamps();

    $table->index(['tanggal_pemakaian', 'status']);
});

// 2026_05_27_200002_create_pemakaian_bhp_item_table.php

Schema::create('pemakaian_bhp_item', function (Blueprint $table) {
    $table->id();
    $table->foreignId('pemakaian_bhp_id')
          ->constrained('pemakaian_bhp')->onDelete('cascade');
    $table->foreignId('barang_id')
          ->constrained('barang')->onDelete('restrict');
    $table->decimal('jumlah', 10, 2);
    $table->decimal('harga_pokok_saat_itu', 14, 2)->default(0); // snapshot HPR
    $table->decimal('nilai_total', 14, 2)->default(0);           // jumlah × HPR
    $table->text('keterangan')->nullable();
    $table->timestamps();

    $table->index('pemakaian_bhp_id');
});
```

### 5.4 Model

```php
// app/Models/PemakaianBhp.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class PemakaianBhp extends Model
{
    protected $table    = 'pemakaian_bhp';
    protected $fillable = [
        'nomor_bhp', 'dicatat_oleh', 'diverifikasi_oleh',
        'tanggal_pemakaian', 'status', 'catatan',
    ];

    protected function casts(): array
    {
        return ['tanggal_pemakaian' => 'date'];
    }

    public function pencatat():    BelongsTo { return $this->belongsTo(User::class, 'dicatat_oleh'); }
    public function verifikator(): BelongsTo { return $this->belongsTo(User::class, 'diverifikasi_oleh'); }
    public function items():       HasMany   { return $this->hasMany(PemakaianBhpItem::class); }

    public function getTotalNilaiAttribute(): float
    {
        return (float) $this->items->sum('nilai_total');
    }
}
```

```php
// app/Models/PemakaianBhpItem.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PemakaianBhpItem extends Model
{
    protected $table    = 'pemakaian_bhp_item';
    protected $fillable = [
        'pemakaian_bhp_id', 'barang_id', 'jumlah',
        'harga_pokok_saat_itu', 'nilai_total', 'keterangan',
    ];

    protected function casts(): array
    {
        return [
            'jumlah'               => 'decimal:2',
            'harga_pokok_saat_itu' => 'decimal:2',
            'nilai_total'          => 'decimal:2',
        ];
    }

    public function pemakaianBhp(): BelongsTo { return $this->belongsTo(PemakaianBhp::class); }
    public function barang():       BelongsTo { return $this->belongsTo(Barang::class); }
}
```

### 5.5 Service

```php
// app/Services/Inventory/PemakaianBhpService.php

namespace App\Services\Inventory;

use App\Models\{PemakaianBhp, PemakaianBhpItem, Barang, MutasiStok};
use App\Services\Akuntansi\InventoriJurnalService; // Fitur 5
use Illuminate\Support\Facades\DB;

class PemakaianBhpService
{
    public function buatDraft(array $data, int $userId): PemakaianBhp
    {
        return PemakaianBhp::create([
            'nomor_bhp'        => $this->generateNomor(),
            'dicatat_oleh'     => $userId,
            'tanggal_pemakaian'=> $data['tanggal_pemakaian'],
            'catatan'          => $data['catatan'] ?? null,
            'status'           => 'draft',
        ]);
    }

    public function tambahItem(PemakaianBhp $bhp, int $barangId, float $jumlah, ?string $keterangan = null): PemakaianBhpItem
    {
        if ($bhp->status !== 'draft') {
            throw new \DomainException('Hanya dokumen draft yang bisa ditambah item.');
        }

        $barang = Barang::findOrFail($barangId);

        if ($barang->jenis !== 'bahan_habis_pakai') {
            throw new \DomainException("Barang '{$barang->nama}' bukan bahan habis pakai.");
        }

        return $bhp->items()->create([
            'barang_id'            => $barangId,
            'jumlah'               => $jumlah,
            'harga_pokok_saat_itu' => $barang->harga_pokok,
            'nilai_total'          => $jumlah * $barang->harga_pokok,
            'keterangan'           => $keterangan,
        ]);
    }

    public function verifikasi(PemakaianBhp $bhp, int $userId): PemakaianBhp
    {
        if ($bhp->status !== 'draft') {
            throw new \DomainException('Hanya dokumen draft yang bisa diverifikasi.');
        }

        if ($bhp->items->isEmpty()) {
            throw new \DomainException('Dokumen BHP tidak boleh kosong.');
        }

        return DB::transaction(function () use ($bhp, $userId) {
            foreach ($bhp->items as $item) {
                // Validasi stok mencukupi
                $barang = Barang::pastikanCukup($item->barang_id, $item->jumlah);

                $stokBefore = $barang->stok;
                $hprBefore  = $barang->harga_pokok;

                $barang->decrement('stok', $item->jumlah);

                MutasiStok::create([
                    'barang_id'      => $item->barang_id,
                    'user_id'        => $userId,
                    'tipe'           => 'keluar_bhp',
                    'jumlah'         => $item->jumlah,
                    'stok_sebelum'   => $stokBefore,
                    'stok_sesudah'   => $stokBefore - $item->jumlah,
                    'hpr_sebelum'    => $hprBefore,
                    'hpr_sesudah'    => $hprBefore,
                    'referensi_tipe' => 'pemakaian_bhp',
                    'referensi_id'   => $bhp->id,
                    'keterangan'     => "Pemakaian BHP {$bhp->nomor_bhp}",
                ]);

                // Update snapshot nilai jika HPR berubah sejak draft
                $item->update(['nilai_total' => $item->jumlah * $barang->fresh()->harga_pokok]);
            }

            $bhp->update([
                'status'            => 'selesai',
                'diverifikasi_oleh' => $userId,
            ]);

            // Hook akuntansi (Fitur 5)
            app(InventoriJurnalService::class)->catatPemakaianBhp($bhp->fresh(['items']));

            return $bhp->fresh();
        });
    }

    private function generateNomor(): string
    {
        $prefix = 'BHP-' . now()->format('Y-m-');
        $last   = PemakaianBhp::where('nomor_bhp', 'like', $prefix . '%')
                    ->orderByDesc('nomor_bhp')->value('nomor_bhp');
        $seq    = $last ? (int) substr($last, -4) + 1 : 1;
        return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }
}
```

### 5.6 Livewire Component

```
app/Livewire/Inventory/Bhp/
├── BhpTable.php       — daftar dokumen BHP dengan filter tanggal & status
└── BhpForm.php        — buat & verifikasi dokumen BHP
```

**`BhpTable.php`** — kolom: Nomor BHP, Tanggal, Dicatat Oleh, Jumlah Item, Total Nilai, Status, Aksi (Lihat / Verifikasi)

**`BhpForm.php`** — properties:
- `tanggalPemakaian`, `catatan` — header dokumen
- `items[]` — dynamic item list: barang_id, nama_barang, jumlah, harga_pokok_saat_itu, nilai_total, keterangan
- `searchBarang` — auto-complete filter jenis `bahan_habis_pakai` saja
- `cekStok(barangId, jumlah)` — real-time stok check tanpa submit

---

## 6. Fitur 4 — Stok Opname

### 6.1 Konsep

Stok Opname adalah proses penghitungan fisik persediaan untuk direkonsiliasi dengan data sistem. Jika ada selisih, koreksi dicatat sebagai `penyesuaian_masuk` atau `penyesuaian_keluar` di `mutasi_stok`.

```
Inisiasi Opname ──► Cetak Form Hitung ──► Input Stok Fisik
                                               │
                                               ▼
                              Sistem hitung selisih (fisik - sistem)
                                               │
                         ┌─────────────────────┤
                         ▼                     ▼
                  Selisih Lebih          Selisih Kurang
                  (fisik > sistem)       (fisik < sistem)
                  penyesuaian_masuk      penyesuaian_keluar
                         │                     │
                         └──────────┬──────────┘
                                    ▼
                             Verifikasi Manager
                                    │
                                    ▼
                         Stok sistem diperbarui
                         Mutasi Stok dibuat
                         Hook Akuntansi dipanggil
```

### 6.2 Database Schema

```php
// 2026_05_27_200003_create_stok_opname_table.php

Schema::create('stok_opname', function (Blueprint $table) {
    $table->id();
    $table->string('nomor_opname', 30)->unique();      // OPN-2026-05-0001
    $table->foreignId('dibuat_oleh')
          ->constrained('users')->onDelete('restrict');
    $table->foreignId('diverifikasi_oleh')->nullable()
          ->constrained('users')->onDelete('restrict');
    $table->date('tanggal_opname');
    $table->string('keterangan_periode', 100)->nullable(); // contoh: "Opname Mei 2026"
    $table->enum('status', ['draft', 'menunggu_verifikasi', 'selesai', 'dibatalkan'])
          ->default('draft');
    $table->text('catatan')->nullable();
    $table->timestamps();

    $table->index(['tanggal_opname', 'status']);
});

// 2026_05_27_200004_create_stok_opname_item_table.php

Schema::create('stok_opname_item', function (Blueprint $table) {
    $table->id();
    $table->foreignId('stok_opname_id')
          ->constrained('stok_opname')->onDelete('cascade');
    $table->foreignId('barang_id')
          ->constrained('barang')->onDelete('restrict');

    $table->decimal('stok_sistem', 10, 2);    // snapshot stok saat opname dibuat
    $table->decimal('stok_fisik', 10, 2)->nullable(); // diisi saat penghitungan fisik
    $table->decimal('selisih', 10, 2)->nullable();    // computed: stok_fisik - stok_sistem
    $table->decimal('hpr_saat_itu', 14, 2)->default(0); // snapshot HPR
    $table->decimal('nilai_selisih', 14, 2)->default(0); // |selisih| × HPR
    $table->enum('tipe_selisih', ['sesuai', 'lebih', 'kurang'])->nullable();
    $table->text('keterangan')->nullable();
    $table->timestamps();

    $table->unique(['stok_opname_id', 'barang_id']);
    $table->index('barang_id');
});
```

### 6.3 Model

```php
// app/Models/StokOpname.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class StokOpname extends Model
{
    protected $table    = 'stok_opname';
    protected $fillable = [
        'nomor_opname', 'dibuat_oleh', 'diverifikasi_oleh',
        'tanggal_opname', 'keterangan_periode', 'status', 'catatan',
    ];

    protected function casts(): array
    {
        return ['tanggal_opname' => 'date'];
    }

    public function pembuat():    BelongsTo { return $this->belongsTo(User::class, 'dibuat_oleh'); }
    public function verifikator():BelongsTo { return $this->belongsTo(User::class, 'diverifikasi_oleh'); }
    public function items():      HasMany   { return $this->hasMany(StokOpnameItem::class); }

    public function getRingkasanAttribute(): array
    {
        return [
            'total_item'    => $this->items->count(),
            'sudah_diisi'   => $this->items->whereNotNull('stok_fisik')->count(),
            'sesuai'        => $this->items->where('tipe_selisih', 'sesuai')->count(),
            'lebih'         => $this->items->where('tipe_selisih', 'lebih')->count(),
            'kurang'        => $this->items->where('tipe_selisih', 'kurang')->count(),
            'nilai_selisih' => $this->items->sum('nilai_selisih'),
        ];
    }
}
```

```php
// app/Models/StokOpnameItem.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StokOpnameItem extends Model
{
    protected $table    = 'stok_opname_item';
    protected $fillable = [
        'stok_opname_id', 'barang_id', 'stok_sistem', 'stok_fisik',
        'selisih', 'hpr_saat_itu', 'nilai_selisih', 'tipe_selisih', 'keterangan',
    ];

    protected function casts(): array
    {
        return [
            'stok_sistem'   => 'decimal:2',
            'stok_fisik'    => 'decimal:2',
            'selisih'       => 'decimal:2',
            'hpr_saat_itu'  => 'decimal:2',
            'nilai_selisih' => 'decimal:2',
        ];
    }

    public function opname(): BelongsTo { return $this->belongsTo(StokOpname::class, 'stok_opname_id'); }
    public function barang(): BelongsTo { return $this->belongsTo(Barang::class); }
}
```

### 6.4 Service

```php
// app/Services/Inventory/StokOpnameService.php

namespace App\Services\Inventory;

use App\Models\{StokOpname, StokOpnameItem, Barang, MutasiStok};
use App\Services\Akuntansi\InventoriJurnalService;
use Illuminate\Support\Facades\DB;

class StokOpnameService
{
    /**
     * Buat dokumen opname dan snapshot stok sistem saat ini.
     * Bisa untuk SEMUA barang atau filter jenis/kategori tertentu.
     */
    public function buatOpname(array $data, int $userId, ?string $filterJenis = null): StokOpname
    {
        return DB::transaction(function () use ($data, $userId, $filterJenis) {
            $opname = StokOpname::create([
                'nomor_opname'        => $this->generateNomor(),
                'dibuat_oleh'         => $userId,
                'tanggal_opname'      => $data['tanggal_opname'],
                'keterangan_periode'  => $data['keterangan_periode'] ?? null,
                'catatan'             => $data['catatan'] ?? null,
            ]);

            $query = Barang::where('is_active', true);
            if ($filterJenis) $query->where('jenis', $filterJenis);

            foreach ($query->get() as $barang) {
                $opname->items()->create([
                    'barang_id'     => $barang->id,
                    'stok_sistem'   => $barang->stok,
                    'hpr_saat_itu'  => $barang->harga_pokok,
                    'stok_fisik'    => null,    // belum diisi
                ]);
            }

            return $opname->load('items.barang');
        });
    }

    /**
     * Update stok fisik untuk satu item, hitung selisih otomatis.
     */
    public function inputStokFisik(StokOpnameItem $item, float $stokFisik): StokOpnameItem
    {
        $selisih = $stokFisik - $item->stok_sistem;

        $tipeSelisih = match(true) {
            abs($selisih) < 0.001 => 'sesuai',
            $selisih > 0          => 'lebih',
            default               => 'kurang',
        };

        $item->update([
            'stok_fisik'    => $stokFisik,
            'selisih'       => $selisih,
            'tipe_selisih'  => $tipeSelisih,
            'nilai_selisih' => abs($selisih) * $item->hpr_saat_itu,
        ]);

        return $item->fresh();
    }

    /**
     * Submit untuk verifikasi — semua item harus sudah diisi.
     */
    public function submitUntukVerifikasi(StokOpname $opname): StokOpname
    {
        $belumDiisi = $opname->items()->whereNull('stok_fisik')->count();

        if ($belumDiisi > 0) {
            throw new \DomainException("Masih ada {$belumDiisi} item yang belum diisi stok fisiknya.");
        }

        $opname->update(['status' => 'menunggu_verifikasi']);
        return $opname;
    }

    /**
     * Verifikasi & posting — update stok sistem sesuai stok fisik.
     */
    public function verifikasi(StokOpname $opname, int $userId): StokOpname
    {
        if ($opname->status !== 'menunggu_verifikasi') {
            throw new \DomainException('Status opname harus "menunggu_verifikasi".');
        }

        return DB::transaction(function () use ($opname, $userId) {
            foreach ($opname->items as $item) {
                if ($item->tipe_selisih === 'sesuai') continue;

                $barang = Barang::lockForUpdate()->findOrFail($item->barang_id);
                $stokLama = $barang->stok;

                // Update stok ke stok fisik
                $barang->update(['stok' => $item->stok_fisik]);

                $tipeMutasi = $item->tipe_selisih === 'lebih'
                    ? 'penyesuaian_masuk'
                    : 'penyesuaian_keluar';

                MutasiStok::create([
                    'barang_id'      => $barang->id,
                    'user_id'        => $userId,
                    'tipe'           => $tipeMutasi,
                    'jumlah'         => abs($item->selisih),
                    'stok_sebelum'   => $stokLama,
                    'stok_sesudah'   => $item->stok_fisik,
                    'hpr_sebelum'    => $item->hpr_saat_itu,
                    'hpr_sesudah'    => $item->hpr_saat_itu,  // HPR tidak berubah saat opname
                    'referensi_tipe' => 'stok_opname',
                    'referensi_id'   => $opname->id,
                    'keterangan'     => "Stok Opname {$opname->nomor_opname}: {$item->tipe_selisih}",
                ]);
            }

            $opname->update([
                'status'             => 'selesai',
                'diverifikasi_oleh'  => $userId,
            ]);

            // Hook akuntansi
            app(InventoriJurnalService::class)->catatStokOpname($opname->fresh(['items']));

            return $opname->fresh();
        });
    }

    public function batalkan(StokOpname $opname): StokOpname
    {
        if ($opname->status === 'selesai') {
            throw new \DomainException('Opname yang sudah selesai tidak bisa dibatalkan.');
        }

        $opname->update(['status' => 'dibatalkan']);
        return $opname;
    }

    private function generateNomor(): string
    {
        $prefix = 'OPN-' . now()->format('Y-m-');
        $last   = StokOpname::where('nomor_opname', 'like', $prefix . '%')
                    ->orderByDesc('nomor_opname')->value('nomor_opname');
        $seq    = $last ? (int) substr($last, -4) + 1 : 1;
        return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }
}
```

### 6.5 Livewire Components

```
app/Livewire/Inventory/Opname/
├── OpnameTable.php    — daftar opname dengan filter status & tanggal
├── OpnameForm.php     — buat opname baru + filter jenis barang
└── OpnameDetail.php   — input stok fisik per item, submit verifikasi, lihat selisih
```

**`OpnameDetail.php`** — fitur utama:
- Tabel per item: nama barang | stok sistem | stok fisik (input inline) | selisih | nilai selisih | status
- Filter: tampilkan hanya yang belum diisi / ada selisih / semua
- Progress bar: X dari Y item sudah diisi
- Summary selisih: total lebih / kurang / nilai selisih
- Tombol "Submit Verifikasi" (hanya jika semua item terisi)
- Export PDF form penghitungan fisik (tanpa kolom stok sistem — untuk tim gudang)

---

## 7. Fitur 5 — Fondasi Akuntansi Inventori

### 7.1 Prinsip

Modul akuntansi belum dibangun, tetapi semua transaksi inventori harus **menyiapkan data jurnal** yang siap di-posting saat modul akuntansi aktif. Fondasi ini terdiri dari:

1. **`InventoriJurnalService`** — service stub dengan method untuk setiap jenis transaksi
2. **Tabel `jurnal_inventori_pending`** — buffer jurnal yang menunggu modul akuntansi
3. **Event System** — Laravel Events untuk loose coupling dengan modul akuntansi

### 7.2 Pemetaan Jurnal

| Transaksi | Debit | Kredit | Nilai |
|-----------|-------|--------|-------|
| GR Verifikasi (masuk_pembelian) | Persediaan Barang (1-1300) | Hutang Dagang (2-1100) | `jumlah × harga_beli_efektif` |
| Keluar Resep (keluar_resep) | HPP Farmasi (5-1100) | Persediaan Barang (1-1300) | `jumlah × HPR` |
| Keluar Tindakan (keluar_tindakan) | HPP Tindakan (5-1200) | Persediaan Barang (1-1300) | `jumlah × HPR` |
| Keluar BHP (keluar_bhp) | Biaya BHP (5-2100) | Persediaan Barang (1-1300) | `jumlah × HPR` |
| Opname Lebih (penyesuaian_masuk) | Persediaan Barang (1-1300) | Selisih Opname (8-1100) | `|selisih| × HPR` |
| Opname Kurang (penyesuaian_keluar) | Selisih Opname (8-1100) | Persediaan Barang (1-1300) | `|selisih| × HPR` |
| Retur ke Supplier (retur_ke_supplier) | Hutang Dagang (2-1100) | Persediaan Barang (1-1300) | `jumlah × HPR` |

> **Catatan**: Kode akun (misal 1-1300) bersifat placeholder — akan disesuaikan saat `PRD_Akuntansi.md` diimplementasi. Yang penting adalah **tipe akun** (Persediaan, HPP, Hutang, dll.) sudah terdefinisi.

### 7.3 Database — Buffer Jurnal

```php
// 2026_05_27_200005_create_jurnal_inventori_pending_table.php

Schema::create('jurnal_inventori_pending', function (Blueprint $table) {
    $table->id();
    $table->string('sumber_tipe', 50);     // 'mutasi_stok', 'pemakaian_bhp', 'stok_opname'
    $table->unsignedBigInteger('sumber_id');
    $table->string('tipe_transaksi', 50);  // 'masuk_pembelian', 'keluar_bhp', dll.
    $table->date('tanggal_transaksi');
    $table->string('kode_akun_debit', 20);
    $table->string('kode_akun_kredit', 20);
    $table->decimal('nominal', 16, 2);
    $table->string('keterangan', 255)->nullable();
    $table->json('metadata')->nullable();  // data tambahan untuk modul akuntansi
    $table->enum('status', ['pending', 'posted', 'diabaikan'])->default('pending');
    $table->timestamp('posted_at')->nullable();
    $table->timestamps();

    $table->index(['sumber_tipe', 'sumber_id']);
    $table->index(['status', 'tanggal_transaksi']);
});
```

### 7.4 Service Stub

```php
// app/Services/Akuntansi/InventoriJurnalService.php

namespace App\Services\Akuntansi;

use App\Models\{GoodsReceipt, PemakaianBhp, StokOpname, JurnalInventoriPending};
use Carbon\Carbon;

class InventoriJurnalService
{
    // Konstanta kode akun — update setelah PRD_Akuntansi.md
    const AKUN = [
        'persediaan_barang' => '1-1300',
        'hutang_dagang'     => '2-1100',
        'hpp_farmasi'       => '5-1100',
        'hpp_tindakan'      => '5-1200',
        'biaya_bhp'         => '5-2100',
        'selisih_opname'    => '8-1100',
    ];

    public function catatPembelian(GoodsReceipt $gr): void
    {
        foreach ($gr->items as $item) {
            $nilai = $item->jumlah_terima * $item->getHargaEfektifAttribute();
            $this->simpan(
                sumberTipe:       'goods_receipt',
                sumberId:         $gr->id,
                tipeTransaksi:    'masuk_pembelian',
                tanggal:          $gr->tanggal_terima,
                akun_debit:       self::AKUN['persediaan_barang'],
                akun_kredit:      self::AKUN['hutang_dagang'],
                nominal:          $nilai,
                keterangan:       "GR {$gr->nomor_gr}: {$item->barang->nama}",
                metadata:         ['barang_id' => $item->barang_id, 'gr_item_id' => $item->id],
            );
        }
    }

    public function catatPemakaianBhp(PemakaianBhp $bhp): void
    {
        foreach ($bhp->items as $item) {
            $this->simpan(
                sumberTipe:    'pemakaian_bhp',
                sumberId:      $bhp->id,
                tipeTransaksi: 'keluar_bhp',
                tanggal:       $bhp->tanggal_pemakaian,
                akun_debit:    self::AKUN['biaya_bhp'],
                akun_kredit:   self::AKUN['persediaan_barang'],
                nominal:       $item->nilai_total,
                keterangan:    "BHP {$bhp->nomor_bhp}: {$item->barang->nama}",
                metadata:      ['barang_id' => $item->barang_id, 'bhp_item_id' => $item->id],
            );
        }
    }

    public function catatStokOpname(StokOpname $opname): void
    {
        foreach ($opname->items->where('tipe_selisih', '!=', 'sesuai') as $item) {
            $isDrPersediaan = $item->tipe_selisih === 'lebih';
            $this->simpan(
                sumberTipe:    'stok_opname',
                sumberId:      $opname->id,
                tipeTransaksi: $isDrPersediaan ? 'penyesuaian_masuk' : 'penyesuaian_keluar',
                tanggal:       $opname->tanggal_opname,
                akun_debit:    $isDrPersediaan ? self::AKUN['persediaan_barang'] : self::AKUN['selisih_opname'],
                akun_kredit:   $isDrPersediaan ? self::AKUN['selisih_opname']    : self::AKUN['persediaan_barang'],
                nominal:       $item->nilai_selisih,
                keterangan:    "Opname {$opname->nomor_opname}: {$item->barang->nama} ({$item->tipe_selisih})",
                metadata:      ['barang_id' => $item->barang_id, 'opname_item_id' => $item->id],
            );
        }
    }

    private function simpan(
        string $sumberTipe, int $sumberId, string $tipeTransaksi,
        mixed $tanggal, string $akun_debit, string $akun_kredit,
        float $nominal, ?string $keterangan = null, array $metadata = []
    ): void {
        JurnalInventoriPending::create([
            'sumber_tipe'        => $sumberTipe,
            'sumber_id'          => $sumberId,
            'tipe_transaksi'     => $tipeTransaksi,
            'tanggal_transaksi'  => $tanggal,
            'kode_akun_debit'    => $akun_debit,
            'kode_akun_kredit'   => $akun_kredit,
            'nominal'            => $nominal,
            'keterangan'         => $keterangan,
            'metadata'           => $metadata,
            'status'             => 'pending',
        ]);
    }
}
```

### 7.5 Integrasi ke Existing Services

Tambahkan panggilan `InventoriJurnalService` di titik verifikasi yang sudah ada:

```php
// app/Services/Inventory/PenerimaanService.php — TAMBAHAN di verifikasiGr()
// (setelah semua item diproses dan status di-update):
app(InventoriJurnalService::class)->catatPembelian($gr->fresh(['items.barang']));
```

### 7.6 Business Rules Akuntansi

```
✓ Setiap verifikasi transaksi inventori → 1 atau lebih jurnal_inventori_pending dibuat
✓ Jurnal tidak pernah diposting langsung — status awal selalu 'pending'
✓ Kode akun bersifat placeholder — diupdate saat COA akuntansi tersedia
✓ Metadata JSON menyimpan referensi lengkap agar modul akuntansi bisa trace-back
✓ Jurnal yang sudah di-post ('posted') tidak bisa diubah
✓ Pembatalan transaksi inventori: buat jurnal koreksi (debit/kredit dibalik), TIDAK hapus jurnal lama
✓ Selisih < Rp 1.000 per item opname bisa dikonfigurasi sebagai threshold 'diabaikan'
```

---

## 8. Skema Database & Migration

### Urutan Migration

```
2026_05_27_200001_create_pemakaian_bhp_table.php
2026_05_27_200002_create_pemakaian_bhp_item_table.php
2026_05_27_200003_create_stok_opname_table.php
2026_05_27_200004_create_stok_opname_item_table.php
2026_05_27_200005_create_jurnal_inventori_pending_table.php
2026_05_27_200006_alter_mutasi_stok_add_keluar_bhp.php   — tambah enum 'keluar_bhp'
```

### Ringkasan Kolom Baru

| Tabel | Kolom Kunci |
|-------|-------------|
| `pemakaian_bhp` | nomor_bhp, dicatat_oleh, diverifikasi_oleh, tanggal_pemakaian, status, catatan |
| `pemakaian_bhp_item` | pemakaian_bhp_id, barang_id, jumlah, harga_pokok_saat_itu, nilai_total, keterangan |
| `stok_opname` | nomor_opname, dibuat_oleh, diverifikasi_oleh, tanggal_opname, keterangan_periode, status |
| `stok_opname_item` | stok_opname_id, barang_id, stok_sistem, stok_fisik, selisih, hpr_saat_itu, nilai_selisih, tipe_selisih |
| `jurnal_inventori_pending` | sumber_tipe, sumber_id, tipe_transaksi, tanggal_transaksi, kode_akun_debit, kode_akun_kredit, nominal, status |

---

## 9. Model Eloquent

### File yang Dibuat Baru

```
app/Models/
├── PemakaianBhp.php
├── PemakaianBhpItem.php
├── StokOpname.php
├── StokOpnameItem.php
└── JurnalInventoriPending.php
```

### File yang Dimodifikasi

```
app/Models/Barang.php
  + static pastikanCukup(int $barangId, float $jumlah): self

app/Models/MutasiStok.php
  + 'keluar_bhp' ditambahkan ke array getTipeLabels()
```

---

## 10. Service Layer

```
app/Services/Inventory/
├── SupplierService.php          (tidak berubah)
├── PembelianService.php         (tidak berubah, + hook akuntansi di verifikasiGr)
├── PenerimaanService.php        (tidak berubah, + hook akuntansi)
├── PemakaianBhpService.php      ← BARU
└── StokOpnameService.php        ← BARU

app/Services/Akuntansi/
└── InventoriJurnalService.php   ← BARU (stub untuk integrasi akuntansi)
```

---

## 11. Livewire Components

```
app/Livewire/Inventory/
├── Po/
│   ├── PoTable.php              (tidak berubah)
│   └── PoForm.php               ← UPDATE: tambah mapping grid
├── Gr/
│   ├── GrTable.php              (tidak berubah)
│   └── GrForm.php               (tidak berubah)
├── Bhp/
│   ├── BhpTable.php             ← BARU
│   └── BhpForm.php              ← BARU
└── Opname/
    ├── OpnameTable.php          ← BARU
    ├── OpnameForm.php           ← BARU
    └── OpnameDetail.php         ← BARU
```

### View Struktur

```
resources/views/livewire/inventory/
├── po/
│   ├── po-table.blade.php      (tidak berubah)
│   └── po-form.blade.php       ← UPDATE: tambah section mapping grid
├── bhp/
│   ├── bhp-table.blade.php     ← BARU
│   └── bhp-form.blade.php      ← BARU
└── opname/
    ├── opname-table.blade.php  ← BARU
    ├── opname-form.blade.php   ← BARU
    └── opname-detail.blade.php ← BARU

resources/views/inventory/
├── bhp/
│   └── index.blade.php         ← BARU (wrapper)
└── opname/
    ├── index.blade.php         ← BARU (wrapper list)
    └── show.blade.php          ← BARU (detail & input stok fisik)
```

---

## 12. Route & Controller

```php
// routes/web.php — tambahan di dalam group inventory

Route::prefix('inventory')->name('inventory.')->middleware(['auth', 'active', 'permission:obat.view'])->group(function () {

    // ... existing routes (po, gr, kartu-stok) tidak berubah ...

    // Pemakaian BHP
    Route::prefix('bhp')->name('bhp.')->group(function () {
        Route::get('/',        fn () => view('inventory.bhp.index'))->name('index');
        Route::get('/create',  fn () => view('inventory.bhp.create'))->name('create')
             ->middleware('permission:obat.edit');
    });

    // Stok Opname
    Route::prefix('opname')->name('opname.')->group(function () {
        Route::get('/',              fn () => view('inventory.opname.index'))->name('index');
        Route::get('/create',        fn () => view('inventory.opname.create'))->name('create')
             ->middleware('permission:obat.edit');
        Route::get('/{opname}',      function ($id) {
            $opname = \App\Models\StokOpname::with(['items.barang', 'pembuat', 'verifikator'])
                ->findOrFail($id);
            return view('inventory.opname.show', compact('opname'));
        })->name('show');
    });
});
```

---

## 13. Role & Hak Akses

| Aksi | super_admin | admin | apoteker | gudang | keuangan |
|------|:-----------:|:-----:|:--------:|:------:|:--------:|
| Lihat Mapping PO | ✅ | ✅ | ✅ | ✅ | ❌ |
| Buat & Approve PO | ✅ | ✅ | ✅ | ❌ | ❌ |
| Buat Pemakaian BHP | ✅ | ✅ | ✅ | ✅ | ❌ |
| Verifikasi Pemakaian BHP | ✅ | ✅ | ✅ | ❌ | ❌ |
| Buat Stok Opname | ✅ | ✅ | ✅ | ✅ | ❌ |
| Input Stok Fisik Opname | ✅ | ✅ | ✅ | ✅ | ❌ |
| Verifikasi / Posting Opname | ✅ | ✅ | ❌ | ❌ | ✅ |
| Lihat Jurnal Pending | ✅ | ✅ | ❌ | ❌ | ✅ |

---

## 14. User Stories & Business Rules

| ID | Persona | Skenario | Expected Behavior |
|----|---------|----------|-------------------|
| US01 | Apoteker | Buat PO ke supplier Kimia Farma | Setelah pilih supplier, grid muncul daftar 25 barang terikat Kimia Farma dengan harga terakhir otomatis |
| US02 | Apoteker | Centang 8 dari 25 barang di grid | Hanya 8 yang dipilih masuk ke draft PO |
| US03 | Apoteker | Tambah barang di luar mapping | Bisa lewat search seperti sebelumnya |
| US04 | Kasir | Dispense resep, stok barang = 0 | Error: "Stok Paracetamol tidak mencukupi. Tersedia: 0 tablet" |
| US05 | Kasir | Dispense resep, stok = 5, minta 10 | Error: "Tersedia: 5 tablet, Diminta: 10 tablet" |
| US06 | Gudang | Pakai 50 pcs sarung tangan dari gudang | Buat PemakaianBHP → stok berkurang 50 → mutasi keluar_bhp tercatat |
| US07 | Gudang | Input BHP untuk barang jenis `obat` | Error: "Barang ini bukan bahan habis pakai" |
| US08 | Gudang | Verifikasi BHP, stok cukup | Stok berkurang, mutasi tercatat, jurnal pending dibuat |
| US09 | Apoteker | Buat stok opname untuk semua barang | 150 item muncul di tabel dengan stok sistem ter-snapshot |
| US10 | Gudang | Input stok fisik paracetamol = 95, sistem = 100 | Selisih = -5, tipe = kurang, nilai = 5 × HPR |
| US11 | Apoteker | Submit opname untuk verifikasi | Semua 150 item harus sudah diisi — jika ada yang kosong, error |
| US12 | Keuangan | Verifikasi opname dengan selisih | Stok sistem diperbarui, mutasi penyesuaian dibuat, jurnal pending dibuat |
| US13 | Keuangan | Lihat jurnal pending dari opname bulan ini | Tabel menampilkan debit/kredit per selisih, nilai, status posting |
| US14 | Admin | GR diverifikasi | Jurnal pending masuk_pembelian otomatis terbuat (Dr Persediaan / Cr Hutang) |
| US15 | Opname selesai, item kurang | Stok fisik < stok sistem | MutasiStok tipe penyesuaian_keluar dibuat, jurnal Dr Selisih Opname / Cr Persediaan |

---

## 15. Seeder Data Awal

```php
// Tidak ada seeder baru untuk fitur ini.
// Stok Opname dan Pemakaian BHP dibuat manual oleh user.
// jurnal_inventori_pending terisi otomatis saat verifikasi transaksi.

// Yang perlu dipastikan:
// ✓ Barang dengan jenis 'bahan_habis_pakai' sudah ada di database
// ✓ supplier_barang sudah ada (agar mapping grid berfungsi)
// ✓ Barang memiliki stok > 0 untuk pengujian validasi
```

---

## 16. Urutan Implementasi

| Fase | Task | Dependensi |
|------|------|------------|
| **1** | Migration 6 file baru | — |
| **2** | Model: PemakaianBhp, PemakaianBhpItem, StokOpname, StokOpnameItem, JurnalInventoriPending | Migrasi selesai |
| **3** | Update Model Barang: `pastikanCukup()` | Model selesai |
| **4** | Service: InventoriJurnalService (stub) | Model selesai |
| **5** | Service: PemakaianBhpService | Barang.pastikanCukup(), InventoriJurnalService |
| **6** | Service: StokOpnameService | Barang.pastikanCukup(), InventoriJurnalService |
| **7** | Update PoForm: mapping grid | supplier_barang ada |
| **8** | Livewire BhpTable + BhpForm + blades | PemakaianBhpService |
| **9** | Livewire OpnameTable + OpnameForm + OpnameDetail + blades | StokOpnameService |
| **10** | Update routes & sidebar | Livewire selesai |
| **11** | Integrasi hook akuntansi ke PenerimaanService (GR verify) | InventoriJurnalService |
| **12** | Jalankan migrate, test end-to-end | Semua selesai |
| **13** | Commit & push | — |

---

*PRD Inventory Update v2.0*
*Konsisten dengan `manajemen_inventory.md` · `kartu_stok.md` · `PRD_Akuntansi.md` (coming soon)*
*(Laravel 12 · Livewire 3 · MySQL · Tailwind CSS)*

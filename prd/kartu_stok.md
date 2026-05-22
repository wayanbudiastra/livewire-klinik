# PRD: Kartu Stok Barang (kartu_stok.md)

| Info | Detail |
|:-----|:-------|
| **Versi** | 1.0.0 |
| **Tanggal** | Mei 2026 |
| **Status** | Draft |
| **Tech Stack** | Laravel 12 · Livewire 3 · MySQL · Tailwind CSS |
| **Depends On** | `manajemen_inventory.md` (tabel `mutasi_stok`, `barang`, `supplier`) |
| **Scope** | Kartu Stok per Barang — riwayat mutasi masuk/keluar dengan saldo berjalan & HPR |
| **Lokasi Menu** | Inventory → Kartu Stok |

---

## Daftar Isi

1. [Ringkasan Eksekutif](#1-ringkasan-eksekutif)
2. [Tujuan & Sasaran](#2-tujuan--sasaran)
3. [Definisi Kartu Stok](#3-definisi-kartu-stok)
4. [Fitur Utama](#4-fitur-utama)
5. [Skema Data (Sumber dari mutasi_stok)](#5-skema-data-sumber-dari-mutasi_stok)
6. [Business Rules](#6-business-rules)
7. [Service Layer](#7-service-layer)
8. [Livewire Components](#8-livewire-components)
9. [Blade Views](#9-blade-views)
10. [Routes & RBAC](#10-routes--rbac)
11. [Struktur Folder](#11-struktur-folder)
12. [User Stories](#12-user-stories)
13. [Export PDF/Excel](#13-export-pdfexcel)

---

## 1. Ringkasan Eksekutif

**Kartu Stok** adalah laporan kronologis per barang yang menampilkan setiap mutasi stok (masuk/keluar) beserta saldo berjalan. Dokumen ini adalah *audit trail* fisik dari setiap pergerakan barang di gudang, mulai dari pembelian, pemakaian resep, tindakan, penyesuaian stok opname, hingga disposal barang expired.

Kartu stok bersumber dari tabel `mutasi_stok` yang sudah diisi secara otomatis oleh modul-modul:
- **Inventory** (Goods Receipt → `masuk_pembelian`)
- **Farmasi** (Dispensing → `keluar_resep`)
- **Tindakan** (Pemakaian alkes → `keluar_tindakan`)
- **Stock Opname** (Penyesuaian → `penyesuaian_masuk` / `penyesuaian_keluar`)
- **Retur Supplier** (`retur_ke_supplier`)
- **Disposal Expired** (`expired`)

---

## 2. Tujuan & Sasaran

### Tujuan
- Menyediakan visibilitas penuh atas riwayat pergerakan stok setiap barang
- Memudahkan audit dan rekonsiliasi stok fisik vs sistem
- Melacak perubahan Harga Pokok Rata-rata (HPR) dari waktu ke waktu
- Mengidentifikasi anomali mutasi stok (stok tiba-tiba turun drastis, dll.)

### Sasaran Teknis
- Query kartu stok **tidak pernah** memodifikasi data — hanya READ
- Saldo berjalan dihitung dari `stok_sesudah` field terakhir per tanggal
- Filter minimal: barang, rentang tanggal, tipe mutasi
- Export PDF (untuk print fisik) dan Excel (untuk rekonsiliasi)

---

## 3. Definisi Kartu Stok

```
┌─────────────────────────────────────────────────────────────────────────┐
│                        KARTU STOK BARANG                                │
│  Barang  : Paracetamol 500mg (BRG-000001)                               │
│  Satuan  : Tablet                                                        │
│  Periode : 01/01/2026 — 31/05/2026                                      │
├──────────┬──────┬──────┬──────────────┬────────┬────────┬───────┬───────┤
│ Tanggal  │Waktu │ Tipe │  Keterangan  │ Masuk  │ Keluar │ Saldo │  HPR  │
├──────────┼──────┼──────┼──────────────┼────────┼────────┼───────┼───────┤
│ Saldo    │      │      │ Saldo Awal   │        │        │   200 │  850  │
│ 05/01/26 │08:32 │Beli  │GR-2026-01-01 │    500 │        │   700 │  814  │
│ 07/01/26 │10:15 │Resep │Res-00123     │        │     10 │   690 │  814  │
│ 07/01/26 │14:20 │Resep │Res-00125     │        │     20 │   670 │  814  │
│ 10/01/26 │09:00 │Opname│Adj-2026-01-02│     30 │        │   700 │  814  │
│ 15/01/26 │11:00 │Resep │Res-00156     │        │     50 │   650 │  814  │
├──────────┴──────┴──────┴──────────────┴────────┴────────┴───────┴───────┤
│  RINGKASAN PERIODE: Masuk: 530 | Keluar: 80 | Saldo Akhir: 650         │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## 4. Fitur Utama

### 4.1 Pencarian & Filter Barang
- Search barang by nama, kode, barcode
- Tampil info barang: stok saat ini, HPR, satuan, kategori
- Badge level stok (habis/kritis/aman)

### 4.2 Filter Kartu Stok
| Filter | Opsi |
|--------|------|
| **Rentang Tanggal** | Default: 1 bulan terakhir; min/max bebas dipilih |
| **Tipe Mutasi** | Semua · Masuk saja · Keluar saja · Per tipe spesifik |
| **Pengguna** | Filter by user yang mencatat mutasi |

### 4.3 Tampilan Kartu
- **Header**: Info barang lengkap (kode, nama, satuan, kategori, supplier utama)
- **Saldo awal**: Stok pada awal rentang tanggal yang dipilih
- **Baris mutasi**: Tanggal, waktu, tipe mutasi, keterangan/referensi, masuk, keluar, saldo berjalan, HPR
- **Footer**: Ringkasan total masuk, total keluar, saldo akhir

### 4.4 Grafik Mini (Opsional)
- Grafik garis stok berjalan per hari dalam periode yang dipilih

### 4.5 Export
- **Print / PDF**: Format kartu stok resmi (landscape, A4)
- **Excel**: Data mentah untuk rekonsiliasi

---

## 5. Skema Data (Sumber dari mutasi_stok)

### Tabel yang Dipakai (READ ONLY)

```
mutasi_stok
  ├── id
  ├── barang_id          → JOIN barang
  ├── user_id            → JOIN users (nama pencatat)
  ├── tipe               → masuk_pembelian | keluar_resep | keluar_tindakan | ...
  ├── jumlah             → qty bergerak
  ├── stok_sebelum       → saldo sebelum mutasi ini
  ├── stok_sesudah       → saldo setelah mutasi ini (= saldo berjalan)
  ├── hpr_sebelum        → HPR sebelum
  ├── hpr_sesudah        → HPR setelah (terupdate saat masuk_pembelian)
  ├── referensi_tipe     → goods_receipt | resep | tindakan | dll
  ├── referensi_id       → ID dokumen sumber
  ├── keterangan         → teks bebas
  └── created_at         → timestamp mutasi
```

### Klasifikasi Tipe Mutasi

```php
// Tipe masuk (positif)
'masuk_pembelian'    → dari Goods Receipt
'penyesuaian_masuk'  → dari stock opname (tambah)

// Tipe keluar (negatif)
'keluar_resep'       → dispensing resep
'keluar_tindakan'    → pemakaian tindakan medis
'penyesuaian_keluar' → dari stock opname (kurang)
'retur_ke_supplier'  → retur barang
'expired'            → disposal barang expired
```

---

## 6. Business Rules

```
✓ Kartu stok bersifat READ ONLY — tidak ada create/update/delete dari fitur ini
✓ Saldo awal = stok_sebelum dari mutasi PERTAMA dalam rentang yang dipilih
  (atau stok saat ini jika tidak ada mutasi dalam rentang)
✓ Saldo berjalan = stok_sesudah dari setiap baris mutasi
✓ HPR hanya berubah saat tipe = 'masuk_pembelian'
✓ Tanggal default = bulan berjalan (1 s/d hari ini)
✓ Maksimal rentang tanggal = 1 tahun (untuk performa query)
✓ Data diurutkan ASC by created_at (kronologis)
✓ Jika stok_sesudah negatif (anomali data) → tampil merah dengan warning
```

---

## 7. Service Layer

```php
// app/Services/Inventory/KartuStokService.php

namespace App\Services\Inventory;

use App\Models\Barang;
use App\Models\MutasiStok;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class KartuStokService
{
    /**
     * Ambil data kartu stok untuk satu barang dalam rentang tanggal
     */
    public function getKartuStok(
        int    $barangId,
        string $tanggalMulai,
        string $tanggalAkhir,
        ?string $tipeMutasi = null
    ): array {
        $barang = Barang::with(['supplierUtama:id,nama'])->findOrFail($barangId);

        // Saldo awal = stok_sebelum dari mutasi pertama dalam periode
        // Jika tidak ada mutasi, gunakan stok_sesudah terakhir sebelum periode
        $mutasiSebelumPeriode = MutasiStok::where('barang_id', $barangId)
            ->where('created_at', '<', Carbon::parse($tanggalMulai)->startOfDay())
            ->orderByDesc('created_at')
            ->first();

        $saldoAwal = $mutasiSebelumPeriode?->stok_sesudah ?? 0;
        $hprAwal   = $mutasiSebelumPeriode?->hpr_sesudah  ?? (float) $barang->harga_pokok;

        // Ambil mutasi dalam periode
        $mutasi = MutasiStok::with('user:id,nama')
            ->where('barang_id', $barangId)
            ->whereBetween('created_at', [
                Carbon::parse($tanggalMulai)->startOfDay(),
                Carbon::parse($tanggalAkhir)->endOfDay(),
            ])
            ->when($tipeMutasi, fn ($q, $t) => $q->where('tipe', $t))
            ->orderBy('created_at')
            ->get();

        // Klasifikasi masuk / keluar
        $tipemasuk  = ['masuk_pembelian', 'penyesuaian_masuk'];
        $tipeKeluar = ['keluar_resep', 'keluar_tindakan', 'penyesuaian_keluar', 'retur_ke_supplier', 'expired'];

        $rows = $mutasi->map(function ($m) use ($tipemasuk, $tipeKeluar) {
            $isMasuk = in_array($m->tipe, $tipemasuk);
            return [
                'id'              => $m->id,
                'tanggal'         => $m->created_at->format('d/m/Y'),
                'waktu'           => $m->created_at->format('H:i'),
                'created_at'      => $m->created_at,
                'tipe'            => $m->tipe,
                'tipe_label'      => self::getTipeLabel($m->tipe),
                'keterangan'      => $m->keterangan,
                'referensi_tipe'  => $m->referensi_tipe,
                'referensi_id'    => $m->referensi_id,
                'masuk'           => $isMasuk ? $m->jumlah : 0,
                'keluar'          => ! $isMasuk ? $m->jumlah : 0,
                'saldo'           => $m->stok_sesudah,
                'hpr'             => (float) $m->hpr_sesudah,
                'user_nama'       => $m->user?->nama ?? '-',
                'is_anomali'      => $m->stok_sesudah < 0,
            ];
        });

        // Ringkasan
        $totalMasuk  = $rows->sum('masuk');
        $totalKeluar = $rows->sum('keluar');
        $saldoAkhir  = $rows->last()['saldo'] ?? $saldoAwal;

        return [
            'barang'       => $barang,
            'saldo_awal'   => $saldoAwal,
            'hpr_awal'     => $hprAwal,
            'rows'         => $rows,
            'total_masuk'  => $totalMasuk,
            'total_keluar' => $totalKeluar,
            'saldo_akhir'  => $saldoAkhir,
            'tanggal_mulai'=> $tanggalMulai,
            'tanggal_akhir'=> $tanggalAkhir,
        ];
    }

    /**
     * Ringkasan semua barang yang punya mutasi dalam periode
     */
    public function getRingkasanMutasi(
        string $tanggalMulai,
        string $tanggalAkhir
    ): Collection {
        return MutasiStok::selectRaw('
                barang_id,
                COUNT(*) as total_transaksi,
                SUM(CASE WHEN tipe IN ("masuk_pembelian","penyesuaian_masuk") THEN jumlah ELSE 0 END) as total_masuk,
                SUM(CASE WHEN tipe NOT IN ("masuk_pembelian","penyesuaian_masuk") THEN jumlah ELSE 0 END) as total_keluar,
                MAX(stok_sesudah) as stok_tertinggi,
                MIN(stok_sesudah) as stok_terendah
            ')
            ->with('barang:id,kode,nama,satuan,stok,stok_minimum')
            ->whereBetween('created_at', [
                Carbon::parse($tanggalMulai)->startOfDay(),
                Carbon::parse($tanggalAkhir)->endOfDay(),
            ])
            ->groupBy('barang_id')
            ->orderBy('barang_id')
            ->get();
    }

    public static function getTipeLabel(string $tipe): string
    {
        return match ($tipe) {
            'masuk_pembelian'    => 'Pembelian',
            'keluar_resep'       => 'Resep',
            'keluar_tindakan'    => 'Tindakan',
            'penyesuaian_masuk'  => 'Opname (+)',
            'penyesuaian_keluar' => 'Opname (-)',
            'retur_ke_supplier'  => 'Retur',
            'expired'            => 'Expired',
            default              => ucfirst(str_replace('_', ' ', $tipe)),
        };
    }

    public static function getTipeBadgeClass(string $tipe): string
    {
        $masuk  = ['masuk_pembelian', 'penyesuaian_masuk'];
        $danger = ['expired', 'retur_ke_supplier'];
        if (in_array($tipe, $masuk))  return 'badge-success';
        if (in_array($tipe, $danger)) return 'badge-danger';
        return 'badge-warning';
    }
}
```

---

## 8. Livewire Components

### 8.1 KartuStokIndex — Halaman Pencarian & Pilih Barang

```php
// app/Livewire/Inventory/KartuStok/KartuStokIndex.php

namespace App\Livewire\Inventory\KartuStok;

use App\Models\Barang;
use App\Services\Inventory\KartuStokService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

class KartuStokIndex extends Component
{
    // Filter pencarian barang
    #[Url(as: 'q')]
    public string $searchBarang    = '';
    public ?int   $selectedBarangId = null;
    public string $selectedBarangNama = '';

    // Filter kartu stok
    #[Url]
    public string $tanggalMulai   = '';
    #[Url]
    public string $tanggalAkhir   = '';
    #[Url]
    public string $tipeMutasi     = '';

    // Hasil kartu stok
    public array $kartuData = [];
    public bool  $sudahCari = false;

    public function mount(): void
    {
        $this->tanggalMulai = now()->startOfMonth()->format('Y-m-d');
        $this->tanggalAkhir = now()->format('Y-m-d');
    }

    #[Computed]
    public function barangSuggestions()
    {
        if (strlen($this->searchBarang) < 2) return collect();
        return Barang::where('is_active', true)
            ->where(fn ($q) =>
                $q->where('nama', 'like', "%{$this->searchBarang}%")
                  ->orWhere('kode', 'like', "%{$this->searchBarang}%"))
            ->select('id', 'kode', 'nama', 'satuan', 'stok', 'stok_minimum', 'jenis')
            ->limit(10)
            ->get();
    }

    public function pilihBarang(int $id, string $nama): void
    {
        $this->selectedBarangId   = $id;
        $this->selectedBarangNama = $nama;
        $this->searchBarang       = $nama;
        $this->sudahCari          = false;
        $this->kartuData          = [];
    }

    public function lihatKartu(KartuStokService $service): void
    {
        $this->validate([
            'selectedBarangId' => 'required|integer|exists:barang,id',
            'tanggalMulai'     => 'required|date',
            'tanggalAkhir'     => 'required|date|after_or_equal:tanggalMulai',
        ], [
            'selectedBarangId.required' => 'Pilih barang terlebih dahulu.',
            'tanggalAkhir.after_or_equal' => 'Tanggal akhir harus setelah tanggal mulai.',
        ]);

        $this->kartuData = $service->getKartuStok(
            $this->selectedBarangId,
            $this->tanggalMulai,
            $this->tanggalAkhir,
            $this->tipeMutasi ?: null
        );

        $this->sudahCari = true;
    }

    public function resetPilihan(): void
    {
        $this->selectedBarangId   = null;
        $this->selectedBarangNama = '';
        $this->searchBarang       = '';
        $this->kartuData          = [];
        $this->sudahCari          = false;
    }

    public function getTipeOptions(): array
    {
        return [
            ''                    => 'Semua Tipe',
            'masuk_pembelian'     => 'Pembelian (Masuk)',
            'keluar_resep'        => 'Resep (Keluar)',
            'keluar_tindakan'     => 'Tindakan (Keluar)',
            'penyesuaian_masuk'   => 'Opname Tambah',
            'penyesuaian_keluar'  => 'Opname Kurang',
            'retur_ke_supplier'   => 'Retur Supplier',
            'expired'             => 'Disposal Expired',
        ];
    }

    public function render()
    {
        return view('livewire.inventory.kartu-stok.kartu-stok-index');
    }
}
```

### 8.2 KartuStokRingkasan — Semua Barang yang Bergerak

```php
// app/Livewire/Inventory/KartuStok/KartuStokRingkasan.php

namespace App\Livewire\Inventory\KartuStok;

use App\Services\Inventory\KartuStokService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

class KartuStokRingkasan extends Component
{
    #[Url]
    public string $tanggalMulai = '';
    #[Url]
    public string $tanggalAkhir = '';
    #[Url]
    public string $search       = '';

    public function mount(): void
    {
        $this->tanggalMulai = now()->startOfMonth()->format('Y-m-d');
        $this->tanggalAkhir = now()->format('Y-m-d');
    }

    #[Computed]
    public function ringkasan()
    {
        return app(KartuStokService::class)
            ->getRingkasanMutasi($this->tanggalMulai, $this->tanggalAkhir)
            ->when($this->search, fn ($c) =>
                $c->filter(fn ($r) =>
                    str_contains(strtolower($r->barang?->nama ?? ''), strtolower($this->search))
                    || str_contains(strtolower($r->barang?->kode ?? ''), strtolower($this->search))
                )
            );
    }

    public function render()
    {
        return view('livewire.inventory.kartu-stok.kartu-stok-ringkasan');
    }
}
```

---

## 9. Blade Views

### Halaman Utama Kartu Stok

```blade
{{-- resources/views/inventory/kartu-stok.blade.php --}}
<x-app-layout>
    <x-slot name="title">Kartu Stok</x-slot>

    <div class="page-header">
        <div>
            <h2 class="page-title">Kartu Stok Barang</h2>
            <p class="page-subtitle">Riwayat mutasi masuk/keluar dengan saldo berjalan per barang</p>
        </div>
    </div>

    @php $tab = request()->query('tab', 'kartu'); @endphp

    <div class="border-b border-gray-200 dark:border-gray-700 mb-6">
        <nav class="flex gap-0 -mb-px">
            @foreach ([
                'kartu'    => 'Kartu Stok per Barang',
                'ringkasan'=> 'Ringkasan Semua Barang',
            ] as $key => $label)
            <a href="?tab={{ $key }}"
               @class(['px-5 py-3 text-sm font-medium border-b-2 whitespace-nowrap transition-colors',
                   'border-[#0a3d62] text-[#0a3d62] dark:border-blue-400 dark:text-blue-400' => $tab === $key,
                   'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400' => $tab !== $key])>
                {{ $label }}
            </a>
            @endforeach
        </nav>
    </div>

    @switch($tab)
        @case('kartu')
            <livewire:inventory.kartu-stok.kartu-stok-index />
            @break
        @case('ringkasan')
            <livewire:inventory.kartu-stok.kartu-stok-ringkasan />
            @break
    @endswitch

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

### Livewire View — KartuStokIndex

```blade
{{-- resources/views/livewire/inventory/kartu-stok/kartu-stok-index.blade.php --}}
<div class="space-y-5">

    {{-- Panel Filter --}}
    <div class="card">
        <div class="card-header">
            <h3 class="text-sm font-semibold dark:text-white">Filter Kartu Stok</h3>
        </div>
        <div class="card-body space-y-4">

            {{-- Pilih Barang --}}
            <div class="form-group relative">
                <label class="form-label dark:text-gray-300">Barang <span class="text-red-500">*</span></label>
                <input wire:model.live.debounce.400ms="searchBarang" type="text"
                       placeholder="Cari nama atau kode barang..."
                       class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
                @error('selectedBarangId') <p class="form-error">{{ $message }}</p> @enderror

                {{-- Dropdown --}}
                @if ($this->barangSuggestions->isNotEmpty() && !$selectedBarangId)
                <div class="absolute z-20 top-full left-0 right-0 mt-1 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-600 shadow-lg max-h-60 overflow-y-auto">
                    @foreach ($this->barangSuggestions as $b)
                    <button type="button" wire:click="pilihBarang({{ $b->id }}, '{{ addslashes($b->nama) }}')"
                            class="w-full text-left px-4 py-3 hover:bg-blue-50 dark:hover:bg-gray-700 border-b border-gray-100 dark:border-gray-700 last:border-0 transition-colors">
                        <div class="flex items-center justify-between">
                            <div>
                                <span class="font-medium text-sm text-gray-900 dark:text-gray-100">{{ $b->nama }}</span>
                                <span class="text-xs font-mono text-gray-400 ml-2">{{ $b->kode }}</span>
                            </div>
                            <div class="flex items-center gap-2 text-xs">
                                <span class="text-gray-500">{{ $b->satuan }}</span>
                                <span @class(['font-semibold',
                                    'text-red-600' => $b->stok === 0,
                                    'text-amber-600' => $b->stok <= $b->stok_minimum && $b->stok > 0,
                                    'text-gray-700 dark:text-gray-300' => $b->stok > $b->stok_minimum
                                ])>Stok: {{ $b->stok }}</span>
                            </div>
                        </div>
                    </button>
                    @endforeach
                </div>
                @endif

                {{-- Barang dipilih --}}
                @if ($selectedBarangId)
                <div class="mt-2 flex items-center justify-between rounded-lg bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 px-4 py-2">
                    <span class="text-sm font-medium text-blue-700 dark:text-blue-400">✓ {{ $selectedBarangNama }}</span>
                    <button wire:click="resetPilihan" class="text-xs text-red-400 hover:text-red-600">Ganti</button>
                </div>
                @endif
            </div>

            {{-- Tanggal & Filter Tipe --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div class="form-group">
                    <label class="form-label dark:text-gray-300">Dari Tanggal</label>
                    <input wire:model="tanggalMulai" type="date"
                           class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
                    @error('tanggalMulai') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div class="form-group">
                    <label class="form-label dark:text-gray-300">Sampai Tanggal</label>
                    <input wire:model="tanggalAkhir" type="date"
                           class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
                    @error('tanggalAkhir') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div class="form-group">
                    <label class="form-label dark:text-gray-300">Tipe Mutasi</label>
                    <select wire:model="tipeMutasi"
                            class="form-select dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200">
                        @foreach ($this->getTipeOptions() as $val => $lbl)
                            <option value="{{ $val }}">{{ $lbl }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="flex justify-end">
                <button wire:click="lihatKartu" class="btn-primary" wire:loading.attr="disabled">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                    </svg>
                    <span wire:loading.remove wire:target="lihatKartu">Tampilkan Kartu Stok</span>
                    <span wire:loading wire:target="lihatKartu" class="flex items-center gap-2">
                        <div class="spinner h-4 w-4 border-white border-t-transparent"></div> Memuat...
                    </span>
                </button>
            </div>
        </div>
    </div>

    {{-- Hasil Kartu Stok --}}
    @if ($sudahCari && !empty($kartuData))
    @php
        $barang      = $kartuData['barang'];
        $rows        = $kartuData['rows'];
        $saldoAwal   = $kartuData['saldo_awal'];
        $hprAwal     = $kartuData['hpr_awal'];
        $totalMasuk  = $kartuData['total_masuk'];
        $totalKeluar = $kartuData['total_keluar'];
        $saldoAkhir  = $kartuData['saldo_akhir'];
    @endphp

    {{-- Info Barang --}}
    <div class="card">
        <div class="card-body">
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
                <div>
                    <p class="text-gray-500 dark:text-gray-400 text-xs uppercase tracking-wide mb-1">Kode</p>
                    <p class="font-mono font-semibold text-gray-800 dark:text-gray-200">{{ $barang->kode }}</p>
                </div>
                <div class="sm:col-span-2">
                    <p class="text-gray-500 dark:text-gray-400 text-xs uppercase tracking-wide mb-1">Nama Barang</p>
                    <p class="font-semibold text-gray-900 dark:text-white">{{ $barang->nama }}</p>
                    @if($barang->nama_generik)<p class="text-xs text-gray-400 italic">{{ $barang->nama_generik }}</p>@endif
                </div>
                <div>
                    <p class="text-gray-500 dark:text-gray-400 text-xs uppercase tracking-wide mb-1">Satuan</p>
                    <p class="font-medium text-gray-800 dark:text-gray-200">{{ $barang->satuan }}</p>
                </div>
                <div>
                    <p class="text-gray-500 dark:text-gray-400 text-xs uppercase tracking-wide mb-1">Stok Saat Ini</p>
                    <p class="font-bold text-lg {{ $barang->stok === 0 ? 'text-red-600' : ($barang->stok <= $barang->stok_minimum ? 'text-amber-600' : 'text-emerald-600') }}">
                        {{ number_format($barang->stok) }}
                    </p>
                </div>
                <div>
                    <p class="text-gray-500 dark:text-gray-400 text-xs uppercase tracking-wide mb-1">HPR Saat Ini</p>
                    <p class="font-semibold text-gray-800 dark:text-gray-200">Rp {{ number_format($barang->harga_pokok, 0, ',', '.') }}</p>
                </div>
                <div>
                    <p class="text-gray-500 dark:text-gray-400 text-xs uppercase tracking-wide mb-1">Supplier Utama</p>
                    <p class="text-gray-700 dark:text-gray-300">{{ $barang->supplierUtama?->nama ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-gray-500 dark:text-gray-400 text-xs uppercase tracking-wide mb-1">Periode</p>
                    <p class="text-gray-700 dark:text-gray-300 text-xs">
                        {{ \Carbon\Carbon::parse($tanggalMulai)->format('d/m/Y') }} —
                        {{ \Carbon\Carbon::parse($tanggalAkhir)->format('d/m/Y') }}
                    </p>
                </div>
            </div>
        </div>
    </div>

    {{-- Summary Bar --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="card p-4 text-center">
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Saldo Awal</p>
            <p class="text-xl font-bold text-gray-800 dark:text-gray-200">{{ number_format($saldoAwal) }}</p>
        </div>
        <div class="card p-4 text-center border-l-4 border-emerald-400">
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Total Masuk</p>
            <p class="text-xl font-bold text-emerald-600">+{{ number_format($totalMasuk) }}</p>
        </div>
        <div class="card p-4 text-center border-l-4 border-red-400">
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Total Keluar</p>
            <p class="text-xl font-bold text-red-600">-{{ number_format($totalKeluar) }}</p>
        </div>
        <div class="card p-4 text-center border-l-4 border-[#0a3d62]">
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Saldo Akhir</p>
            <p class="text-xl font-bold text-[#0a3d62] dark:text-blue-400">{{ number_format($saldoAkhir) }}</p>
        </div>
    </div>

    {{-- Tabel Kartu Stok --}}
    <div class="card">
        <div class="card-header">
            <h3 class="text-sm font-semibold dark:text-white">Detail Mutasi Stok</h3>
            <div class="flex gap-2">
                <a href="{{ route('inventory.kartu-stok.export-pdf', [
                    'barang_id'     => $selectedBarangId,
                    'tanggal_mulai' => $tanggalMulai,
                    'tanggal_akhir' => $tanggalAkhir,
                    'tipe'          => $tipeMutasi,
                ]) }}" target="_blank" class="btn-danger btn-sm">
                    PDF
                </a>
                <a href="{{ route('inventory.kartu-stok.export-excel', [
                    'barang_id'     => $selectedBarangId,
                    'tanggal_mulai' => $tanggalMulai,
                    'tanggal_akhir' => $tanggalAkhir,
                    'tipe'          => $tipeMutasi,
                ]) }}" class="btn-success btn-sm">
                    Excel
                </a>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-wrapper rounded-none border-0">
                <table class="table" style="min-width:800px">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Waktu</th>
                            <th>Tipe Mutasi</th>
                            <th>Keterangan</th>
                            <th class="text-center text-emerald-600">Masuk</th>
                            <th class="text-center text-red-600">Keluar</th>
                            <th class="text-center font-bold">Saldo</th>
                            <th class="text-right">HPR (Rp)</th>
                            <th>Dicatat Oleh</th>
                        </tr>
                    </thead>
                    <tbody>
                        {{-- Baris Saldo Awal --}}
                        <tr class="bg-blue-50/50 dark:bg-blue-900/10 font-medium">
                            <td class="text-xs text-blue-700 dark:text-blue-400">
                                {{ \Carbon\Carbon::parse($tanggalMulai)->format('d/m/Y') }}
                            </td>
                            <td>—</td>
                            <td><span class="badge-info">Saldo Awal</span></td>
                            <td class="text-xs text-gray-500">Saldo awal periode</td>
                            <td class="text-center">—</td>
                            <td class="text-center">—</td>
                            <td class="text-center font-bold text-gray-900 dark:text-white">{{ number_format($saldoAwal) }}</td>
                            <td class="text-right text-xs text-gray-500">{{ number_format($hprAwal, 0, ',', '.') }}</td>
                            <td>—</td>
                        </tr>

                        @forelse ($rows as $row)
                        <tr @class([
                            'bg-red-50/30 dark:bg-red-900/10' => $row['is_anomali'],
                            'hover:bg-emerald-50/30 dark:hover:bg-emerald-900/10' => $row['masuk'] > 0,
                        ])>
                            <td class="text-sm text-gray-700 dark:text-gray-300">{{ $row['tanggal'] }}</td>
                            <td class="text-xs text-gray-400">{{ $row['waktu'] }}</td>
                            <td>
                                <span @class([
                                    'badge',
                                    'badge-success' => in_array($row['tipe'], ['masuk_pembelian','penyesuaian_masuk']),
                                    'badge-danger'  => in_array($row['tipe'], ['expired','retur_ke_supplier']),
                                    'badge-warning' => !in_array($row['tipe'], ['masuk_pembelian','penyesuaian_masuk','expired','retur_ke_supplier']),
                                ])>
                                    {{ \App\Services\Inventory\KartuStokService::getTipeLabel($row['tipe']) }}
                                </span>
                            </td>
                            <td class="text-xs text-gray-600 dark:text-gray-400 max-w-xs truncate">
                                {{ $row['keterangan'] ?? ($row['referensi_tipe'] ? "{$row['referensi_tipe']} #{$row['referensi_id']}" : '—') }}
                            </td>
                            <td class="text-center">
                                @if($row['masuk'] > 0)
                                <span class="font-semibold text-emerald-600">+{{ number_format($row['masuk']) }}</span>
                                @else
                                <span class="text-gray-300">—</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if($row['keluar'] > 0)
                                <span class="font-semibold text-red-600">-{{ number_format($row['keluar']) }}</span>
                                @else
                                <span class="text-gray-300">—</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <span @class(['font-bold text-sm', 'text-red-600' => $row['is_anomali'], 'text-gray-900 dark:text-white' => !$row['is_anomali']])>
                                    {{ number_format($row['saldo']) }}
                                    @if($row['is_anomali'])
                                    <span class="text-xs text-red-500 block">⚠ Anomali</span>
                                    @endif
                                </span>
                            </td>
                            <td class="text-right text-xs text-gray-500">
                                {{ number_format($row['hpr'], 0, ',', '.') }}
                            </td>
                            <td class="text-xs text-gray-500">{{ $row['user_nama'] }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9">
                                <div class="empty-state py-8">
                                    <p class="empty-state-text">Tidak ada mutasi stok dalam periode ini</p>
                                </div>
                            </td>
                        </tr>
                        @endforelse

                        {{-- Baris Total --}}
                        @if($rows->isNotEmpty())
                        <tr class="bg-gray-50 dark:bg-gray-700/50 font-semibold border-t-2 border-gray-300 dark:border-gray-500">
                            <td colspan="4" class="text-right text-gray-700 dark:text-gray-300 px-4 py-3">
                                Total Periode
                            </td>
                            <td class="text-center text-emerald-600 px-4 py-3">
                                +{{ number_format($totalMasuk) }}
                            </td>
                            <td class="text-center text-red-600 px-4 py-3">
                                -{{ number_format($totalKeluar) }}
                            </td>
                            <td class="text-center text-gray-900 dark:text-white font-black px-4 py-3">
                                {{ number_format($saldoAkhir) }}
                            </td>
                            <td colspan="2"></td>
                        </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @elseif($sudahCari && empty($kartuData['rows'] ?? []))
    <div class="card">
        <div class="card-body">
            <div class="empty-state py-10">
                <p class="empty-state-text">Tidak ada data mutasi untuk barang ini dalam periode yang dipilih</p>
            </div>
        </div>
    </div>
    @endif
</div>
```

### Livewire View — Ringkasan

```blade
{{-- resources/views/livewire/inventory/kartu-stok/kartu-stok-ringkasan.blade.php --}}
<div class="space-y-5">
    {{-- Filter Periode --}}
    <div class="card">
        <div class="card-body">
            <div class="flex flex-wrap gap-3 items-end">
                <div class="form-group">
                    <label class="form-label dark:text-gray-300">Dari</label>
                    <input wire:model.live="tanggalMulai" type="date" class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
                </div>
                <div class="form-group">
                    <label class="form-label dark:text-gray-300">Sampai</label>
                    <input wire:model.live="tanggalAkhir" type="date" class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
                </div>
                <div class="form-group flex-1">
                    <label class="form-label dark:text-gray-300">Cari Barang</label>
                    <input wire:model.live.debounce.400ms="search" type="text" placeholder="Nama / kode..." class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
                </div>
            </div>
        </div>
    </div>

    {{-- Tabel Ringkasan --}}
    <div class="table-wrapper">
        <table class="table">
            <thead>
                <tr>
                    <th>Kode</th>
                    <th>Nama Barang</th>
                    <th>Satuan</th>
                    <th class="text-center text-emerald-600">Total Masuk</th>
                    <th class="text-center text-red-600">Total Keluar</th>
                    <th class="text-center">Transaksi</th>
                    <th class="text-center">Stok Saat Ini</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->ringkasan as $r)
                <tr wire:key="ring-{{ $r->barang_id }}">
                    <td class="font-mono text-xs text-gray-500">{{ $r->barang?->kode }}</td>
                    <td class="font-medium text-gray-900 dark:text-gray-100">{{ $r->barang?->nama }}</td>
                    <td class="text-sm text-gray-500">{{ $r->barang?->satuan }}</td>
                    <td class="text-center font-semibold text-emerald-600">+{{ number_format($r->total_masuk) }}</td>
                    <td class="text-center font-semibold text-red-600">-{{ number_format($r->total_keluar) }}</td>
                    <td class="text-center"><span class="badge-gray">{{ $r->total_transaksi }}</span></td>
                    <td class="text-center">
                        <span @class(['font-bold text-sm',
                            'text-red-600' => $r->barang?->stok === 0,
                            'text-amber-600' => $r->barang && $r->barang->stok <= $r->barang->stok_minimum && $r->barang->stok > 0,
                            'text-gray-800 dark:text-gray-200' => $r->barang && $r->barang->stok > $r->barang->stok_minimum,
                        ])>{{ $r->barang?->stok }}</span>
                    </td>
                    <td>
                        <a href="{{ route('inventory.kartu-stok.index', ['q' => $r->barang?->kode, 'tab' => 'kartu']) }}"
                           class="btn-info btn-sm">Lihat Kartu</a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="8"><div class="empty-state"><p class="empty-state-text">Tidak ada mutasi dalam periode ini</p></div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
```

---

## 10. Routes & RBAC

### Routes

```php
// Tambahkan ke routes/web.php dalam group inventory

Route::prefix('inventory')->name('inventory.')->middleware(['auth','active','permission:obat.view'])->group(function () {

    // ... routes existing ...

    // Kartu Stok
    Route::prefix('kartu-stok')->name('kartu-stok.')->group(function () {
        Route::get('/', fn () => view('inventory.kartu-stok'))->name('index');

        // Export (menggunakan controller untuk generate file)
        Route::get('/export-pdf',   [\App\Http\Controllers\Inventory\KartuStokController::class, 'exportPdf'])
             ->name('export-pdf');
        Route::get('/export-excel', [\App\Http\Controllers\Inventory\KartuStokController::class, 'exportExcel'])
             ->name('export-excel');
    });
});
```

### Controller Export

```php
// app/Http/Controllers/Inventory/KartuStokController.php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Services\Inventory\KartuStokService;
use Illuminate\Http\Request;

class KartuStokController extends Controller
{
    public function __construct(
        private readonly KartuStokService $service
    ) {}

    public function exportPdf(Request $request)
    {
        $request->validate([
            'barang_id'     => 'required|integer|exists:barang,id',
            'tanggal_mulai' => 'required|date',
            'tanggal_akhir' => 'required|date|after_or_equal:tanggal_mulai',
        ]);

        $data = $this->service->getKartuStok(
            $request->barang_id,
            $request->tanggal_mulai,
            $request->tanggal_akhir,
            $request->tipe
        );

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView(
            'inventory.kartu-stok-pdf',
            $data
        )->setPaper('a4', 'landscape');

        $filename = "kartu-stok-{$data['barang']->kode}-{$request->tanggal_mulai}.pdf";
        return $pdf->download($filename);
    }

    public function exportExcel(Request $request)
    {
        $request->validate([
            'barang_id'     => 'required|integer|exists:barang,id',
            'tanggal_mulai' => 'required|date',
            'tanggal_akhir' => 'required|date|after_or_equal:tanggal_mulai',
        ]);

        // Export menggunakan Maatwebsite Excel
        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\KartuStokExport(
                $this->service->getKartuStok(
                    $request->barang_id,
                    $request->tanggal_mulai,
                    $request->tanggal_akhir,
                    $request->tipe
                )
            ),
            "kartu-stok-{$request->barang_id}-{$request->tanggal_mulai}.xlsx"
        );
    }
}
```

### Update Sidebar

```blade
{{-- Di layouts/app.blade.php, setelah link Inventory --}}
{{-- (menu Inventory sudah ada, kartu stok masuk dalam halaman inventory dengan tab) --}}
```

---

## 11. Struktur Folder

```
app/
├── Http/Controllers/Inventory/
│   └── KartuStokController.php       ← Export PDF & Excel
│
├── Livewire/Inventory/KartuStok/
│   ├── KartuStokIndex.php            ← Pilih barang + filter + tampilkan kartu
│   └── KartuStokRingkasan.php        ← Semua barang yang bergerak dalam periode
│
├── Services/Inventory/
│   └── KartuStokService.php          ← getKartuStok(), getRingkasanMutasi()
│
└── Exports/
    └── KartuStokExport.php           ← Maatwebsite Excel export

resources/views/
├── inventory/
│   ├── kartu-stok.blade.php          ← Halaman utama (tab: kartu | ringkasan)
│   └── kartu-stok-pdf.blade.php      ← Template PDF (landscape)
└── livewire/inventory/kartu-stok/
    ├── kartu-stok-index.blade.php    ← Filter + tabel kartu stok
    └── kartu-stok-ringkasan.blade.php← Tabel ringkasan semua barang
```

---

## 12. User Stories

| ID | Persona | Skenario | Expected Behavior |
|----|---------|----------|-------------------|
| **US01** | Apoteker | Lihat kartu stok Paracetamol bulan Mei 2026 | Tampil semua mutasi kronologis + saldo berjalan + summary |
| **US02** | Admin Gudang | Filter hanya mutasi "Pembelian" untuk stok opname | Dropdown filter tipe → hanya baris masuk_pembelian tampil |
| **US03** | Apoteker | Cek stok Amoxicillin turun drastis pada 10 Mei | Baris 10 Mei dengan keluar besar + saldo turun terlihat jelas |
| **US04** | Admin | Export kartu stok untuk audit internal | Klik PDF → file landscape A4 terunduh |
| **US05** | Apoteker | Lihat semua barang yang bergerak bulan ini | Tab Ringkasan → tabel semua barang + total masuk/keluar |
| **US06** | Admin | Kartu stok menampilkan anomali stok negatif | Baris dengan saldo < 0 tampil merah + badge ⚠ Anomali |
| **US07** | Apoteker | Klik "Lihat Kartu" dari tab Ringkasan | Pindah ke tab Kartu dengan barang & periode sudah terisi |

---

## 13. Export PDF/Excel

### Template PDF (`resources/views/inventory/kartu-stok-pdf.blade.php`)

```blade
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8"/>
    <style>
        body { font-family: Arial, sans-serif; font-size: 9pt; }
        h2 { font-size: 12pt; margin: 0; }
        .header { margin-bottom: 10px; border-bottom: 2px solid #0a3d62; padding-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; font-size: 8pt; }
        th { background: #0a3d62; color: white; padding: 5px 6px; text-align: left; }
        td { padding: 4px 6px; border-bottom: 1px solid #e5e7eb; }
        .masuk  { color: #059669; font-weight: bold; }
        .keluar { color: #dc2626; font-weight: bold; }
        .saldo  { font-weight: bold; text-align: center; }
        .total-row { background: #f3f4f6; font-weight: bold; }
        .footer { margin-top: 15px; font-size: 8pt; color: #6b7280; }
    </style>
</head>
<body>
    <div class="header">
        <h2>KARTU STOK BARANG</h2>
        <table style="border:none; margin-top: 6px;">
            <tr>
                <td style="border:none; width:120px; font-weight:bold; padding:2px 0">Kode Barang</td>
                <td style="border:none; padding:2px 0">: {{ $barang->kode }}</td>
                <td style="border:none; width:80px; font-weight:bold; padding:2px 0">Satuan</td>
                <td style="border:none; padding:2px 0">: {{ $barang->satuan }}</td>
            </tr>
            <tr>
                <td style="border:none; font-weight:bold; padding:2px 0">Nama Barang</td>
                <td style="border:none; padding:2px 0">: {{ $barang->nama }}</td>
                <td style="border:none; font-weight:bold; padding:2px 0">Periode</td>
                <td style="border:none; padding:2px 0">: {{ \Carbon\Carbon::parse($tanggal_mulai)->format('d/m/Y') }} — {{ \Carbon\Carbon::parse($tanggal_akhir)->format('d/m/Y') }}</td>
            </tr>
            <tr>
                <td style="border:none; font-weight:bold; padding:2px 0">Generik</td>
                <td style="border:none; padding:2px 0">: {{ $barang->nama_generik ?? '-' }}</td>
                <td style="border:none; font-weight:bold; padding:2px 0">Dicetak</td>
                <td style="border:none; padding:2px 0">: {{ now()->format('d/m/Y H:i') }}</td>
            </tr>
        </table>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width:70px">Tanggal</th>
                <th style="width:40px">Jam</th>
                <th style="width:80px">Tipe Mutasi</th>
                <th>Keterangan</th>
                <th style="width:60px; text-align:center">Masuk</th>
                <th style="width:60px; text-align:center">Keluar</th>
                <th style="width:60px; text-align:center">Saldo</th>
                <th style="width:80px; text-align:right">HPR (Rp)</th>
                <th style="width:90px">Dicatat Oleh</th>
            </tr>
        </thead>
        <tbody>
            {{-- Saldo Awal --}}
            <tr style="background:#eff6ff;">
                <td colspan="4" style="font-weight:bold; color:#1d4ed8;">Saldo Awal Periode</td>
                <td style="text-align:center">—</td>
                <td style="text-align:center">—</td>
                <td class="saldo">{{ number_format($saldo_awal) }}</td>
                <td style="text-align:right">{{ number_format($hpr_awal, 0, ',', '.') }}</td>
                <td>—</td>
            </tr>

            @foreach($rows as $row)
            <tr>
                <td>{{ $row['tanggal'] }}</td>
                <td>{{ $row['waktu'] }}</td>
                <td>{{ \App\Services\Inventory\KartuStokService::getTipeLabel($row['tipe']) }}</td>
                <td style="max-width:180px; overflow:hidden;">{{ $row['keterangan'] ?? ($row['referensi_tipe']."#".$row['referensi_id']) }}</td>
                <td class="masuk" style="text-align:center">{{ $row['masuk'] > 0 ? '+'.number_format($row['masuk']) : '—' }}</td>
                <td class="keluar" style="text-align:center">{{ $row['keluar'] > 0 ? '-'.number_format($row['keluar']) : '—' }}</td>
                <td class="saldo" style="{{ $row['is_anomali'] ? 'color:#dc2626;' : '' }}">{{ number_format($row['saldo']) }}</td>
                <td style="text-align:right">{{ number_format($row['hpr'], 0, ',', '.') }}</td>
                <td>{{ $row['user_nama'] }}</td>
            </tr>
            @endforeach

            {{-- Total --}}
            <tr class="total-row">
                <td colspan="4" style="text-align:right; padding-right:10px;">TOTAL PERIODE</td>
                <td class="masuk" style="text-align:center">+{{ number_format($total_masuk) }}</td>
                <td class="keluar" style="text-align:center">-{{ number_format($total_keluar) }}</td>
                <td class="saldo">{{ number_format($saldo_akhir) }}</td>
                <td colspan="2"></td>
            </tr>
        </tbody>
    </table>

    <div class="footer">
        <p>* Kartu stok ini digenerate otomatis dari sistem EMR. HPR dihitung menggunakan metode Moving Average.</p>
    </div>
</body>
</html>
```

### Export Excel Class

```php
// app/Exports/KartuStokExport.php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\{FromArray, WithHeadings, WithTitle, WithStyles, ShouldAutoSize};
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class KartuStokExport implements FromArray, WithHeadings, WithTitle, ShouldAutoSize
{
    public function __construct(private readonly array $data) {}

    public function title(): string
    {
        return "Kartu Stok {$this->data['barang']->kode}";
    }

    public function headings(): array
    {
        return ['Tanggal','Waktu','Tipe Mutasi','Keterangan','Masuk','Keluar','Saldo','HPR (Rp)','Dicatat Oleh'];
    }

    public function array(): array
    {
        $rows = [];
        // Saldo awal
        $rows[] = [$this->data['tanggal_mulai'],'—','Saldo Awal','Saldo awal periode','—','—',$this->data['saldo_awal'],$this->data['hpr_awal'],'—'];

        foreach ($this->data['rows'] as $r) {
            $rows[] = [
                $r['tanggal'], $r['waktu'],
                \App\Services\Inventory\KartuStokService::getTipeLabel($r['tipe']),
                $r['keterangan'] ?? "{$r['referensi_tipe']}#{$r['referensi_id']}",
                $r['masuk'] > 0 ? $r['masuk'] : '',
                $r['keluar'] > 0 ? $r['keluar'] : '',
                $r['saldo'], $r['hpr'], $r['user_nama'],
            ];
        }

        // Total
        $rows[] = ['','','','TOTAL PERIODE',
            $this->data['total_masuk'],
            $this->data['total_keluar'],
            $this->data['saldo_akhir'],'',''];

        return $rows;
    }
}
```

---

*kartu_stok.md v1.0.0 · Laravel 12 + Livewire 3 · Depends on manajemen_inventory.md*

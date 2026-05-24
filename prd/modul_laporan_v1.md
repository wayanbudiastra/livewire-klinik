# Product Requirements Document (PRD)
# Modul Laporan (Reporting) — v1

| Info | Detail |
|:-----|:-------|
| **Versi** | 1.0.0 |
| **Tanggal** | Mei 2026 |
| **Status** | Draft |
| **Depends On** | `PRD_EMR_Laravel.md` · `setup_pasien.md` · `PRD_Manajemen_Inventory.md` · `PRD_Modul_Kasir_Update.md` |
| **Tech Stack** | Laravel 12 · Livewire 3 · MySQL · Tailwind CSS · DomPDF · Maatwebsite Excel |
| **Scope** | Laporan Registrasi · Pemeriksaan · Kasir · Pharmacy |

---

## Daftar Isi

1. [Ringkasan Eksekutif](#1-ringkasan-eksekutif)
2. [Struktur Menu Laporan](#2-struktur-menu-laporan)
3. [Role & Hak Akses](#3-role--hak-akses)
4. [Konsep Periode Pelaporan](#4-konsep-periode-pelaporan)
5. [Arsitektur Modul Laporan](#5-arsitektur-modul-laporan)
6. [Laporan Registrasi](#6-laporan-registrasi)
7. [Laporan Pemeriksaan](#7-laporan-pemeriksaan)
8. [Laporan Kasir](#8-laporan-kasir)
9. [Laporan Pharmacy](#9-laporan-pharmacy)
10. [Base Class & Komponen Bersama](#10-base-class--komponen-bersama)
11. [Service Layer](#11-service-layer)
12. [Livewire Components](#12-livewire-components)
13. [Export PDF & Excel](#13-export-pdf--excel)
14. [Route & Controller](#14-route--controller)
15. [Struktur Folder](#15-struktur-folder)
16. [User Stories & Business Rules](#16-user-stories--business-rules)

---

## 1. Ringkasan Eksekutif

Modul Laporan menyediakan pusat pelaporan terintegrasi untuk seluruh aktivitas operasional klinik/rumah sakit. Setiap laporan mendukung filter periode (Bulanan, Triwulan, Semester, Tahunan) serta rentang tanggal kustom, dengan kemampuan export ke PDF dan Excel.

```
┌──────────────────────────────────────────────────────────────┐
│                     MODUL LAPORAN                            │
│                                                              │
│  ┌────────────┐  ┌─────────────┐  ┌────────┐  ┌──────────┐  │
│  │ REGISTRASI │  │ PEMERIKSAAN │  │ KASIR  │  │ PHARMACY │  │
│  ├────────────┤  ├─────────────┤  ├────────┤  ├──────────┤  │
│  │• Kunjungan │  │• Diagnosa   │  │• Trx   │  │• Resep   │  │
│  │• Batal Reg │  │• Tindakan   │  │  Kasir │  │• Fast    │  │
│  │• Appointmt │  │• Poli       │  │• Cancel│  │  Moving  │  │
│  │• Rekap WNA │  │• Dokter     │  │  Bill  │  │• Nilai   │  │
│  │            │  │             │  │• Deposit│  │  Invntry │  │
│  └────────────┘  └─────────────┘  └────────┘  └──────────┘  │
│         │              │              │            │         │
│         └──────────────┴──────────────┴────────────┘         │
│                          │                                   │
│              Filter Periode + Export (PDF/Excel)             │
└──────────────────────────────────────────────────────────────┘
```

---

## 2. Struktur Menu Laporan

```
Laporan
├── Registrasi
│   ├── Kunjungan Pasien
│   ├── Batal Registrasi
│   ├── Appointment
│   └── Rekap Warga Negara (WNI/WNA)
│
├── Pemeriksaan
│   ├── Rekap Data Diagnosa
│   ├── Rekap Tindakan
│   ├── Rekap per Poli
│   └── Rekap per Dokter
│
├── Kasir
│   ├── Transaksi Kasir
│   ├── Cancel Bill (Pembatalan)
│   └── Deposit
│
└── Pharmacy
    ├── Rekap Resep
    ├── Obat Fast Moving
    └── Nilai Inventory
```

---

## 3. Role & Hak Akses

| Kategori Laporan | super_admin | admin | dokter | apoteker | kasir | rekam_medis |
|------------------|:-----------:|:-----:|:------:|:--------:|:-----:|:-----------:|
| Registrasi | ✅ | ✅ | 👁 | ❌ | ❌ | ✅ |
| Pemeriksaan | ✅ | ✅ | ✅ | ❌ | ❌ | ✅ |
| Kasir | ✅ | ✅ | ❌ | ❌ | 👁 (sendiri) | ❌ |
| Pharmacy | ✅ | ✅ | ❌ | ✅ | ❌ | ❌ |

> Kasir hanya melihat laporan transaksi dari sesi kas miliknya sendiri (`user_id = auth()->id()`), kecuali admin/super_admin yang melihat semua.

### Permissions (Spatie)

```php
// Tambahkan ke RolePermissionSeeder
$reportPermissions = [
    'laporan.registrasi.view',
    'laporan.pemeriksaan.view',
    'laporan.kasir.view',
    'laporan.kasir.view_all',   // lihat semua kasir, bukan hanya sendiri
    'laporan.pharmacy.view',
    'laporan.export',           // izin export PDF/Excel
];
```

---

## 4. Konsep Periode Pelaporan

Semua laporan mendukung 5 tipe periode yang dipilih via dropdown, plus rentang kustom.

| Tipe | Deskripsi | Contoh Output |
|------|-----------|---------------|
| **Bulanan** | Per bulan kalender | Mei 2026 (01–31 Mei) |
| **Triwulan** | Per kuartal (Q1–Q4) | Q2 2026 (Apr–Jun) |
| **Semester** | Per 6 bulan | Semester 1 2026 (Jan–Jun) |
| **Tahunan** | Per tahun penuh | 2026 (Jan–Des) |
| **Kustom** | Rentang tanggal bebas | 01/05/2026 – 15/05/2026 |

### Helper Periode

```php
// app/Support/PeriodeHelper.php

namespace App\Support;

use Carbon\Carbon;

class PeriodeHelper
{
    /**
     * Resolve tipe periode + nilai → [tanggal_mulai, tanggal_akhir]
     */
    public static function resolve(string $tipe, array $params): array
    {
        $tahun = (int) ($params['tahun'] ?? now()->year);

        return match ($tipe) {
            'bulanan' => self::bulanan($tahun, (int) $params['bulan']),
            'triwulan'=> self::triwulan($tahun, (int) $params['triwulan']),
            'semester'=> self::semester($tahun, (int) $params['semester']),
            'tahunan' => self::tahunan($tahun),
            'kustom'  => [
                Carbon::parse($params['tanggal_mulai'])->startOfDay(),
                Carbon::parse($params['tanggal_akhir'])->endOfDay(),
            ],
            default   => self::bulanan($tahun, now()->month),
        };
    }

    private static function bulanan(int $tahun, int $bulan): array
    {
        $start = Carbon::create($tahun, $bulan, 1)->startOfMonth();
        return [$start, $start->copy()->endOfMonth()];
    }

    private static function triwulan(int $tahun, int $q): array
    {
        $bulanMulai = ($q - 1) * 3 + 1;   // Q1→1, Q2→4, Q3→7, Q4→10
        $start = Carbon::create($tahun, $bulanMulai, 1)->startOfMonth();
        return [$start, $start->copy()->addMonths(2)->endOfMonth()];
    }

    private static function semester(int $tahun, int $s): array
    {
        $bulanMulai = $s === 1 ? 1 : 7;
        $start = Carbon::create($tahun, $bulanMulai, 1)->startOfMonth();
        return [$start, $start->copy()->addMonths(5)->endOfMonth()];
    }

    private static function tahunan(int $tahun): array
    {
        return [
            Carbon::create($tahun, 1, 1)->startOfYear(),
            Carbon::create($tahun, 12, 31)->endOfYear(),
        ];
    }

    public static function label(string $tipe, array $params): string
    {
        $tahun  = $params['tahun'] ?? now()->year;
        $namaBulan = [1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',
                      7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'];

        return match ($tipe) {
            'bulanan' => "{$namaBulan[$params['bulan']]} {$tahun}",
            'triwulan'=> "Triwulan {$params['triwulan']} {$tahun}",
            'semester'=> "Semester {$params['semester']} {$tahun}",
            'tahunan' => "Tahun {$tahun}",
            'kustom'  => Carbon::parse($params['tanggal_mulai'])->format('d/m/Y') . ' – ' .
                         Carbon::parse($params['tanggal_akhir'])->format('d/m/Y'),
            default   => "{$tahun}",
        };
    }
}
```

---

## 5. Arsitektur Modul Laporan

```
┌─────────────────────────────────────────────────────────────┐
│              LIVEWIRE LAPORAN COMPONENTS                     │
│   (extends BaseLaporanComponent — shared filter periode)    │
└────────────────────────────┬────────────────────────────────┘
                             │ filter periode
┌────────────────────────────▼────────────────────────────────┐
│                    LAPORAN SERVICE LAYER                     │
│  RegistrasiLaporanService │ PemeriksaanLaporanService        │
│  KasirLaporanService      │ PharmacyLaporanService           │
│         (query agregat + grouping by periode)               │
└────────────────────────────┬────────────────────────────────┘
                             │
┌────────────────────────────▼────────────────────────────────┐
│                     EXPORT LAYER                             │
│  PDF (DomPDF + Blade) │ Excel (Maatwebsite FromCollection)   │
└────────────────────────────┬────────────────────────────────┘
                             │
┌────────────────────────────▼────────────────────────────────┐
│                        MySQL                                 │
│   kunjungan │ pasien │ soap_note │ tindakan │ billing        │
│   pembayaran_split │ transaksi_deposit │ resep │ barang      │
└──────────────────────────────────────────────────────────────┘
```

---

## 6. Laporan Registrasi

### 6.1 Kunjungan Pasien

Rekap jumlah kunjungan per periode, dengan breakdown per poli, tipe pembayaran, dan pasien baru vs lama.

```php
// app/Services/Laporan/RegistrasiLaporanService.php (method kunjungan)

public function kunjunganPasien(Carbon $mulai, Carbon $akhir): array
{
    $kunjungan = Kunjungan::query()
        ->whereBetween('tanggal', [$mulai, $akhir])
        ->where('status', '!=', 'dibatalkan')
        ->with(['pasien', 'poli', 'dokter.user'])
        ->get();

    // Pasien baru = pasien yang dibuat dalam periode
    $pasienBaru = Pasien::whereBetween('created_at', [$mulai, $akhir])->count();

    return [
        'total_kunjungan'   => $kunjungan->count(),
        'pasien_baru'       => $pasienBaru,
        'pasien_lama'       => $kunjungan->count() - $pasienBaru,
        'per_poli'          => $kunjungan->groupBy('poli.nama')
                                 ->map->count()->sortDesc(),
        'per_tipe_bayar'    => $kunjungan->groupBy('tipe_pembayaran')
                                 ->map->count(),
        'per_hari'          => $kunjungan->groupBy(fn($k) => $k->tanggal->format('Y-m-d'))
                                 ->map->count(),
        'detail'            => $kunjungan,
    ];
}
```

**Kolom Laporan:**

| Kolom | Sumber |
|-------|--------|
| Tanggal | `kunjungan.tanggal` |
| No. Antrean | `kunjungan.nomor_antrean` |
| No. RM | `pasien.nomor_rm` |
| Nama Pasien | `pasien.nama` |
| Poli | `poli.nama` |
| Dokter | `dokter.user.nama` |
| Tipe Bayar | `kunjungan.tipe_pembayaran` |
| Status | `kunjungan.status` |

### 6.2 Batal Registrasi

```php
public function batalRegistrasi(Carbon $mulai, Carbon $akhir): array
{
    $batal = Kunjungan::whereBetween('tanggal', [$mulai, $akhir])
        ->where('status', 'dibatalkan')
        ->with(['pasien', 'poli'])
        ->get();

    return [
        'total_batal' => $batal->count(),
        'per_poli'    => $batal->groupBy('poli.nama')->map->count(),
        'detail'      => $batal,
    ];
}
```

### 6.3 Appointment

> **Catatan:** Memerlukan tabel `appointment` (jika belum ada di modul kunjungan, ditambahkan terpisah). Laporan menampilkan jadwal appointment, status (terjadwal/hadir/tidak hadir/dibatalkan), dan rasio kehadiran.

```php
public function appointment(Carbon $mulai, Carbon $akhir): array
{
    $appointment = Appointment::whereBetween('tanggal_appointment', [$mulai, $akhir])
        ->with(['pasien', 'poli', 'dokter.user'])
        ->get();

    return [
        'total'        => $appointment->count(),
        'hadir'        => $appointment->where('status', 'hadir')->count(),
        'tidak_hadir'  => $appointment->where('status', 'tidak_hadir')->count(),
        'dibatalkan'   => $appointment->where('status', 'dibatalkan')->count(),
        'rasio_hadir'  => $appointment->count() > 0
            ? round($appointment->where('status','hadir')->count() / $appointment->count() * 100, 1)
            : 0,
        'detail'       => $appointment,
    ];
}
```

### 6.4 Rekap Warga Negara (WNI/WNA)

Memanfaatkan field `tipe_pasien` dari `setup_pasien.md`.

```php
public function rekapWargaNegara(Carbon $mulai, Carbon $akhir): array
{
    // Pasien yang berkunjung dalam periode
    $kunjungan = Kunjungan::whereBetween('tanggal', [$mulai, $akhir])
        ->where('status', '!=', 'dibatalkan')
        ->with('pasien')
        ->get();

    $wni = $kunjungan->filter(fn($k) => $k->pasien->tipe_pasien === 'WNI');
    $wna = $kunjungan->filter(fn($k) => $k->pasien->tipe_pasien === 'WNA');

    return [
        'total_wni'      => $wni->pluck('pasien_id')->unique()->count(),
        'total_wna'      => $wna->pluck('pasien_id')->unique()->count(),
        'kunjungan_wni'  => $wni->count(),
        'kunjungan_wna'  => $wna->count(),
        // Breakdown WNA per negara asal
        'wna_per_negara' => $wna->groupBy('pasien.negara_asal')
                              ->map(fn($g) => $g->pluck('pasien_id')->unique()->count())
                              ->sortDesc(),
        'detail_wna'     => $wna->unique('pasien_id')->map(fn($k) => [
            'nomor_rm'    => $k->pasien->nomor_rm,
            'nama'        => $k->pasien->nama,
            'no_paspor'   => $k->pasien->no_paspor,
            'negara_asal' => $k->pasien->negara_asal,
        ]),
    ];
}
```

---

## 7. Laporan Pemeriksaan

### 7.1 Rekap Data Diagnosa

Menghitung frekuensi diagnosa (kode ICD-10) dari `soap_note.icd_codes`.

```php
// app/Services/Laporan/PemeriksaanLaporanService.php

public function rekapDiagnosa(Carbon $mulai, Carbon $akhir): array
{
    $soap = SOAPNote::whereHas('kunjungan', fn($q) =>
            $q->whereBetween('tanggal', [$mulai, $akhir])
        )
        ->whereNotNull('icd_codes')
        ->with('kunjungan.poli')
        ->get();

    // Flatten semua kode ICD dari semua SOAP note
    $diagnosaCounter = [];
    foreach ($soap as $note) {
        foreach ($note->icd_codes ?? [] as $icd) {
            $key = $icd['code'] ?? $icd;
            $diagnosaCounter[$key] = ($diagnosaCounter[$key] ?? 0) + 1;
        }
    }
    arsort($diagnosaCounter);

    return [
        'total_diagnosa'  => array_sum($diagnosaCounter),
        'jumlah_jenis'    => count($diagnosaCounter),
        // 10 besar penyakit
        'sepuluh_besar'   => array_slice($diagnosaCounter, 0, 10, true),
        'semua'           => $diagnosaCounter,
    ];
}
```

**Output utama:** Tabel 10 Besar Penyakit (ranking, kode ICD, nama diagnosa, jumlah kasus, persentase).

### 7.2 Rekap Tindakan

```php
public function rekapTindakan(Carbon $mulai, Carbon $akhir): array
{
    $tindakan = Tindakan::whereHas('kunjungan', fn($q) =>
            $q->whereBetween('tanggal', [$mulai, $akhir])
        )
        ->with('masterTindakan')
        ->get();

    return [
        'total_tindakan' => $tindakan->sum('jumlah'),
        'per_tindakan'   => $tindakan->groupBy('masterTindakan.nama')
                              ->map(fn($g) => [
                                  'jumlah'      => $g->sum('jumlah'),
                                  'total_tarif' => $g->sum(fn($t) =>
                                      $t->jumlah * $t->masterTindakan->tarif),
                              ])
                              ->sortByDesc('jumlah'),
    ];
}
```

### 7.3 Rekap per Poli

```php
public function rekapPerPoli(Carbon $mulai, Carbon $akhir): array
{
    return Kunjungan::whereBetween('tanggal', [$mulai, $akhir])
        ->where('status', '!=', 'dibatalkan')
        ->selectRaw('poli_id, COUNT(*) as total')
        ->with('poli')
        ->groupBy('poli_id')
        ->orderByDesc('total')
        ->get()
        ->map(fn($r) => [
            'poli'  => $r->poli?->nama ?? 'Tanpa Poli',
            'total' => $r->total,
        ])
        ->toArray();
}
```

### 7.4 Rekap per Dokter

```php
public function rekapPerDokter(Carbon $mulai, Carbon $akhir): array
{
    return Kunjungan::whereBetween('tanggal', [$mulai, $akhir])
        ->where('status', 'selesai')
        ->whereNotNull('dokter_id')
        ->selectRaw('dokter_id, COUNT(*) as total_pasien')
        ->with('dokter.user', 'dokter.poli')
        ->groupBy('dokter_id')
        ->orderByDesc('total_pasien')
        ->get()
        ->map(fn($r) => [
            'dokter'       => 'dr. ' . ($r->dokter?->user->nama ?? '-'),
            'spesialisasi' => $r->dokter?->spesialisasi ?? '-',
            'poli'         => $r->dokter?->poli?->nama ?? '-',
            'total_pasien' => $r->total_pasien,
        ])
        ->toArray();
}
```

---

## 8. Laporan Kasir

### 8.1 Transaksi Kasir

Rekap semua transaksi pembayaran per periode, breakdown per metode (memanfaatkan `pembayaran_split` dari `PRD_Modul_Kasir_Update.md`).

```php
// app/Services/Laporan/KasirLaporanService.php

public function transaksiKasir(Carbon $mulai, Carbon $akhir, ?int $userId = null): array
{
    $query = PembayaranSplit::query()
        ->whereHas('billing', fn($q) => $q
            ->where('status', 'lunas')
            ->whereBetween('created_at', [$mulai, $akhir])
        )
        ->with(['billing.kunjungan.pasien', 'user']);

    // Filter per kasir (jika bukan admin)
    if ($userId) {
        $query->where('user_id', $userId);
    }

    $split = $query->get();

    return [
        'total_transaksi'  => $split->pluck('billing_id')->unique()->count(),
        'total_nilai'      => $split->sum('jumlah'),
        'per_metode'       => $split->groupBy('metode')->map(fn($g) => [
            'jumlah_transaksi' => $g->count(),
            'total'            => $g->sum('jumlah'),
        ]),
        'per_kasir'        => $split->groupBy('user.nama')->map(fn($g) => [
            'total' => $g->sum('jumlah'),
            'count' => $g->pluck('billing_id')->unique()->count(),
        ]),
        'per_hari'         => $split->groupBy(fn($s) => $s->created_at->format('Y-m-d'))
                                ->map->sum('jumlah'),
    ];
}
```

### 8.2 Cancel Bill (Pembatalan)

Memanfaatkan kolom `dibatalkan_*` di tabel `billing`.

```php
public function cancelBill(Carbon $mulai, Carbon $akhir): array
{
    $batal = Billing::where('status', 'dibatalkan')
        ->whereBetween('dibatalkan_pada', [$mulai, $akhir])
        ->with(['kunjungan.pasien', 'dibatalkanOleh'])
        ->get();

    return [
        'total_batal'      => $batal->count(),
        'total_nilai_batal'=> $batal->sum('total_tagihan'),
        'per_alasan'       => $batal->groupBy('dibatalkan_alasan')->map->count(),
        'per_petugas'      => $batal->groupBy('dibatalkanOleh.nama')->map->count(),
        'detail'           => $batal->map(fn($b) => [
            'nomor_invoice' => $b->nomor_invoice,
            'tanggal_batal' => $b->dibatalkan_pada?->format('d/m/Y H:i'),
            'pasien'        => $b->kunjungan->pasien->nama,
            'nilai'         => $b->total_tagihan,
            'alasan'        => $b->dibatalkan_alasan,
            'oleh'          => $b->dibatalkanOleh?->nama,
        ]),
    ];
}
```

### 8.3 Deposit

Memanfaatkan tabel `transaksi_deposit`.

```php
public function deposit(Carbon $mulai, Carbon $akhir): array
{
    $trx = TransaksiDeposit::whereBetween('created_at', [$mulai, $akhir])
        ->with('pasien')
        ->get();

    return [
        'total_topup'      => $trx->where('tipe', 'topup')->sum('jumlah'),
        'total_pemakaian'  => $trx->where('tipe', 'pemakaian')->sum('jumlah'),
        'total_refund'     => $trx->where('tipe', 'refund')->sum('jumlah'),
        'jumlah_transaksi' => $trx->count(),
        // Saldo deposit terkini seluruh pasien
        'total_saldo_aktif'=> DepositPasien::sum('saldo'),
        'per_tipe'         => $trx->groupBy('tipe')->map(fn($g) => [
            'count' => $g->count(),
            'total' => $g->sum('jumlah'),
        ]),
        'detail'           => $trx->map(fn($t) => [
            'tanggal'        => $t->created_at->format('d/m/Y H:i'),
            'nomor'          => $t->nomor_transaksi,
            'pasien'         => $t->pasien->nama,
            'nomor_rm'       => $t->pasien->nomor_rm,
            'tipe'           => $t->tipe,
            'jumlah'         => $t->jumlah,
            'saldo_sesudah'  => $t->saldo_sesudah,
        ]),
    ];
}
```

---

## 9. Laporan Pharmacy

### 9.1 Rekap Resep

```php
// app/Services/Laporan/PharmacyLaporanService.php

public function rekapResep(Carbon $mulai, Carbon $akhir): array
{
    $resep = Resep::whereHas('kunjungan', fn($q) =>
            $q->whereBetween('tanggal', [$mulai, $akhir])
        )
        ->with(['items.obat', 'dokter.user'])
        ->get();

    return [
        'total_resep'      => $resep->count(),
        'per_status'       => $resep->groupBy('status')->map->count(),
        'per_dokter'       => $resep->groupBy('dokter.user.nama')->map->count()->sortDesc(),
        'total_item_obat'  => $resep->sum(fn($r) => $r->items->sum('jumlah')),
        // Resep dengan obat racikan vs non-racikan bisa ditambah jika ada flag
    ];
}
```

### 9.2 Obat Fast Moving

Menghitung obat dengan frekuensi keluar tertinggi berdasarkan `mutasi_stok` (tipe `keluar_resep`) dari `PRD_Manajemen_Inventory.md`.

```php
public function obatFastMoving(Carbon $mulai, Carbon $akhir, int $limit = 20): array
{
    $mutasi = MutasiStok::where('tipe', 'keluar_resep')
        ->whereBetween('created_at', [$mulai, $akhir])
        ->selectRaw('barang_id, SUM(jumlah) as total_keluar, COUNT(*) as frekuensi')
        ->with('barang')
        ->groupBy('barang_id')
        ->orderByDesc('total_keluar')
        ->limit($limit)
        ->get();

    return [
        'periode'    => "{$mulai->format('d/m/Y')} – {$akhir->format('d/m/Y')}",
        'data'       => $mutasi->map(fn($m) => [
            'kode'         => $m->barang->kode,
            'nama'         => $m->barang->nama,
            'jenis'        => $m->barang->jenis,
            'total_keluar' => $m->total_keluar,
            'frekuensi'    => $m->frekuensi,       // berapa kali transaksi
            'stok_sekarang'=> $m->barang->stok,
            'satuan'       => $m->barang->satuan,
        ]),
    ];
}
```

### 9.3 Nilai Inventory

Menghitung total nilai stok = `stok × harga_pokok` (HPR) per barang dan total keseluruhan.

```php
public function nilaiInventory(): array
{
    $barang = Barang::where('is_active', true)->get();

    $totalNilai = $barang->sum(fn($b) => $b->stok * $b->harga_pokok);

    return [
        'tanggal_snapshot'  => now()->format('d/m/Y H:i'),
        'total_jenis_barang'=> $barang->count(),
        'total_nilai'       => $totalNilai,
        'per_jenis'         => $barang->groupBy('jenis')->map(fn($g) => [
            'jumlah_item' => $g->count(),
            'total_stok'  => $g->sum('stok'),
            'total_nilai' => $g->sum(fn($b) => $b->stok * $b->harga_pokok),
        ]),
        'detail'            => $barang->map(fn($b) => [
            'kode'         => $b->kode,
            'nama'         => $b->nama,
            'jenis'        => $b->jenis,
            'stok'         => $b->stok,
            'satuan'       => $b->satuan,
            'harga_pokok'  => $b->harga_pokok,
            'nilai'        => $b->stok * $b->harga_pokok,
        ])->sortByDesc('nilai')->values(),
    ];
}
```

> **Catatan:** Nilai Inventory adalah snapshot **saat ini** (real-time), bukan per periode, karena mencerminkan kondisi stok terkini.

---

## 10. Base Class & Komponen Bersama

### 10.1 Base Livewire Component

Semua Livewire laporan mewarisi logika filter periode dari base class.

```php
// app/Livewire/Laporan/BaseLaporanComponent.php

namespace App\Livewire\Laporan;

use Livewire\Component;
use App\Support\PeriodeHelper;
use Carbon\Carbon;

abstract class BaseLaporanComponent extends Component
{
    public string $tipePeriode  = 'bulanan';
    public int    $tahun        = 0;
    public int    $bulan        = 0;
    public int    $triwulan     = 1;
    public int    $semester     = 1;
    public string $tanggalMulai = '';
    public string $tanggalAkhir = '';

    public ?array $hasil = null;

    public function mountPeriode(): void
    {
        $this->tahun = now()->year;
        $this->bulan = now()->month;
    }

    public function getPeriodeRangeProperty(): array
    {
        return PeriodeHelper::resolve($this->tipePeriode, [
            'tahun'         => $this->tahun,
            'bulan'         => $this->bulan,
            'triwulan'      => $this->triwulan,
            'semester'      => $this->semester,
            'tanggal_mulai' => $this->tanggalMulai ?: now()->startOfMonth()->format('Y-m-d'),
            'tanggal_akhir' => $this->tanggalAkhir ?: now()->endOfMonth()->format('Y-m-d'),
        ]);
    }

    public function getPeriodeLabelProperty(): string
    {
        return PeriodeHelper::label($this->tipePeriode, [
            'tahun'    => $this->tahun,
            'bulan'    => $this->bulan,
            'triwulan' => $this->triwulan,
            'semester' => $this->semester,
            'tanggal_mulai' => $this->tanggalMulai,
            'tanggal_akhir' => $this->tanggalAkhir,
        ]);
    }

    /** Dipanggil saat tombol "Tampilkan" diklik — diimplementasikan child class */
    abstract public function generate(): void;
}
```

### 10.2 Blade Component — Filter Periode

```blade
{{-- resources/views/components/laporan/filter-periode.blade.php --}}
<div class="card">
    <div class="card-body">
        <div class="flex flex-wrap items-end gap-3">

            {{-- Tipe Periode --}}
            <div class="form-group">
                <label class="form-label">Tipe Periode</label>
                <select wire:model.live="tipePeriode" class="form-select w-40">
                    <option value="bulanan">Bulanan</option>
                    <option value="triwulan">Triwulan</option>
                    <option value="semester">Semester</option>
                    <option value="tahunan">Tahunan</option>
                    <option value="kustom">Kustom</option>
                </select>
            </div>

            {{-- Tahun (untuk semua kecuali kustom) --}}
            @if($tipePeriode !== 'kustom')
            <div class="form-group">
                <label class="form-label">Tahun</label>
                <select wire:model.live="tahun" class="form-select w-28">
                    @for($y = now()->year; $y >= now()->year - 5; $y--)
                        <option value="{{ $y }}">{{ $y }}</option>
                    @endfor
                </select>
            </div>
            @endif

            {{-- Bulan --}}
            @if($tipePeriode === 'bulanan')
            <div class="form-group">
                <label class="form-label">Bulan</label>
                <select wire:model.live="bulan" class="form-select w-36">
                    @foreach(['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'] as $i => $nama)
                        <option value="{{ $i + 1 }}">{{ $nama }}</option>
                    @endforeach
                </select>
            </div>
            @endif

            {{-- Triwulan --}}
            @if($tipePeriode === 'triwulan')
            <div class="form-group">
                <label class="form-label">Triwulan</label>
                <select wire:model.live="triwulan" class="form-select w-32">
                    <option value="1">Q1 (Jan–Mar)</option>
                    <option value="2">Q2 (Apr–Jun)</option>
                    <option value="3">Q3 (Jul–Sep)</option>
                    <option value="4">Q4 (Okt–Des)</option>
                </select>
            </div>
            @endif

            {{-- Semester --}}
            @if($tipePeriode === 'semester')
            <div class="form-group">
                <label class="form-label">Semester</label>
                <select wire:model.live="semester" class="form-select w-40">
                    <option value="1">Semester 1 (Jan–Jun)</option>
                    <option value="2">Semester 2 (Jul–Des)</option>
                </select>
            </div>
            @endif

            {{-- Kustom --}}
            @if($tipePeriode === 'kustom')
            <div class="form-group">
                <label class="form-label">Dari</label>
                <input type="date" wire:model.live="tanggalMulai" class="form-input" />
            </div>
            <div class="form-group">
                <label class="form-label">Sampai</label>
                <input type="date" wire:model.live="tanggalAkhir" class="form-input" />
            </div>
            @endif

            {{-- Tombol Aksi --}}
            <div class="flex gap-2 ml-auto">
                <button type="button" wire:click="generate" class="btn-primary">
                    <span wire:loading.remove wire:target="generate">📊 Tampilkan</span>
                    <span wire:loading wire:target="generate" class="flex items-center gap-2">
                        <div class="spinner w-4 h-4"></div> Memuat...
                    </span>
                </button>

                @can('laporan.export')
                @if($hasil)
                <button type="button" wire:click="exportPdf" class="btn-danger">📄 PDF</button>
                <button type="button" wire:click="exportExcel" class="btn-success">📊 Excel</button>
                @endif
                @endcan
            </div>
        </div>
    </div>
</div>
```

---

## 11. Service Layer

```
app/Services/Laporan/
├── RegistrasiLaporanService.php   # kunjungan, batalReg, appointment, rekapWargaNegara
├── PemeriksaanLaporanService.php  # rekapDiagnosa, rekapTindakan, rekapPerPoli, rekapPerDokter
├── KasirLaporanService.php        # transaksiKasir, cancelBill, deposit
└── PharmacyLaporanService.php     # rekapResep, obatFastMoving, nilaiInventory
```

Setiap service menerima `Carbon $mulai, Carbon $akhir` dan mengembalikan array agregat siap-render (lihat contoh tiap method di Section 6–9).

---

## 12. Livewire Components

```
app/Livewire/Laporan/
├── BaseLaporanComponent.php       # abstract — filter periode + export trait
│
├── Registrasi/
│   ├── KunjunganPasienReport.php
│   ├── BatalRegistrasiReport.php
│   ├── AppointmentReport.php
│   └── RekapWargaNegaraReport.php
│
├── Pemeriksaan/
│   ├── RekapDiagnosaReport.php
│   ├── RekapTindakanReport.php
│   ├── RekapPoliReport.php
│   └── RekapDokterReport.php
│
├── Kasir/
│   ├── TransaksiKasirReport.php
│   ├── CancelBillReport.php
│   └── DepositReport.php
│
└── Pharmacy/
    ├── RekapResepReport.php
    ├── ObatFastMovingReport.php
    └── NilaiInventoryReport.php
```

### Contoh Implementasi Child Component

```php
// app/Livewire/Laporan/Registrasi/KunjunganPasienReport.php

namespace App\Livewire\Laporan\Registrasi;

use App\Livewire\Laporan\BaseLaporanComponent;
use App\Services\Laporan\RegistrasiLaporanService;
use App\Exports\Laporan\KunjunganPasienExport;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;

class KunjunganPasienReport extends BaseLaporanComponent
{
    public function mount(): void
    {
        $this->mountPeriode();
    }

    public function generate(): void
    {
        [$mulai, $akhir] = $this->periodeRange;
        $this->hasil = app(RegistrasiLaporanService::class)
            ->kunjunganPasien($mulai, $akhir);
    }

    public function exportPdf()
    {
        [$mulai, $akhir] = $this->periodeRange;
        $data = app(RegistrasiLaporanService::class)->kunjunganPasien($mulai, $akhir);

        $pdf = Pdf::loadView('laporan.pdf.kunjungan-pasien', [
            'data'   => $data,
            'label'  => $this->periodeLabel,
            'mulai'  => $mulai,
            'akhir'  => $akhir,
        ])->setPaper('a4', 'landscape');

        return response()->streamDownload(
            fn() => print($pdf->output()),
            "Laporan-Kunjungan-{$this->periodeLabel}.pdf"
        );
    }

    public function exportExcel()
    {
        [$mulai, $akhir] = $this->periodeRange;
        return Excel::download(
            new KunjunganPasienExport($mulai, $akhir),
            "Laporan-Kunjungan-{$this->periodeLabel}.xlsx"
        );
    }

    public function render()
    {
        return view('livewire.laporan.registrasi.kunjungan-pasien-report');
    }
}
```

### Contoh Blade View Report

```blade
{{-- resources/views/livewire/laporan/registrasi/kunjungan-pasien-report.blade.php --}}
<div class="space-y-5">

    {{-- Header --}}
    <div class="page-header">
        <div>
            <h1 class="page-title">Laporan Kunjungan Pasien</h1>
            <p class="page-subtitle">Rekap kunjungan pasien per periode</p>
        </div>
    </div>

    {{-- Filter Periode (shared component) --}}
    <x-laporan.filter-periode />

    {{-- Hasil --}}
    @if($hasil)
    <div wire:loading.remove wire:target="generate">

        {{-- Summary Cards --}}
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-5">
            <div class="stat-card">
                <div class="stat-icon bg-blue-50">
                    <svg class="w-6 h-6 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </div>
                <div>
                    <p class="stat-value">{{ number_format($hasil['total_kunjungan']) }}</p>
                    <p class="stat-label">Total Kunjungan</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon bg-emerald-50">
                    <svg class="w-6 h-6 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                </div>
                <div>
                    <p class="stat-value">{{ number_format($hasil['pasien_baru']) }}</p>
                    <p class="stat-label">Pasien Baru</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon bg-purple-50">
                    <svg class="w-6 h-6 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                </div>
                <div>
                    <p class="stat-value">{{ number_format($hasil['pasien_lama']) }}</p>
                    <p class="stat-label">Pasien Lama</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon bg-amber-50">
                    <svg class="w-6 h-6 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1"/>
                    </svg>
                </div>
                <div>
                    <p class="stat-value">{{ $hasil['per_poli']->count() }}</p>
                    <p class="stat-label">Poli Aktif</p>
                </div>
            </div>
        </div>

        {{-- Breakdown per Poli --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
            <div class="card">
                <div class="card-header"><h3 class="text-sm font-semibold text-gray-700">Kunjungan per Poli</h3></div>
                <div class="card-body p-0">
                    <table class="table">
                        <thead><tr><th>Poli</th><th class="text-right">Jumlah</th></tr></thead>
                        <tbody>
                            @foreach($hasil['per_poli'] as $poli => $jumlah)
                            <tr>
                                <td>{{ $poli ?? 'Tanpa Poli' }}</td>
                                <td class="text-right font-medium">{{ number_format($jumlah) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h3 class="text-sm font-semibold text-gray-700">Per Tipe Pembayaran</h3></div>
                <div class="card-body p-0">
                    <table class="table">
                        <thead><tr><th>Tipe</th><th class="text-right">Jumlah</th></tr></thead>
                        <tbody>
                            @foreach($hasil['per_tipe_bayar'] as $tipe => $jumlah)
                            <tr>
                                <td class="capitalize">{{ $tipe ?? 'Umum' }}</td>
                                <td class="text-right font-medium">{{ number_format($jumlah) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    @else
    <div class="card">
        <div class="card-body">
            <div class="empty-state">
                <svg class="empty-state-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <p class="empty-state-text">Pilih periode dan klik "Tampilkan" untuk melihat laporan</p>
            </div>
        </div>
    </div>
    @endif
</div>
```

---

## 13. Export PDF & Excel

### 13.1 Excel Export (Maatwebsite)

```php
// app/Exports/Laporan/KunjunganPasienExport.php

namespace App\Exports\Laporan;

use App\Services\Laporan\RegistrasiLaporanService;
use Maatwebsite\Excel\Concerns\{FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles};
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Carbon\Carbon;

class KunjunganPasienExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithTitle
{
    public function __construct(
        private Carbon $mulai,
        private Carbon $akhir
    ) {}

    public function collection()
    {
        return app(RegistrasiLaporanService::class)
            ->kunjunganPasien($this->mulai, $this->akhir)['detail'];
    }

    public function headings(): array
    {
        return ['Tanggal', 'No. Antrean', 'No. RM', 'Nama Pasien', 'Poli', 'Dokter', 'Tipe Bayar', 'Status'];
    }

    public function map($k): array
    {
        return [
            $k->tanggal->format('d/m/Y H:i'),
            $k->nomor_antrean,
            $k->pasien->nomor_rm,
            $k->pasien->nama,
            $k->poli?->nama ?? '-',
            $k->dokter?->user->nama ?? '-',
            $k->tipe_pembayaran ?? 'Umum',
            ucfirst($k->status),
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [1 => ['font' => ['bold' => true]]];
    }

    public function title(): string
    {
        return 'Kunjungan Pasien';
    }
}
```

### 13.2 PDF Export (DomPDF + Blade)

```blade
{{-- resources/views/laporan/pdf/kunjungan-pasien.blade.php --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #111; }
        .header { text-align: center; border-bottom: 2px solid #1d4ed8; padding-bottom: 8px; margin-bottom: 12px; }
        .header h1 { font-size: 16px; color: #1d4ed8; }
        .header .sub { font-size: 11px; color: #555; }
        .periode { text-align: center; font-size: 11px; margin-bottom: 10px; font-weight: bold; }
        .summary { display: flex; justify-content: space-around; margin: 12px 0; }
        .summary-item { text-align: center; }
        .summary-value { font-size: 18px; font-weight: bold; color: #1d4ed8; }
        .summary-label { font-size: 9px; color: #666; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 4px 6px; text-align: left; }
        th { background: #f3f4f6; font-weight: bold; font-size: 9px; }
        .footer { margin-top: 20px; font-size: 8px; color: #999; text-align: right; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ config('emr.klinik_nama', 'Klinik Sehat') }}</h1>
        <div class="sub">Laporan Kunjungan Pasien</div>
    </div>

    <div class="periode">Periode: {{ $label }}</div>

    <div class="summary">
        <div class="summary-item">
            <div class="summary-value">{{ number_format($data['total_kunjungan']) }}</div>
            <div class="summary-label">Total Kunjungan</div>
        </div>
        <div class="summary-item">
            <div class="summary-value">{{ number_format($data['pasien_baru']) }}</div>
            <div class="summary-label">Pasien Baru</div>
        </div>
        <div class="summary-item">
            <div class="summary-value">{{ number_format($data['pasien_lama']) }}</div>
            <div class="summary-label">Pasien Lama</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Tanggal</th><th>No. Antrean</th><th>No. RM</th><th>Nama</th>
                <th>Poli</th><th>Dokter</th><th>Tipe Bayar</th><th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data['detail'] as $k)
            <tr>
                <td>{{ $k->tanggal->format('d/m/Y H:i') }}</td>
                <td>{{ $k->nomor_antrean }}</td>
                <td>{{ $k->pasien->nomor_rm }}</td>
                <td>{{ $k->pasien->nama }}</td>
                <td>{{ $k->poli?->nama ?? '-' }}</td>
                <td>{{ $k->dokter?->user->nama ?? '-' }}</td>
                <td>{{ $k->tipe_pembayaran ?? 'Umum' }}</td>
                <td>{{ ucfirst($k->status) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Dicetak pada {{ now()->format('d/m/Y H:i') }} oleh {{ auth()->user()->nama }}
    </div>
</body>
</html>
```

---

## 14. Route & Controller

```php
// routes/web.php — tambahkan group laporan

Route::middleware(['auth'])->prefix('laporan')->name('laporan.')->group(function () {

    // Registrasi
    Route::middleware('permission:laporan.registrasi.view')->prefix('registrasi')->name('registrasi.')->group(function () {
        Route::get('/kunjungan',      [LaporanRegistrasiController::class, 'kunjungan'])->name('kunjungan');
        Route::get('/batal',          [LaporanRegistrasiController::class, 'batal'])->name('batal');
        Route::get('/appointment',    [LaporanRegistrasiController::class, 'appointment'])->name('appointment');
        Route::get('/warga-negara',   [LaporanRegistrasiController::class, 'wargaNegara'])->name('warga-negara');
    });

    // Pemeriksaan
    Route::middleware('permission:laporan.pemeriksaan.view')->prefix('pemeriksaan')->name('pemeriksaan.')->group(function () {
        Route::get('/diagnosa',  [LaporanPemeriksaanController::class, 'diagnosa'])->name('diagnosa');
        Route::get('/tindakan',  [LaporanPemeriksaanController::class, 'tindakan'])->name('tindakan');
        Route::get('/poli',      [LaporanPemeriksaanController::class, 'poli'])->name('poli');
        Route::get('/dokter',    [LaporanPemeriksaanController::class, 'dokter'])->name('dokter');
    });

    // Kasir
    Route::middleware('permission:laporan.kasir.view')->prefix('kasir')->name('kasir.')->group(function () {
        Route::get('/transaksi', [LaporanKasirController::class, 'transaksi'])->name('transaksi');
        Route::get('/cancel-bill',[LaporanKasirController::class, 'cancelBill'])->name('cancel-bill');
        Route::get('/deposit',   [LaporanKasirController::class, 'deposit'])->name('deposit');
    });

    // Pharmacy
    Route::middleware('permission:laporan.pharmacy.view')->prefix('pharmacy')->name('pharmacy.')->group(function () {
        Route::get('/resep',         [LaporanPharmacyController::class, 'resep'])->name('resep');
        Route::get('/fast-moving',   [LaporanPharmacyController::class, 'fastMoving'])->name('fast-moving');
        Route::get('/nilai-inventory',[LaporanPharmacyController::class, 'nilaiInventory'])->name('nilai-inventory');
    });
});
```

---

## 15. Struktur Folder

```
app/
├── Http/Controllers/Laporan/
│   ├── LaporanRegistrasiController.php
│   ├── LaporanPemeriksaanController.php
│   ├── LaporanKasirController.php
│   └── LaporanPharmacyController.php
│
├── Livewire/Laporan/           # (lihat Section 12)
│
├── Services/Laporan/           # (lihat Section 11)
│   ├── RegistrasiLaporanService.php
│   ├── PemeriksaanLaporanService.php
│   ├── KasirLaporanService.php
│   └── PharmacyLaporanService.php
│
├── Exports/Laporan/            # Maatwebsite Excel exports
│   ├── KunjunganPasienExport.php
│   ├── BatalRegistrasiExport.php
│   ├── RekapDiagnosaExport.php
│   ├── RekapTindakanExport.php
│   ├── TransaksiKasirExport.php
│   ├── CancelBillExport.php
│   ├── DepositExport.php
│   ├── RekapResepExport.php
│   ├── ObatFastMovingExport.php
│   └── NilaiInventoryExport.php
│
└── Support/
    └── PeriodeHelper.php       # resolve & label periode

resources/views/
├── laporan/
│   ├── pdf/                    # Template PDF DomPDF per laporan
│   │   ├── kunjungan-pasien.blade.php
│   │   ├── rekap-diagnosa.blade.php
│   │   ├── transaksi-kasir.blade.php
│   │   └── ... (per laporan)
│   ├── registrasi/index.blade.php
│   ├── pemeriksaan/index.blade.php
│   ├── kasir/index.blade.php
│   └── pharmacy/index.blade.php
│
├── livewire/laporan/           # Blade views Livewire per laporan
│
└── components/laporan/
    └── filter-periode.blade.php   # Komponen filter bersama
```

---

## 16. User Stories & Business Rules

| ID | Persona | Skenario | Expected Behavior |
|----|---------|----------|-------------------|
| US01 | Admin | Pilih Laporan Kunjungan periode Bulanan Mei 2026 | Tampilkan total kunjungan, pasien baru/lama, breakdown per poli |
| US02 | Admin | Ganti periode ke Triwulan Q2 2026 | Data otomatis re-query untuk rentang Apr–Jun 2026 |
| US03 | Rekam Medis | Export laporan kunjungan ke Excel | File `.xlsx` ter-download dengan heading & data lengkap |
| US04 | Admin | Lihat Rekap Warga Negara | Tampil total WNI vs WNA, WNA dipecah per negara asal |
| US05 | Dokter | Lihat Rekap Diagnosa periode Tahunan | Tampil 10 besar penyakit dengan kode ICD & persentase |
| US06 | Admin | Lihat Rekap per Dokter | Ranking dokter by jumlah pasien, dengan spesialisasi & poli |
| US07 | Kasir | Lihat Transaksi Kasir | Hanya tampil transaksi dari user_id sendiri |
| US08 | Admin | Lihat Transaksi Kasir | Tampil semua kasir (permission `view_all`), breakdown per kasir & metode |
| US09 | Admin | Lihat Cancel Bill | Tampil daftar pembatalan + alasan + petugas yang membatalkan |
| US10 | Admin | Lihat Laporan Deposit | Tampil total topup, pemakaian, refund, dan saldo aktif seluruh pasien |
| US11 | Apoteker | Lihat Obat Fast Moving Triwulan | Ranking obat by total keluar dari mutasi_stok tipe keluar_resep |
| US12 | Apoteker | Lihat Nilai Inventory | Snapshot real-time: total nilai = Σ(stok × HPR), breakdown per jenis |
| US13 | Kasir | Coba akses Laporan Pharmacy | Ditolak — tidak punya permission `laporan.pharmacy.view` |
| US14 | Admin | Export PDF tanpa generate dulu | Tombol export hanya muncul setelah `$hasil` terisi |
| US15 | Admin | Pilih periode Kustom 01–15 Mei | Field tanggal mulai & akhir muncul, query sesuai rentang |

---

## Catatan Dependensi Antar Modul

| Laporan | Bergantung pada Tabel/Modul |
|---------|----------------------------|
| Kunjungan, Batal Reg | `kunjungan`, `pasien` (EMR base) |
| Rekap Warga Negara | `pasien.tipe_pasien`, `negara_asal` (setup_pasien.md) |
| Appointment | tabel `appointment` (perlu dibuat jika belum ada) |
| Rekap Diagnosa | `soap_note.icd_codes` (EMR base) |
| Rekap Tindakan | `tindakan`, `master_tindakan` (EMR base) |
| Transaksi Kasir | `pembayaran_split`, `billing` (Kasir Update) |
| Cancel Bill | `billing.dibatalkan_*` (Kasir Update) |
| Deposit | `transaksi_deposit`, `deposit_pasien` (Kasir Update) |
| Obat Fast Moving | `mutasi_stok` tipe `keluar_resep` (Inventory) |
| Nilai Inventory | `barang.stok × harga_pokok` (Inventory) |

> **Appointment:** Modul appointment belum didefinisikan di PRD sebelumnya. Jika belum diimplementasikan, laporan ini bersifat placeholder hingga tabel `appointment` dibuat. Disarankan dibuat di PRD terpisah (`PRD_Appointment.md`).

---

*PRD_Laporan_v1.md v1.0.0*  
*Konsisten dengan PRD_EMR_Laravel.md · setup_pasien.md · PRD_Manajemen_Inventory.md · PRD_Modul_Kasir_Update.md*  
*(Laravel 12 · Livewire 3 · MySQL · Tailwind CSS · DomPDF · Maatwebsite Excel)*
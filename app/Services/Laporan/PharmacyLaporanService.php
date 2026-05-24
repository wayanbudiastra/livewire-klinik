<?php

namespace App\Services\Laporan;

use App\Models\Barang;
use App\Models\MutasiStok;
use App\Models\Resep;
use Carbon\Carbon;

class PharmacyLaporanService
{
    public function rekapResep(Carbon $mulai, Carbon $akhir): array
    {
        $resep = Resep::whereHas('kunjungan', fn ($q) =>
                $q->whereBetween('tanggal', [$mulai, $akhir])
            )
            ->with(['itemResep.barang', 'dokter.user'])
            ->get();

        return [
            'total_resep'     => $resep->count(),
            'per_status'      => $resep->groupBy('status')->map->count(),
            'per_dokter'      => $resep->groupBy(fn ($r) => $r->dokter?->user?->nama ?? 'N/A')
                                   ->map->count()->sortDesc(),
            'total_item_obat' => $resep->sum(fn ($r) => $r->itemResep->sum('jumlah')),
        ];
    }

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
            'periode' => "{$mulai->format('d/m/Y')} – {$akhir->format('d/m/Y')}",
            'data'    => $mutasi->map(fn ($m) => [
                'kode'          => $m->barang->kode,
                'nama'          => $m->barang->nama,
                'jenis'         => $m->barang->jenis,
                'total_keluar'  => $m->total_keluar,
                'frekuensi'     => $m->frekuensi,
                'stok_sekarang' => $m->barang->stok,
                'satuan'        => $m->barang->satuan,
            ]),
        ];
    }

    public function nilaiInventory(): array
    {
        $barang = Barang::where('is_active', true)->get();

        $totalNilai = $barang->sum(fn ($b) => $b->stok * $b->harga_pokok);

        return [
            'tanggal_snapshot'   => now()->format('d/m/Y H:i'),
            'total_jenis_barang' => $barang->count(),
            'total_nilai'        => $totalNilai,
            'per_jenis'          => $barang->groupBy('jenis')->map(fn ($g) => [
                'jumlah_item' => $g->count(),
                'total_stok'  => $g->sum('stok'),
                'total_nilai' => $g->sum(fn ($b) => $b->stok * $b->harga_pokok),
            ]),
            'detail'             => $barang->map(fn ($b) => [
                'kode'        => $b->kode,
                'nama'        => $b->nama,
                'jenis'       => $b->jenis,
                'stok'        => $b->stok,
                'satuan'      => $b->satuan,
                'harga_pokok' => $b->harga_pokok,
                'nilai'       => $b->stok * $b->harga_pokok,
            ])->sortByDesc('nilai')->values(),
        ];
    }
}

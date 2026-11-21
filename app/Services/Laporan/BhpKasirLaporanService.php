<?php

namespace App\Services\Laporan;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Laporan BACA-SAJA BHP dan obat yang diinput kasir (pemakaian_alkes sumber 'kasir').
 * Tidak menampilkan nominal uang. Lihat docs/PERANCANGAN_BHP_KASIR.md.
 */
class BhpKasirLaporanService
{
    /** Batas baris agar halaman tetap ringan di server 2GB RAM. */
    public const BATAS_BARIS = 1000;

    public function laporan(Carbon $mulai, Carbon $akhir, ?int $userId = null): array
    {
        $query = DB::table('pemakaian_alkes as pa')
            ->join('kunjungan as k', 'k.id', '=', 'pa.kunjungan_id')
            ->join('pasien as p', 'p.id', '=', 'k.pasien_id')
            ->join('barang as b', 'b.id', '=', 'pa.barang_id')
            ->leftJoin('users as u', 'u.id', '=', 'pa.dicatat_oleh')
            ->where('pa.sumber', 'kasir')
            ->whereBetween('pa.created_at', [$mulai, $akhir])
            ->when($userId, fn ($q) => $q->where('pa.dicatat_oleh', $userId));

        $totalBaris = (clone $query)->count();
        $totalQty   = (int) (clone $query)->sum('pa.jumlah');

        $detail = $query
            ->orderByDesc('pa.created_at')
            ->limit(self::BATAS_BARIS)
            ->get([
                'pa.created_at', 'p.nomor_rm', 'p.nama as pasien',
                'b.nama as barang', 'b.jenis', 'b.satuan', 'pa.jumlah',
                'u.nama as kasir', 'pa.stok_dipotong_at',
            ])
            ->map(fn ($r) => [
                'tanggal'  => Carbon::parse($r->created_at)->format('d/m/Y H:i'),
                'nomor_rm' => $r->nomor_rm,
                'pasien'   => $r->pasien,
                'barang'   => $r->barang,
                'jenis'    => $r->jenis === 'obat' ? 'Obat' : 'BHP',
                'qty'      => (int) $r->jumlah,
                'satuan'   => $r->satuan,
                'kasir'    => $r->kasir ?? '-',
                'status'   => $r->stok_dipotong_at
                    ? 'Stok dipotong ' . Carbon::parse($r->stok_dipotong_at)->format('d/m/Y')
                    : 'Menunggu lunas',
            ])
            ->all();

        return [
            'total_baris' => $totalBaris,
            'total_qty'   => $totalQty,
            'terpotong'   => $totalBaris > self::BATAS_BARIS,
            'batas'       => self::BATAS_BARIS,
            'detail'      => $detail,
        ];
    }
}

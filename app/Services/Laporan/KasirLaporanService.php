<?php

namespace App\Services\Laporan;

use App\Models\DepositPasien;
use App\Models\Invoice;
use App\Models\PembayaranSplit;
use App\Models\TransaksiDeposit;
use Carbon\Carbon;

class KasirLaporanService
{
    public function transaksiKasir(Carbon $mulai, Carbon $akhir, ?int $userId = null): array
    {
        $query = PembayaranSplit::query()
            ->whereHas('billing', fn ($q) => $q
                ->where('status', 'lunas')
                ->whereBetween('created_at', [$mulai, $akhir])
            )
            ->with(['billing.kunjungan.pasien', 'user']);

        if ($userId) {
            $query->where('user_id', $userId);
        }

        $split = $query->get();

        return [
            'total_transaksi' => $split->pluck('billing_id')->unique()->count(),
            'total_nilai'     => $split->sum('jumlah'),
            'per_metode'      => $split->groupBy('metode')->map(fn ($g) => [
                'jumlah_transaksi' => $g->count(),
                'total'            => $g->sum('jumlah'),
            ]),
            'per_kasir'       => $split->groupBy(fn ($s) => $s->user?->nama ?? 'N/A')
                                   ->map(fn ($g) => [
                                       'total' => $g->sum('jumlah'),
                                       'count' => $g->pluck('billing_id')->unique()->count(),
                                   ]),
            'per_hari'        => $split->groupBy(fn ($s) => $s->created_at->format('Y-m-d'))
                                   ->map->sum('jumlah'),
        ];
    }

    public function cancelBill(Carbon $mulai, Carbon $akhir): array
    {
        $batal = Invoice::where('status', 'dibatalkan')
            ->whereBetween('dibatalkan_pada', [$mulai, $akhir])
            ->with(['kunjungan.pasien', 'dibatalkanOleh'])
            ->get();

        return [
            'total_batal'       => $batal->count(),
            'total_nilai_batal' => $batal->sum('total_tagihan'),
            'per_alasan'        => $batal->groupBy('cancel_reason')->map->count(),
            'per_petugas'       => $batal->groupBy(fn ($b) => $b->dibatalkanOleh?->nama ?? 'N/A')->map->count(),
            'detail'            => $batal->map(fn ($b) => [
                'nomor_invoice' => $b->nomor_invoice,
                'tanggal_batal' => $b->dibatalkan_pada?->format('d/m/Y H:i'),
                'pasien'        => $b->kunjungan->pasien->nama,
                'nilai'         => $b->total_tagihan,
                'alasan'        => $b->cancel_reason,
                'oleh'          => $b->dibatalkanOleh?->nama,
            ]),
        ];
    }

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
            'total_saldo_aktif'=> DepositPasien::sum('saldo'),
            'per_tipe'         => $trx->groupBy('tipe')->map(fn ($g) => [
                'count' => $g->count(),
                'total' => $g->sum('jumlah'),
            ]),
            'detail'           => $trx->map(fn ($t) => [
                'tanggal'       => $t->created_at->format('d/m/Y H:i'),
                'nomor'         => $t->nomor_transaksi,
                'pasien'        => $t->pasien->nama,
                'nomor_rm'      => $t->pasien->nomor_rm,
                'tipe'          => $t->tipe,
                'jumlah'        => $t->jumlah,
                'saldo_sesudah' => $t->saldo_sesudah,
            ]),
        ];
    }
}

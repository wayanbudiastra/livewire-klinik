<?php

namespace App\Services\Laporan;

use App\Models\DepositPasien;
use App\Models\Invoice;
use App\Models\PembayaranSplit;
use App\Models\TransaksiDeposit;
use App\Models\TransaksiRitel;
use Carbon\Carbon;

class KasirLaporanService
{
    public function transaksiKasir(Carbon $mulai, Carbon $akhir, ?int $userId = null): array
    {
        // ── Billing pasien (PembayaranSplit) ────────────────────────────────
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

        // ── Transaksi ritel (dibayar/selesai dalam periode) ─────────────────
        $ritelQuery = TransaksiRitel::query()
            ->whereIn('status', ['dibayar', 'selesai'])
            ->whereBetween('dibayar_at', [$mulai, $akhir])
            ->with('kasir');

        if ($userId) {
            $ritelQuery->where('kasir_id', $userId);
        }

        $ritel = $ritelQuery->get();

        // ── Gabung per-metode ────────────────────────────────────────────────
        $perMetode = $split->groupBy('metode')->map(fn ($g) => [
            'jumlah_transaksi' => $g->count(),
            'total'            => $g->sum('jumlah'),
        ]);

        // Ritel: map metode ke kategori laporan (kartu → kartu, split → split)
        foreach ($ritel->groupBy('metode_bayar') as $metode => $g) {
            $existing = $perMetode->get($metode, ['jumlah_transaksi' => 0, 'total' => 0]);
            $perMetode[$metode] = [
                'jumlah_transaksi' => $existing['jumlah_transaksi'] + $g->count(),
                'total'            => $existing['total'] + $g->sum('total_bayar'),
            ];
        }

        // ── Gabung per-kasir ─────────────────────────────────────────────────
        $perKasir = $split->groupBy(fn ($s) => $s->user?->nama ?? 'N/A')
                          ->map(fn ($g) => [
                              'total' => $g->sum('jumlah'),
                              'count' => $g->pluck('billing_id')->unique()->count(),
                          ]);

        foreach ($ritel->groupBy(fn ($r) => $r->kasir?->nama ?? 'N/A') as $nama => $g) {
            $existing = $perKasir->get($nama, ['total' => 0, 'count' => 0]);
            $perKasir[$nama] = [
                'total' => $existing['total'] + $g->sum('total_bayar'),
                'count' => $existing['count'] + $g->count(),
            ];
        }

        // ── Gabung per-hari ──────────────────────────────────────────────────
        $perHari = $split->groupBy(fn ($s) => $s->created_at->format('Y-m-d'))
                         ->map->sum('jumlah');

        foreach ($ritel->groupBy(fn ($r) => $r->dibayar_at->format('Y-m-d')) as $tgl => $g) {
            $perHari[$tgl] = ($perHari[$tgl] ?? 0) + $g->sum('total_bayar');
        }

        $perHari = $perHari->sortKeys();

        return [
            'total_transaksi' => $split->pluck('billing_id')->unique()->count() + $ritel->count(),
            'total_nilai'     => $split->sum('jumlah') + $ritel->sum('total_bayar'),
            'total_ritel'     => $ritel->sum('total_bayar'),
            'jumlah_ritel'    => $ritel->count(),
            'per_metode'      => $perMetode,
            'per_kasir'       => $perKasir,
            'per_hari'        => $perHari,
        ];
    }

    public function cancelBill(Carbon $mulai, Carbon $akhir): array
    {
        $batal = Invoice::where('status', 'dibatalkan')
            ->whereBetween('dibatalkan_pada', [$mulai, $akhir])
            ->with(['kunjungan.pasien', 'dibatalkanOleh', 'cancelVerifiedBy'])
            ->orderBy('dibatalkan_pada')
            ->get();

        return [
            'total_batal'       => $batal->count(),
            'total_nilai_batal' => $batal->sum('total_tagihan'),
            'per_petugas'       => $batal->groupBy(fn ($b) => $b->dibatalkanOleh?->nama ?? 'N/A')->map->count(),
            'detail'            => $batal->map(fn ($b) => [
                'nomor_invoice'    => $b->nomor_invoice,
                'tanggal_transaksi'=> $b->created_at->format('d/m/Y H:i'),
                'tanggal_batal'    => $b->dibatalkan_pada?->format('d/m/Y H:i'),
                'pasien'           => $b->kunjungan->pasien->nama,
                'nomor_rm'         => $b->kunjungan->pasien->nomor_rm,
                'nilai'            => $b->total_tagihan,
                'alasan'           => $b->cancel_reason,
                'dibatalkan_oleh'  => $b->dibatalkanOleh?->nama ?? '-',
                'diverifikasi_oleh'=> $b->cancelVerifiedBy?->nama ?? '-',
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

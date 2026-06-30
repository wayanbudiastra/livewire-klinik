<?php

namespace App\Services\Demo;

use App\Models\Akuntansi\JurnalUmum;
use App\Models\Barang;
use App\Models\GoodsReceipt;
use App\Models\TransaksiRitel;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DemoJurnalGenerator
{
    /**
     * Generate jurnal untuk daftar GR yang baru dibuat.
     * DR 1-1300 Persediaan | KR 2-1100 Hutang Dagang
     *
     * @param  int[]  $grIds
     * @param  int    $userId
     * @return int    Jumlah entri jurnal dibuat
     */
    public function generateForGrn(array $grIds, int $userId): int
    {
        if (empty($grIds)) return 0;

        $now     = now();
        $seqMap  = $this->loadSeqMap($grIds, $userId, 'gr');
        $rows    = [];

        GoodsReceipt::whereIn('id', $grIds)
            ->where('status', 'diverifikasi')
            ->orderBy('tanggal_terima')
            ->each(function (GoodsReceipt $gr) use (&$rows, &$seqMap, $userId, $now) {
                $bulan  = Carbon::parse($gr->tanggal_terima)->format('Ym');
                $posted = Carbon::parse($gr->tanggal_terima)->setTime(9, 0, 0);

                $rows[] = [
                    'nomor_jurnal'     => $this->nextSeq($bulan, $seqMap),
                    'tanggal'          => $gr->tanggal_terima,
                    'kode_akun_debit'  => '1-1300',
                    'kode_akun_kredit' => '2-1100',
                    'nominal'          => $gr->total_nilai,
                    'keterangan'       => 'Penerimaan barang ' . $gr->nomor_gr
                                        . ' | Faktur: ' . $gr->nomor_faktur_supplier,
                    'sumber_tipe'      => 'goods_receipt',
                    'sumber_id'        => $gr->id,
                    'diposting_oleh'   => $userId,
                    'diposting_pada'   => $posted,
                    'created_at'       => $now,
                    'updated_at'       => $now,
                ];
            });

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('jurnal_umum')->insert($chunk);
        }

        return count($rows);
    }

    /**
     * Generate jurnal untuk daftar transaksi ritel yang baru dibuat.
     * Entry A: DR Kas/Bank | KR Pendapatan Penjualan Obat
     * Entry B: DR HPP Farmasi | KR Persediaan Barang
     *
     * @param  int[]  $ritelIds
     * @param  int    $userId
     * @return int    Jumlah entri jurnal dibuat
     */
    public function generateForRitel(array $ritelIds, int $userId): int
    {
        if (empty($ritelIds)) return 0;

        $now    = now();
        $barang = Barang::where('is_active', 1)->pluck('harga_pokok', 'id');
        $seqMap = $this->loadSeqMap($ritelIds, $userId, 'ritel');
        $rows   = [];

        TransaksiRitel::whereIn('id', $ritelIds)
            ->where('status', 'selesai')
            ->with('items')
            ->orderBy('dibayar_at')
            ->each(function (TransaksiRitel $tr) use (&$rows, &$seqMap, $userId, $barang, $now) {
                $bulan  = Carbon::parse($tr->dibayar_at)->format('Ym');
                $posted = $tr->dibayar_at;

                $akunKas = in_array($tr->metode_bayar, ['transfer', 'kartu'])
                    ? '1-1200'
                    : '1-1100';

                // Entry A — Penjualan
                $rows[] = [
                    'nomor_jurnal'     => $this->nextSeq($bulan, $seqMap),
                    'tanggal'          => Carbon::parse($tr->dibayar_at)->toDateString(),
                    'kode_akun_debit'  => $akunKas,
                    'kode_akun_kredit' => '4-1300',
                    'nominal'          => $tr->total_harga,
                    'keterangan'       => 'Penjualan ritel ' . $tr->nomor_ritel
                                        . ' (' . $tr->metode_bayar . ')',
                    'sumber_tipe'      => 'transaksi_ritel',
                    'sumber_id'        => $tr->id,
                    'diposting_oleh'   => $userId,
                    'diposting_pada'   => $posted,
                    'created_at'       => $now,
                    'updated_at'       => $now,
                ];

                // Entry B — HPP
                $totalHpp = $tr->items->sum(
                    fn ($item) => $item->jumlah * (float) ($barang[$item->barang_id] ?? 0)
                );

                if ($totalHpp > 0) {
                    $rows[] = [
                        'nomor_jurnal'     => $this->nextSeq($bulan, $seqMap),
                        'tanggal'          => Carbon::parse($tr->dibayar_at)->toDateString(),
                        'kode_akun_debit'  => '5-1100',
                        'kode_akun_kredit' => '1-1300',
                        'nominal'          => round($totalHpp, 2),
                        'keterangan'       => 'HPP penjualan ritel ' . $tr->nomor_ritel,
                        'sumber_tipe'      => 'transaksi_ritel',
                        'sumber_id'        => $tr->id,
                        'diposting_oleh'   => $userId,
                        'diposting_pada'   => $posted,
                        'created_at'       => $now,
                        'updated_at'       => $now,
                    ];
                }
            });

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('jurnal_umum')->insert($chunk);
        }

        return count($rows);
    }

    private function nextSeq(string $bulan, array &$seqMap): string
    {
        $seqMap[$bulan] = ($seqMap[$bulan] ?? 0) + 1;
        return 'JU-' . $bulan . '-' . str_pad($seqMap[$bulan], 4, '0', STR_PAD_LEFT);
    }

    /**
     * Muat sequence terakhir nomor jurnal per bulan yang relevan dengan data yang akan di-generate.
     */
    private function loadSeqMap(array $ids, int $userId, string $tipe): array
    {
        // Temukan bulan-bulan yang akan terdampak
        if ($tipe === 'gr') {
            $tanggals = GoodsReceipt::whereIn('id', $ids)->pluck('tanggal_terima');
        } else {
            $tanggals = TransaksiRitel::whereIn('id', $ids)->pluck('dibayar_at');
        }

        $months = $tanggals->map(fn ($t) => Carbon::parse($t)->format('Ym'))->unique();
        $seqMap = [];

        foreach ($months as $bulan) {
            $prefix = 'JU-' . $bulan . '-';
            $last   = DB::table('jurnal_umum')
                        ->where('nomor_jurnal', 'like', $prefix . '%')
                        ->orderByDesc('nomor_jurnal')
                        ->value('nomor_jurnal');
            $seqMap[$bulan] = $last ? (int) substr($last, -4) : 0;
        }

        return $seqMap;
    }
}

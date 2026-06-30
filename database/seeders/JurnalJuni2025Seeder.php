<?php

namespace Database\Seeders;

use App\Models\Akuntansi\JurnalUmum;
use App\Models\Barang;
use App\Models\GoodsReceipt;
use App\Models\TransaksiRitel;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class JurnalJuni2025Seeder extends Seeder
{
    public function run(): void
    {
        // Hapus jurnal sebelumnya untuk transaksi yang sama (idempoten)
        DB::table('jurnal_umum')
            ->whereIn('sumber_tipe', ['transaksi_ritel', 'goods_receipt'])
            ->whereBetween('tanggal', ['2025-06-01', '2025-07-31'])
            ->delete();

        $adminId  = User::where('nama', 'like', '%Admin%')->value('id') ?? 2;
        $barang   = Barang::where('is_active', 1)->pluck('harga_pokok', 'id');
        $now      = now();

        // Counter nomor jurnal per bulan
        $seqPerBulan = [];
        $rows        = [];

        $nextSeq = function (string $bulan) use (&$seqPerBulan): string {
            $seqPerBulan[$bulan] = ($seqPerBulan[$bulan] ?? 0) + 1;
            return 'JU-' . $bulan . '-' . str_pad($seqPerBulan[$bulan], 4, '0', STR_PAD_LEFT);
        };

        // ────────────────────────────────────────────
        // 1. JURNAL GOODS RECEIPT (PO → GRN)
        //    DR 1-1300 Persediaan | KR 2-1100 Hutang Dagang
        // ────────────────────────────────────────────
        GoodsReceipt::where('nomor_gr', 'like', 'GR-2025-06-%')
            ->where('status', 'diverifikasi')
            ->orderBy('tanggal_terima')
            ->each(function (GoodsReceipt $gr) use (&$rows, $nextSeq, $adminId, $now) {
                $bulan  = Carbon::parse($gr->tanggal_terima)->format('Ym');
                $posted = Carbon::parse($gr->tanggal_terima)->setTime(9, 0, 0);

                $rows[] = [
                    'nomor_jurnal'    => $nextSeq($bulan),
                    'tanggal'         => $gr->tanggal_terima,
                    'kode_akun_debit' => '1-1300', // Persediaan Barang
                    'kode_akun_kredit'=> '2-1100', // Hutang Dagang Supplier
                    'nominal'         => $gr->total_nilai,
                    'keterangan'      => 'Penerimaan barang ' . $gr->nomor_gr
                                       . ' | Faktur: ' . $gr->nomor_faktur_supplier,
                    'sumber_tipe'     => 'goods_receipt',
                    'sumber_id'       => $gr->id,
                    'diposting_oleh'  => $adminId,
                    'diposting_pada'  => $posted,
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ];
            });

        // ────────────────────────────────────────────
        // 2. JURNAL TRANSAKSI RITEL
        //    Entry A: DR Kas/Bank | KR Pendapatan Penjualan Obat
        //    Entry B: DR HPP Farmasi | KR Persediaan Barang
        // ────────────────────────────────────────────
        TransaksiRitel::where('nomor_ritel', 'like', 'RIT-2025%')
            ->where('status', 'selesai')
            ->with('items')
            ->orderBy('dibayar_at')
            ->each(function (TransaksiRitel $tr) use (&$rows, $nextSeq, $adminId, $barang, $now) {
                $bulan  = Carbon::parse($tr->dibayar_at)->format('Ym');
                $posted = $tr->dibayar_at;

                // Akun kas/bank sesuai metode bayar
                $akunKas = in_array($tr->metode_bayar, ['transfer', 'kartu'])
                    ? '1-1200'  // Bank
                    : '1-1100'; // Kas

                // Entry A – Penjualan
                $rows[] = [
                    'nomor_jurnal'    => $nextSeq($bulan),
                    'tanggal'         => Carbon::parse($tr->dibayar_at)->toDateString(),
                    'kode_akun_debit' => $akunKas,
                    'kode_akun_kredit'=> '4-1300', // Pendapatan Penjualan Obat
                    'nominal'         => $tr->total_harga,
                    'keterangan'      => 'Penjualan ritel ' . $tr->nomor_ritel
                                       . ' (' . $tr->metode_bayar . ')',
                    'sumber_tipe'     => 'transaksi_ritel',
                    'sumber_id'       => $tr->id,
                    'diposting_oleh'  => $adminId,
                    'diposting_pada'  => $posted,
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ];

                // Entry B – HPP
                $totalHpp = $tr->items->sum(
                    fn ($item) => $item->jumlah * (float) ($barang[$item->barang_id] ?? 0)
                );

                if ($totalHpp > 0) {
                    $rows[] = [
                        'nomor_jurnal'    => $nextSeq($bulan),
                        'tanggal'         => Carbon::parse($tr->dibayar_at)->toDateString(),
                        'kode_akun_debit' => '5-1100', // HPP Farmasi
                        'kode_akun_kredit'=> '1-1300', // Persediaan Barang
                        'nominal'         => round($totalHpp, 2),
                        'keterangan'      => 'HPP penjualan ritel ' . $tr->nomor_ritel,
                        'sumber_tipe'     => 'transaksi_ritel',
                        'sumber_id'       => $tr->id,
                        'diposting_oleh'  => $adminId,
                        'diposting_pada'  => $posted,
                        'created_at'      => $now,
                        'updated_at'      => $now,
                    ];
                }
            });

        // Bulk insert per 500 baris
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('jurnal_umum')->insert($chunk);
        }

        // Ringkasan
        $totalJurnal = count($rows);
        $totalGrn    = collect($rows)->where('sumber_tipe', 'goods_receipt')->count();
        $totalRitel  = collect($rows)->where('sumber_tipe', 'transaksi_ritel')->count();
        $sumPersediaan = collect($rows)->where('kode_akun_debit', '1-1300')->sum('nominal');
        $sumPendapatan = collect($rows)->where('kode_akun_kredit', '4-1300')->sum('nominal');
        $sumHpp        = collect($rows)->where('kode_akun_debit', '5-1100')->sum('nominal');

        $this->command->info("✓ {$totalJurnal} entri jurnal berhasil dibuat:");
        $this->command->info("  - GRN  ({$totalGrn}): Persediaan DR / Hutang Dagang KR = Rp " . number_format($sumPersediaan - $sumHpp, 0, ',', '.'));
        $this->command->info("  - Ritel penjualan: Pendapatan = Rp " . number_format($sumPendapatan, 0, ',', '.'));
        $this->command->info("  - Ritel HPP      : HPP Farmasi = Rp " . number_format($sumHpp, 0, ',', '.'));
        $this->command->info("  - Total ritel entries: {$totalRitel}");
    }
}

<?php

namespace App\Services\Demo;

use App\Models\Barang;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DemoDataResetService
{
    /**
     * Hapus semua data demo (PO+GRN, Ritel, Jurnal, Mutasi) dalam rentang tanggal.
     * Recalculate barang.stok setelah penghapusan.
     *
     * Urutan penghapusan aman terhadap foreign key:
     *   jurnal_umum → mutasi_stok → gr_item → goods_receipt
     *                             → po_item  → purchase_order
     *                             → transaksi_ritel_item → transaksi_ritel
     *
     * @return array ['deleted_jurnal'=>int, 'deleted_gr'=>int, 'deleted_po'=>int,
     *                'deleted_ritel'=>int, 'barang_updated'=>int]
     */
    public function hapus(Carbon $dari, Carbon $sampai): array
    {
        $dariStr   = $dari->toDateString();
        $sampaiStr = $sampai->toDateString();

        $result = [
            'deleted_jurnal' => 0,
            'deleted_gr'     => 0,
            'deleted_po'     => 0,
            'deleted_ritel'  => 0,
            'barang_updated' => 0,
        ];

        DB::transaction(function () use ($dariStr, $sampaiStr, &$result) {

            // 1. Hapus jurnal
            $result['deleted_jurnal'] = DB::table('jurnal_umum')
                ->whereIn('sumber_tipe', ['goods_receipt', 'transaksi_ritel'])
                ->whereBetween('tanggal', [$dariStr, $sampaiStr])
                ->delete();

            // 2. Hapus mutasi stok masuk (GRN)
            DB::table('mutasi_stok')
                ->where('tipe', 'masuk_pembelian')
                ->whereDate('created_at', '>=', $dariStr)
                ->whereDate('created_at', '<=', $sampaiStr)
                ->delete();

            // 3. Hapus mutasi stok keluar (Ritel)
            DB::table('mutasi_stok')
                ->where('tipe', 'keluar_ritel')
                ->whereDate('created_at', '>=', $dariStr)
                ->whereDate('created_at', '<=', $sampaiStr)
                ->delete();

            // 4. Hapus GR Items & GRN dalam rentang
            $grIds = DB::table('goods_receipt')
                ->whereBetween('tanggal_terima', [$dariStr, $sampaiStr])
                ->pluck('id');

            if ($grIds->isNotEmpty()) {
                DB::table('gr_item')->whereIn('goods_receipt_id', $grIds)->delete();
                $result['deleted_gr'] = DB::table('goods_receipt')
                    ->whereIn('id', $grIds)->delete();
            }

            // 5. Hapus PO Items & PO dalam rentang
            $poIds = DB::table('purchase_order')
                ->whereBetween('tanggal_po', [$dariStr, $sampaiStr])
                ->pluck('id');

            if ($poIds->isNotEmpty()) {
                DB::table('po_item')->whereIn('purchase_order_id', $poIds)->delete();
                $result['deleted_po'] = DB::table('purchase_order')
                    ->whereIn('id', $poIds)->delete();
            }

            // 6. Hapus Transaksi Ritel Items & Transaksi dalam rentang
            $ritelIds = DB::table('transaksi_ritel')
                ->whereDate('dibayar_at', '>=', $dariStr)
                ->whereDate('dibayar_at', '<=', $sampaiStr)
                ->pluck('id');

            if ($ritelIds->isNotEmpty()) {
                DB::table('transaksi_ritel_item')
                    ->whereIn('transaksi_ritel_id', $ritelIds)->delete();
                $result['deleted_ritel'] = DB::table('transaksi_ritel')
                    ->whereIn('id', $ritelIds)->delete();
            }

            // 7. Recalculate stok dari mutasi yang tersisa
            $result['barang_updated'] = $this->recalculateStok();
        });

        return $result;
    }

    /**
     * Hitung ulang barang.stok dari semua mutasi yang masih ada di DB.
     * Akumulasi masuk: masuk_pembelian, penyesuaian_masuk
     * Akumulasi keluar: keluar_*, penyesuaian_keluar, retur_ke_supplier, expired
     */
    private function recalculateStok(): int
    {
        $tipoMasuk  = ['masuk_pembelian', 'penyesuaian_masuk'];
        $tipoKeluar = ['keluar_resep', 'keluar_tindakan', 'keluar_bhp', 'keluar_ritel',
                       'penyesuaian_keluar', 'retur_ke_supplier', 'expired'];

        $masuk = DB::table('mutasi_stok')
            ->whereIn('tipe', $tipoMasuk)
            ->groupBy('barang_id')
            ->select('barang_id', DB::raw('SUM(jumlah) as total'))
            ->pluck('total', 'barang_id');

        $keluar = DB::table('mutasi_stok')
            ->whereIn('tipe', $tipoKeluar)
            ->groupBy('barang_id')
            ->select('barang_id', DB::raw('SUM(jumlah) as total'))
            ->pluck('total', 'barang_id');

        $barangIds = Barang::where('is_active', 1)->pluck('id');
        $updated   = 0;

        foreach ($barangIds as $id) {
            $stokBaru = ((float) ($masuk[$id] ?? 0)) - ((float) ($keluar[$id] ?? 0));
            $stokBaru = max(0, $stokBaru);

            Barang::where('id', $id)->update(['stok' => $stokBaru]);
            $updated++;
        }

        return $updated;
    }

    /**
     * Periksa apakah ada data yang sudah ada di rentang tanggal.
     *
     * @return array ['po'=>int, 'gr'=>int, 'ritel'=>int, 'ada_konflik'=>bool]
     */
    public function cekKonflik(Carbon $dari, Carbon $sampai): array
    {
        $dariStr   = $dari->toDateString();
        $sampaiStr = $sampai->toDateString();

        $countPo = DB::table('purchase_order')
            ->whereBetween('tanggal_po', [$dariStr, $sampaiStr])
            ->count();

        $countGr = DB::table('goods_receipt')
            ->whereBetween('tanggal_terima', [$dariStr, $sampaiStr])
            ->count();

        $countRitel = DB::table('transaksi_ritel')
            ->whereDate('dibayar_at', '>=', $dariStr)
            ->whereDate('dibayar_at', '<=', $sampaiStr)
            ->count();

        $totalPo    = DB::table('purchase_order')
            ->whereBetween('tanggal_po', [$dariStr, $sampaiStr])
            ->sum('total_nilai');

        $totalRitel = DB::table('transaksi_ritel')
            ->whereDate('dibayar_at', '>=', $dariStr)
            ->whereDate('dibayar_at', '<=', $sampaiStr)
            ->sum('total_harga');

        return [
            'po'          => $countPo,
            'gr'          => $countGr,
            'ritel'       => $countRitel,
            'total_po'    => (float) $totalPo,
            'total_ritel' => (float) $totalRitel,
            'ada_konflik' => ($countPo + $countGr + $countRitel) > 0,
        ];
    }
}

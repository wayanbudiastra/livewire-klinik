<?php

namespace App\Services\Demo;

use App\Models\Barang;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DemoDataResetService
{
    /**
     * Hapus semua data demo (PO+GRN, Ritel, Jurnal, Mutasi, Kunjungan) dalam rentang tanggal.
     * Recalculate barang.stok setelah penghapusan.
     *
     * Urutan penghapusan aman terhadap foreign key:
     *   jurnal_umum (billing+gr+ritel) → pembayaran → invoice_item → billing
     *   → mutasi_stok (keluar_resep) → tindakan → item_resep → resep
     *   → soap_note → asesmen_perawat → kunjungan
     *   → mutasi_stok (masuk+ritel) → gr_item → goods_receipt
     *   → po_item → purchase_order → transaksi_ritel_item → transaksi_ritel
     *
     * @return array ['deleted_jurnal'=>int, 'deleted_kunjungan'=>int,
     *                'deleted_billing'=>int, 'deleted_gr'=>int,
     *                'deleted_po'=>int, 'deleted_ritel'=>int, 'barang_updated'=>int]
     */
    public function hapus(Carbon $dari, Carbon $sampai): array
    {
        $dariStr   = $dari->toDateString();
        $sampaiStr = $sampai->toDateString();

        $result = [
            'deleted_jurnal'    => 0,
            'deleted_kunjungan' => 0,
            'deleted_billing'   => 0,
            'deleted_gr'        => 0,
            'deleted_po'        => 0,
            'deleted_ritel'     => 0,
            'barang_updated'    => 0,
        ];

        DB::transaction(function () use ($dariStr, $sampaiStr, &$result) {

            // -- KUNJUNGAN cleanup --

            // Kunjungan dalam rentang tanggal
            $kunjunganIds = DB::table('kunjungan')
                ->whereDate('tanggal', '>=', $dariStr)
                ->whereDate('tanggal', '<=', $sampaiStr)
                ->pluck('id');

            if ($kunjunganIds->isNotEmpty()) {
                // Billing untuk kunjungan ini
                $billingIds = DB::table('billing')
                    ->whereIn('kunjungan_id', $kunjunganIds)
                    ->pluck('id');

                if ($billingIds->isNotEmpty()) {
                    // 1. Hapus jurnal billing
                    $result['deleted_jurnal'] += DB::table('jurnal_umum')
                        ->where('sumber_tipe', 'billing')
                        ->whereIn('sumber_id', $billingIds)
                        ->delete();

                    // 2. Hapus pembayaran
                    DB::table('pembayaran')
                        ->whereIn('billing_id', $billingIds)->delete();

                    // 3. Hapus invoice_item
                    DB::table('invoice_item')
                        ->whereIn('billing_id', $billingIds)->delete();

                    // 4. Hapus billing
                    $result['deleted_billing'] = DB::table('billing')
                        ->whereIn('id', $billingIds)->delete();
                }

                // 5. Hapus mutasi stok keluar_resep (dari resep kunjungan ini)
                $resepIds = DB::table('resep')
                    ->whereIn('kunjungan_id', $kunjunganIds)
                    ->pluck('id');

                if ($resepIds->isNotEmpty()) {
                    DB::table('mutasi_stok')
                        ->where('tipe', 'keluar_resep')
                        ->where('referensi_tipe', 'resep')
                        ->whereIn('referensi_id', $resepIds)
                        ->delete();

                    // 6. Hapus item_resep
                    DB::table('item_resep')
                        ->whereIn('resep_id', $resepIds)->delete();

                    // 7. Hapus resep
                    DB::table('resep')
                        ->whereIn('id', $resepIds)->delete();
                }

                // 8. Hapus tindakan
                DB::table('tindakan')
                    ->whereIn('kunjungan_id', $kunjunganIds)->delete();

                // 9. Hapus soap_note (CASCADE — tapi dihapus eksplisit agar aman)
                DB::table('soap_note')
                    ->whereIn('kunjungan_id', $kunjunganIds)->delete();

                // 10. Hapus asesmen_perawat (CASCADE)
                DB::table('asesmen_perawat')
                    ->whereIn('kunjungan_id', $kunjunganIds)->delete();

                // 11. Hapus tabel lain ber-FK RESTRICT ke kunjungan
                DB::table('permintaan_penunjang')
                    ->whereIn('kunjungan_id', $kunjunganIds)->delete();
                DB::table('pemakaian_alkes')
                    ->whereIn('kunjungan_id', $kunjunganIds)->delete();
                DB::table('retur_resep')
                    ->whereIn('kunjungan_id', $kunjunganIds)->delete();

                // 12. Hapus kunjungan
                $result['deleted_kunjungan'] = DB::table('kunjungan')
                    ->whereIn('id', $kunjunganIds)->delete();
            }

            // -- PO+GRN+RITEL cleanup --

            // 12. Hapus jurnal GR + Ritel
            $result['deleted_jurnal'] += DB::table('jurnal_umum')
                ->whereIn('sumber_tipe', ['goods_receipt', 'transaksi_ritel'])
                ->whereBetween('tanggal', [$dariStr, $sampaiStr])
                ->delete();

            // 13. Hapus mutasi stok masuk (GRN)
            DB::table('mutasi_stok')
                ->where('tipe', 'masuk_pembelian')
                ->whereDate('created_at', '>=', $dariStr)
                ->whereDate('created_at', '<=', $sampaiStr)
                ->delete();

            // 14. Hapus mutasi stok keluar (Ritel)
            DB::table('mutasi_stok')
                ->where('tipe', 'keluar_ritel')
                ->whereDate('created_at', '>=', $dariStr)
                ->whereDate('created_at', '<=', $sampaiStr)
                ->delete();

            // 15. Hapus GR Items & GRN dalam rentang
            $grIds = DB::table('goods_receipt')
                ->whereBetween('tanggal_terima', [$dariStr, $sampaiStr])
                ->pluck('id');

            if ($grIds->isNotEmpty()) {
                DB::table('gr_item')->whereIn('goods_receipt_id', $grIds)->delete();
                $result['deleted_gr'] = DB::table('goods_receipt')
                    ->whereIn('id', $grIds)->delete();
            }

            // 16. Hapus PO Items & PO dalam rentang
            $poIds = DB::table('purchase_order')
                ->whereBetween('tanggal_po', [$dariStr, $sampaiStr])
                ->pluck('id');

            if ($poIds->isNotEmpty()) {
                DB::table('po_item')->whereIn('purchase_order_id', $poIds)->delete();
                $result['deleted_po'] = DB::table('purchase_order')
                    ->whereIn('id', $poIds)->delete();
            }

            // 17. Hapus Transaksi Ritel Items & Transaksi dalam rentang
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

            // 18. Recalculate stok dari mutasi yang tersisa
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
     * @return array ['po'=>int, 'gr'=>int, 'ritel'=>int, 'kunjungan'=>int, 'ada_konflik'=>bool]
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

        $countKunjungan = DB::table('kunjungan')
            ->whereDate('tanggal', '>=', $dariStr)
            ->whereDate('tanggal', '<=', $sampaiStr)
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
            'kunjungan'   => $countKunjungan,
            'total_po'    => (float) $totalPo,
            'total_ritel' => (float) $totalRitel,
            'ada_konflik' => ($countPo + $countGr + $countRitel + $countKunjungan) > 0,
        ];
    }
}

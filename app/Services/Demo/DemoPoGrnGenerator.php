<?php

namespace App\Services\Demo;

use App\Models\Barang;
use App\Models\GoodsReceipt;
use App\Models\GrItem;
use App\Models\MutasiStok;
use App\Models\PoItem;
use App\Models\PurchaseOrder;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DemoPoGrnGenerator
{
    // Supplier per kelompok (supplier_id)
    private array $supplierPerKelompok = [0 => 1, 1 => 2, 2 => 4, 3 => 3];

    // 4 kelompok barang — base qty dikalibrasi ke ~5 juta/PO (@10jt/hari base)
    private function kelompokBarang(): array
    {
        return [
            // Kelompok 0 — Obat-obatan rutin
            [
                ['barang_id' => 1,  'qty' => 840],
                ['barang_id' => 2,  'qty' => 420],
                ['barang_id' => 3,  'qty' => 420],
                ['barang_id' => 4,  'qty' => 500],
                ['barang_id' => 5,  'qty' => 250],
                ['barang_id' => 6,  'qty' => 250],
                ['barang_id' => 7,  'qty' => 330],
                ['barang_id' => 8,  'qty' => 170],
            ],
            // Kelompok 1 — Alkes habis pakai / consumable
            [
                ['barang_id' => 9,  'qty' => 370],
                ['barang_id' => 10, 'qty' => 740],
                ['barang_id' => 11, 'qty' => 740],
                ['barang_id' => 12, 'qty' => 370],
                ['barang_id' => 15, 'qty' => 1800],
                ['barang_id' => 24, 'qty' => 370],
                ['barang_id' => 26, 'qty' => 90],
                ['barang_id' => 37, 'qty' => 20],
            ],
            // Kelompok 2 — Alkes premium & diagnostik
            [
                ['barang_id' => 36, 'qty' => 3],
                ['barang_id' => 46, 'qty' => 20],
                ['barang_id' => 47, 'qty' => 70],
                ['barang_id' => 48, 'qty' => 20],
                ['barang_id' => 52, 'qty' => 70],
                ['barang_id' => 53, 'qty' => 70],
                ['barang_id' => 54, 'qty' => 40],
                ['barang_id' => 55, 'qty' => 40],
            ],
            // Kelompok 3 — Cairan infus & IV supplies
            [
                ['barang_id' => 22, 'qty' => 110],
                ['barang_id' => 23, 'qty' => 110],
                ['barang_id' => 20, 'qty' => 110],
                ['barang_id' => 21, 'qty' => 60],
                ['barang_id' => 17, 'qty' => 110],
                ['barang_id' => 18, 'qty' => 110],
                ['barang_id' => 50, 'qty' => 60],
                ['barang_id' => 33, 'qty' => 110],
            ],
        ];
    }

    /**
     * Generate PO+GRN untuk rentang tanggal & target tertentu.
     *
     * @param  \Carbon\Carbon  $dari
     * @param  \Carbon\Carbon  $sampai
     * @param  int             $targetHarianRp  Target nilai pembelian per hari (Rp)
     * @param  int             $userId          ID user yang membuat (dibuat_oleh / diterima_oleh)
     * @param  array           &$stokBerjalan   Stok tracking bersama antar generator
     * @param  callable|null   $onProgress      Callback per hari selesai
     * @return array           ['po_ids'=>[], 'gr_ids'=>[], 'total_nilai'=>0.0, 'per_hari'=>[]]
     */
    public function generate(
        Carbon $dari,
        Carbon $sampai,
        int $targetHarianRp,
        int $userId,
        array &$stokBerjalan,
        ?callable $onProgress = null
    ): array {
        $barang         = Barang::where('is_active', 1)->get()->keyBy('id');
        $kelompok       = $this->kelompokBarang();
        $faktorSkala    = $targetHarianRp / 10_000_000; // base 10jt/hari

        // Sequence tracker per bulan untuk nomor PO & GR
        $seqPo = [];
        $seqGr = [];
        $this->initSeq($dari, $sampai, $seqPo, $seqGr);

        $adminId = User::where('nama', 'like', '%Admin%')->value('id') ?? 2;

        $poIds      = [];
        $grIds      = [];
        $totalNilai = 0.0;
        $perHari    = [];

        $current = $dari->copy()->startOfDay();
        $end     = $sampai->copy()->endOfDay();
        $dayIdx  = 0;

        while ($current->lte($end)) {
            $tglPo   = $current->copy()->setTime(8, 0, 0);
            $nilaiHari = 0.0;
            $poHari  = 0;
            $grHari  = 0;

            // 2 PO per hari, 2 kelompok berbeda
            for ($poIdx = 0; $poIdx < 2; $poIdx++) {
                $kelIdx     = ($dayIdx * 2 + $poIdx) % 4;
                $supplierId = $this->supplierPerKelompok[$kelIdx];
                $itemList   = $kelompok[$kelIdx];

                // Kalibrasi qty ke target dengan variasi ±20%
                $itemsSeed = array_map(function ($item) use ($faktorSkala) {
                    $faktor = $faktorSkala * ((80 + mt_rand(0, 40)) / 100);
                    return [
                        'barang_id' => $item['barang_id'],
                        'qty'       => max(1, (int) round($item['qty'] * $faktor)),
                    ];
                }, $itemList);

                // Hitung harga per item
                $poItems    = [];
                $totalNilaiPo = 0.0;

                foreach ($itemsSeed as $item) {
                    $bid = $item['barang_id'];
                    if (!isset($barang[$bid])) continue;

                    $hargaPokok  = (float) $barang[$bid]->harga_pokok;
                    $markup      = 1 + (mt_rand(2, 6) / 100);
                    $hargaSatuan = round($hargaPokok * $markup, 2);
                    $diskon      = [0, 0, 0, 0.5, 1.0, 1.5, 2.0][array_rand([0, 0, 0, 0.5, 1.0, 1.5, 2.0])];
                    $subtotal    = round($item['qty'] * $hargaSatuan * (1 - $diskon / 100), 2);

                    $poItems[] = [
                        'barang_id'     => $bid,
                        'jumlah_pesan'  => $item['qty'],
                        'harga_satuan'  => $hargaSatuan,
                        'diskon_persen' => $diskon,
                        'subtotal'      => $subtotal,
                    ];
                    $totalNilaiPo += $subtotal;
                }

                if (empty($poItems)) continue;

                $nomorPo = $this->nextNomor('PO', $tglPo, $seqPo);
                $tglDisetujui     = (clone $tglPo)->addDay();
                $tglKirimEstimasi = (clone $tglPo)->addDays(3);

                $po = PurchaseOrder::create([
                    'nomor_po'               => $nomorPo,
                    'supplier_id'            => $supplierId,
                    'dibuat_oleh'            => $userId,
                    'disetujui_oleh'         => $adminId,
                    'tanggal_po'             => $tglPo,
                    'tanggal_kirim_estimasi' => $tglKirimEstimasi,
                    'tanggal_disetujui'      => $tglDisetujui,
                    'status'                 => 'selesai',
                    'total_nilai'            => $totalNilaiPo,
                    'catatan'                => null,
                ]);

                $poItemMap = [];
                foreach ($poItems as $item) {
                    $poItem = PoItem::create([
                        'purchase_order_id' => $po->id,
                        'barang_id'         => $item['barang_id'],
                        'jumlah_pesan'      => $item['jumlah_pesan'],
                        'harga_satuan'      => $item['harga_satuan'],
                        'diskon_persen'     => $item['diskon_persen'],
                        'subtotal'          => $item['subtotal'],
                        'jumlah_diterima'   => $item['jumlah_pesan'],
                    ]);
                    $poItemMap[$item['barang_id']] = $poItem->id;
                }

                // GRN: 1–3 hari setelah PO
                $tglTerima   = (clone $tglPo)->addDays(mt_rand(1, 3));
                $nomorGr     = $this->nextNomor('GR', $tglPo, $seqGr);
                $nomorFaktur = 'INV/' . str_pad($supplierId, 2, '0', STR_PAD_LEFT)
                             . '/' . $tglTerima->format('Ym')
                             . '/' . str_pad(($seqGr[$tglPo->format('Y-m')] ?? 1), 4, '0', STR_PAD_LEFT);
                $nomorSJ = 'SJ/' . str_pad($supplierId, 2, '0', STR_PAD_LEFT)
                         . '/' . $tglTerima->format('Ymd')
                         . '/' . mt_rand(100, 999);

                $gr = GoodsReceipt::create([
                    'nomor_gr'              => $nomorGr,
                    'purchase_order_id'     => $po->id,
                    'supplier_id'           => $supplierId,
                    'diterima_oleh'         => $userId,
                    'tanggal_terima'        => $tglTerima,
                    'nomor_faktur_supplier' => $nomorFaktur,
                    'tanggal_faktur'        => $tglTerima,
                    'tanggal_jatuh_tempo'   => (clone $tglTerima)->addDays(30),
                    'nomor_surat_jalan'     => $nomorSJ,
                    'total_nilai'           => $totalNilaiPo,
                    'status'                => 'diverifikasi',
                    'catatan'               => null,
                ]);

                // GR Items + Mutasi Stok
                foreach ($poItems as $item) {
                    $bid      = $item['barang_id'];
                    $hprSblm  = (float) $barang[$bid]->harga_pokok;
                    $hprStlh  = round($item['harga_satuan'] * (1 - $item['diskon_persen'] / 100), 2);
                    $stokSblm = $stokBerjalan[$bid] ?? 0;
                    $stokStlh = $stokSblm + $item['jumlah_pesan'];

                    $nomorBatch = 'BT' . $tglTerima->format('Ym') . mt_rand(1000, 9999);
                    $expiredDt  = Carbon::create(
                        now()->year + mt_rand(0, 1),
                        mt_rand(1, 12),
                        mt_rand(1, 28)
                    )->format('Y-m-d');

                    GrItem::create([
                        'goods_receipt_id' => $gr->id,
                        'barang_id'        => $bid,
                        'po_item_id'       => $poItemMap[$bid] ?? null,
                        'jumlah_terima'    => $item['jumlah_pesan'],
                        'harga_satuan'     => $item['harga_satuan'],
                        'diskon_persen'    => $item['diskon_persen'],
                        'subtotal'         => $item['subtotal'],
                        'nomor_batch'      => $nomorBatch,
                        'expired_date'     => $expiredDt,
                        'hpr_sebelum'      => $hprSblm,
                        'hpr_sesudah'      => $hprStlh,
                    ]);

                    MutasiStok::create([
                        'barang_id'      => $bid,
                        'user_id'        => $userId,
                        'tipe'           => 'masuk_pembelian',
                        'jumlah'         => $item['jumlah_pesan'],
                        'stok_sebelum'   => $stokSblm,
                        'stok_sesudah'   => $stokStlh,
                        'hpr_sebelum'    => $hprSblm,
                        'hpr_sesudah'    => $hprStlh,
                        'referensi_tipe' => 'goods_receipt',
                        'referensi_id'   => $gr->id,
                        'keterangan'     => 'Penerimaan barang ' . $nomorGr . ' dari ' . $nomorFaktur,
                    ]);

                    $stokBerjalan[$bid] = $stokStlh;
                }

                $poIds[]      = $po->id;
                $grIds[]      = $gr->id;
                $totalNilai  += $totalNilaiPo;
                $nilaiHari   += $totalNilaiPo;
                $poHari++;
                $grHari++;
            }

            $perHari[] = [
                'tanggal'    => $current->toDateString(),
                'po'         => $poHari,
                'gr'         => $grHari,
                'nilai'      => $nilaiHari,
            ];

            if ($onProgress) {
                ($onProgress)([
                    'tipe'    => 'po_grn',
                    'tanggal' => $current->toDateString(),
                    'po'      => $poHari,
                    'gr'      => $grHari,
                    'nilai'   => $nilaiHari,
                ]);
            }

            $current->addDay();
            $dayIdx++;
        }

        return [
            'po_ids'      => $poIds,
            'gr_ids'      => $grIds,
            'total_nilai' => $totalNilai,
            'per_hari'    => $perHari,
        ];
    }

    private function nextNomor(string $tipe, Carbon $tgl, array &$seq): string
    {
        $bulan = $tgl->format('Y-m');
        if (!isset($seq[$bulan])) {
            $seq[$bulan] = 0;
        }
        $seq[$bulan]++;
        $prefix = $tipe . '-' . $bulan . '-';
        return $prefix . str_pad($seq[$bulan], 4, '0', STR_PAD_LEFT);
    }

    private function initSeq(Carbon $dari, Carbon $sampai, array &$seqPo, array &$seqGr): void
    {
        // Temukan sequence terakhir yang sudah ada di DB untuk setiap bulan dalam rentang
        $months = [];
        $cur = $dari->copy()->startOfMonth();
        while ($cur->lte($sampai)) {
            $months[] = $cur->format('Y-m');
            $cur->addMonth();
        }

        foreach ($months as $bulan) {
            $prefixPo = 'PO-' . $bulan . '-';
            $lastPo   = PurchaseOrder::where('nomor_po', 'like', $prefixPo . '%')
                            ->orderByDesc('nomor_po')->value('nomor_po');
            $seqPo[$bulan] = $lastPo ? (int) substr($lastPo, -4) : 0;

            $prefixGr = 'GR-' . $bulan . '-';
            $lastGr   = GoodsReceipt::where('nomor_gr', 'like', $prefixGr . '%')
                            ->orderByDesc('nomor_gr')->value('nomor_gr');
            $seqGr[$bulan] = $lastGr ? (int) substr($lastGr, -4) : 0;
        }
    }
}

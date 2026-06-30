<?php

namespace Database\Seeders;

use App\Models\Barang;
use App\Models\GoodsReceipt;
use App\Models\GrItem;
use App\Models\MutasiStok;
use App\Models\PoItem;
use App\Models\PurchaseOrder;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TransaksiPoGrnJuni2025Seeder extends Seeder
{
    private array $stokBerjalan = [];

    // Harga pokok asli dari master data (sebelum pembelian pertama)
    private array $hargaPokokAsli = [
        1  => 857.14,  2  => 2000.00, 3  => 1500.00,  4  => 600.00,
        5  => 2500.00, 6  => 3000.00, 7  => 1200.00,  8  => 3351.72,
        9  => 700.00,  10 => 800.00,  11 => 900.00,   12 => 1200.00,
        15 => 500.00,  17 => 4500.00, 18 => 4500.00,  20 => 6000.00,
        21 => 7000.00, 22 => 8000.00, 23 => 8000.00,  24 => 1046.15,
        26 => 12000.0, 33 => 6000.00, 36 => 45000.00, 37 => 25000.00,
        46 => 80000.0, 47 => 25000.0, 48 => 35000.00, 50 => 8000.00,
        52 => 2500.00, 53 => 2500.00, 54 => 15000.00, 55 => 15000.00,
    ];

    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('gr_item')->truncate();
        DB::table('goods_receipt')->truncate();
        DB::table('po_item')->truncate();
        DB::table('purchase_order')->truncate();
        DB::table('mutasi_stok')->where('tipe', 'masuk_pembelian')->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        // Reset harga_pokok ke nilai asli sebelum dihitung ulang
        foreach ($this->hargaPokokAsli as $id => $pokok) {
            Barang::where('id', $id)->update(['harga_pokok' => $pokok]);
        }

        $barang     = Barang::where('is_active', 1)->get()->keyBy('id');
        $adminId    = User::where('nama', 'like', '%Admin%')->value('id') ?? 2;
        $apotekerId = User::where('nama', 'like', '%Apoteker%')->value('id') ?? 5;

        // Inisialisasi stok berjalan dari DB (stok saat ini)
        foreach ($barang as $id => $b) {
            $this->stokBerjalan[$id] = 0; // fresh — stok akan dibangun dari mutasi
        }

        // Reset stok barang ke 0 di DB (akan diisi ulang dari GRN)
        Barang::where('is_active', 1)->update(['stok' => 0]);

        $poSeq = 1;
        $grSeq = 1;

        // 4 kelompok pembelian bergilir setiap hari
        // Target: ~10 juta/hari = 2 PO/hari @ ~5 juta/PO
        $kelompok = $this->kelompokBarang();
        $supplierPerKelompok = [0 => 1, 1 => 2, 2 => 4, 3 => 3]; // supplier_id

        for ($day = 1; $day <= 30; $day++) {
            $tglPo = Carbon::create(2025, 6, $day, 8, 0, 0);

            // 2 PO per hari, kelompok berbeda
            for ($poIdx = 0; $poIdx < 2; $poIdx++) {
                $kelIdx     = (($day - 1) * 2 + $poIdx) % 4;
                $supplierId = $supplierPerKelompok[$kelIdx];
                $itemList   = $kelompok[$kelIdx];

                // Variasi kuantitas ±20%
                $itemsSeed = array_map(function ($item) {
                    $faktor = (80 + mt_rand(0, 40)) / 100; // 0.80–1.20
                    return [
                        'barang_id' => $item['barang_id'],
                        'qty'       => max(1, (int) round($item['qty'] * $faktor)),
                    ];
                }, $itemList);

                // Hitung detail harga per item
                $poItems = [];
                $totalNilai = 0;

                foreach ($itemsSeed as $item) {
                    $bid = $item['barang_id'];
                    if (!isset($barang[$bid])) continue;

                    $hargaPokok  = (float) $barang[$bid]->harga_pokok;
                    $markup      = 1 + (mt_rand(2, 6) / 100);   // 2–6%
                    $hargaSatuan = round($hargaPokok * $markup, 2);
                    $diskon      = [0, 0, 0, 0.5, 1.0, 1.5, 2.0][array_rand([0,0,0,0.5,1.0,1.5,2.0])];
                    $subtotal    = round($item['qty'] * $hargaSatuan * (1 - $diskon / 100), 2);

                    $poItems[] = [
                        'barang_id'     => $bid,
                        'jumlah_pesan'  => $item['qty'],
                        'harga_satuan'  => $hargaSatuan,
                        'diskon_persen' => $diskon,
                        'subtotal'      => $subtotal,
                    ];
                    $totalNilai += $subtotal;
                }

                $nomorPo          = 'PO-2025-06-' . str_pad($poSeq++, 4, '0', STR_PAD_LEFT);
                $tglDisetujui     = (clone $tglPo)->addDay();
                $tglKirimEstimasi = (clone $tglPo)->addDays(3);

                // Buat PO
                $po = PurchaseOrder::create([
                    'nomor_po'               => $nomorPo,
                    'supplier_id'            => $supplierId,
                    'dibuat_oleh'            => $apotekerId,
                    'disetujui_oleh'         => $adminId,
                    'tanggal_po'             => $tglPo,
                    'tanggal_kirim_estimasi' => $tglKirimEstimasi,
                    'tanggal_disetujui'      => $tglDisetujui,
                    'status'                 => 'selesai',
                    'total_nilai'            => $totalNilai,
                    'catatan'                => null,
                ]);

                // Buat PO Items
                $poItemMap = []; // barang_id => po_item_id
                foreach ($poItems as $item) {
                    $poItem = PoItem::create([
                        'purchase_order_id' => $po->id,
                        'barang_id'         => $item['barang_id'],
                        'jumlah_pesan'      => $item['jumlah_pesan'],
                        'harga_satuan'      => $item['harga_satuan'],
                        'diskon_persen'     => $item['diskon_persen'],
                        'subtotal'          => $item['subtotal'],
                        'jumlah_diterima'   => $item['jumlah_pesan'], // terima penuh
                    ]);
                    $poItemMap[$item['barang_id']] = $poItem->id;
                }

                // Buat GR (1–3 hari setelah PO)
                $tglTerima   = (clone $tglPo)->addDays(mt_rand(1, 3));
                $nomorGr     = 'GR-2025-06-' . str_pad($grSeq, 4, '0', STR_PAD_LEFT);
                $nomorFaktur = 'INV/' . str_pad($supplierId, 2, '0', STR_PAD_LEFT)
                             . '/' . $tglTerima->format('Ym')
                             . '/' . str_pad($grSeq, 4, '0', STR_PAD_LEFT);
                $nomorSJ     = 'SJ/' . str_pad($supplierId, 2, '0', STR_PAD_LEFT)
                             . '/' . $tglTerima->format('Ymd')
                             . '/' . mt_rand(100, 999);
                $grSeq++;

                $gr = GoodsReceipt::create([
                    'nomor_gr'              => $nomorGr,
                    'purchase_order_id'     => $po->id,
                    'supplier_id'           => $supplierId,
                    'diterima_oleh'         => $apotekerId,
                    'tanggal_terima'        => $tglTerima,
                    'nomor_faktur_supplier' => $nomorFaktur,
                    'tanggal_faktur'        => $tglTerima,
                    'tanggal_jatuh_tempo'   => (clone $tglTerima)->addDays(30),
                    'nomor_surat_jalan'     => $nomorSJ,
                    'total_nilai'           => $totalNilai,
                    'status'                => 'diverifikasi',
                    'catatan'               => null,
                ]);

                // Buat GR Items + Mutasi Stok
                foreach ($poItems as $item) {
                    $bid        = $item['barang_id'];
                    $hprSblm    = (float) $barang[$bid]->harga_pokok;
                    $hprStlh    = round($item['harga_satuan'] * (1 - $item['diskon_persen'] / 100), 2);
                    $stokSblm   = $this->stokBerjalan[$bid];
                    $stokStlh   = $stokSblm + $item['jumlah_pesan'];
                    $nomorBatch = 'BT' . $tglTerima->format('Ym') . mt_rand(1000, 9999);
                    $expiredDt  = Carbon::create(
                        2026 + mt_rand(0, 1),
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
                        'user_id'        => $apotekerId,
                        'tipe'           => 'masuk_pembelian',
                        'jumlah'         => $item['jumlah_pesan'],
                        'stok_sebelum'   => $stokSblm,
                        'stok_sesudah'   => $stokStlh,
                        'hpr_sebelum'    => $hprSblm,
                        'hpr_sesudah'    => $hprStlh,
                        'referensi_tipe' => 'goods_receipt',
                        'referensi_id'   => $gr->id,
                        'keterangan'     => 'Penerimaan barang ' . $nomorGr . ' dari ' . $gr->nomor_faktur_supplier,
                    ]);

                    // Update stok berjalan (in-memory)
                    $this->stokBerjalan[$bid] = $stokStlh;
                }

            }
        }

        // Sync stok ke DB (harga_pokok tidak diubah — tetap dari master)
        foreach ($this->stokBerjalan as $id => $stok) {
            Barang::where('id', $id)->update(['stok' => $stok]);
        }

        // Ringkasan
        $totalPo  = PurchaseOrder::where('nomor_po', 'like', 'PO-2025-06-%')->count();
        $totalGr  = GoodsReceipt::where('nomor_gr', 'like', 'GR-2025-06-%')->count();
        $totalNil = PurchaseOrder::where('nomor_po', 'like', 'PO-2025-06-%')->sum('total_nilai');

        $this->command->info(sprintf(
            "✓ %d PO + %d GRN selesai | Total nilai: Rp %s | Rata-rata/hari: Rp %s",
            $totalPo,
            $totalGr,
            number_format($totalNil, 0, ',', '.'),
            number_format($totalNil / 30, 0, ',', '.')
        ));
    }

    /**
     * 4 kelompok barang yang dibeli bergantian tiap hari.
     * Setiap kelompok dikalibrasi menghasilkan ~4–6 juta per PO.
     */
    private function kelompokBarang(): array
    {
        return [
            // Kelompok 0 — Obat-obatan rutin (~5 juta/PO)
            [
                ['barang_id' => 1,  'qty' => 840],  // Paracetamol 500mg  @ 857
                ['barang_id' => 2,  'qty' => 420],  // Amoxicillin 500mg  @ 2.000
                ['barang_id' => 3,  'qty' => 420],  // Ibuprofen 400mg    @ 1.500
                ['barang_id' => 4,  'qty' => 500],  // Antasida Doen      @ 600
                ['barang_id' => 5,  'qty' => 250],  // Cetirizine         @ 2.500
                ['barang_id' => 6,  'qty' => 250],  // Omeprazole         @ 3.000
                ['barang_id' => 7,  'qty' => 330],  // Metformin          @ 1.200
                ['barang_id' => 8,  'qty' => 170],  // Amlodipine         @ 3.352
            ],
            // Kelompok 1 — Alkes habis pakai / consumable (~5 juta/PO)
            [
                ['barang_id' => 9,  'qty' => 370],  // Spuit 1ml          @ 700
                ['barang_id' => 10, 'qty' => 740],  // Spuit 3ml          @ 800
                ['barang_id' => 11, 'qty' => 740],  // Spuit 5ml          @ 900
                ['barang_id' => 12, 'qty' => 370],  // Spuit 10ml         @ 1.200
                ['barang_id' => 15, 'qty' => 1800], // Jarum 23G          @ 500
                ['barang_id' => 24, 'qty' => 370],  // Kassa Steril       @ 1.046
                ['barang_id' => 26, 'qty' => 90],   // Kapas Alkohol Swab @ 12.000
                ['barang_id' => 37, 'qty' => 20],   // Masker Bedah 3-Ply @ 25.000
            ],
            // Kelompok 2 — Alkes premium & diagnostik (~5.5 juta/PO)
            [
                ['barang_id' => 36, 'qty' => 3],    // Sarung Tangan      @ 45.000
                ['barang_id' => 46, 'qty' => 20],   // Strip Gula Darah   @ 80.000
                ['barang_id' => 47, 'qty' => 70],   // Lancet             @ 25.000
                ['barang_id' => 48, 'qty' => 20],   // Elektroda EKG      @ 35.000
                ['barang_id' => 52, 'qty' => 70],   // Surgical Blade 11  @ 2.500
                ['barang_id' => 53, 'qty' => 70],   // Surgical Blade 15  @ 2.500
                ['barang_id' => 54, 'qty' => 40],   // Benang Silk        @ 15.000
                ['barang_id' => 55, 'qty' => 40],   // Benang Catgut      @ 15.000
            ],
            // Kelompok 3 — Cairan infus & IV supplies (~5 juta/PO)
            [
                ['barang_id' => 22, 'qty' => 110],  // Cairan RL 500ml    @ 8.000
                ['barang_id' => 23, 'qty' => 110],  // Cairan NaCl 500ml  @ 8.000
                ['barang_id' => 20, 'qty' => 110],  // Infus Set Dewasa   @ 6.000
                ['barang_id' => 21, 'qty' => 60],   // Infus Set Anak     @ 7.000
                ['barang_id' => 17, 'qty' => 110],  // IV Catheter 18     @ 4.500
                ['barang_id' => 18, 'qty' => 110],  // IV Catheter 20     @ 4.500
                ['barang_id' => 50, 'qty' => 60],   // Urine Bag          @ 8.000
                ['barang_id' => 33, 'qty' => 110],  // Alkohol 70%        @ 6.000
            ],
        ];
    }
}

<?php

namespace Database\Seeders;

use App\Models\Barang;
use App\Models\MutasiStok;
use App\Models\TransaksiRitel;
use App\Models\TransaksiRitelItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TransaksiRitelJuni2025Seeder extends Seeder
{
    private array $stokBerjalan = [];

    // 8 pola pembelian, masing-masing dikalibrasi terhadap target harian
    private function polaBeli(): array
    {
        return [
            // 0 – Obat kronik diabetes (~360k)
            'kronik_diabetes' => [
                ['barang_id' => 7,  'qty' => 30],  // Metformin        @2.000
                ['barang_id' => 8,  'qty' => 30],  // Amlodipine       @3.000
                ['barang_id' => 6,  'qty' => 30],  // Omeprazole       @5.000
                ['barang_id' => 5,  'qty' => 15],  // Cetirizine       @4.000
            ],
            // 1 – Obat kronik hipertensi (~405k)
            'kronik_hipertensi' => [
                ['barang_id' => 8,  'qty' => 30],  // Amlodipine       @3.000
                ['barang_id' => 6,  'qty' => 30],  // Omeprazole       @5.000
                ['barang_id' => 5,  'qty' => 30],  // Cetirizine       @4.000
                ['barang_id' => 1,  'qty' => 30],  // Paracetamol      @1.500
            ],
            // 2 – Obat kronik campuran (~245k)
            'kronik_campuran' => [
                ['barang_id' => 7,  'qty' => 30],  // Metformin        @2.000
                ['barang_id' => 8,  'qty' => 20],  // Amlodipine       @3.000
                ['barang_id' => 5,  'qty' => 20],  // Cetirizine       @4.000
                ['barang_id' => 1,  'qty' => 30],  // Paracetamol      @1.500
            ],
            // 3 – Infeksi / antibiotik (~155k)
            'infeksi' => [
                ['barang_id' => 2,  'qty' => 15],  // Amoxicillin      @3.500
                ['barang_id' => 1,  'qty' => 20],  // Paracetamol      @1.500
                ['barang_id' => 3,  'qty' => 15],  // Ibuprofen        @2.500
                ['barang_id' => 4,  'qty' => 15],  // Antasida         @1.000
                ['barang_id' => 5,  'qty' => 5],   // Cetirizine       @4.000
            ],
            // 4 – Batuk & pilek (~125k)
            'batuk_pilek' => [
                ['barang_id' => 1,  'qty' => 20],  // Paracetamol      @1.500
                ['barang_id' => 5,  'qty' => 15],  // Cetirizine       @4.000
                ['barang_id' => 3,  'qty' => 10],  // Ibuprofen        @2.500
                ['barang_id' => 4,  'qty' => 10],  // Antasida         @1.000
            ],
            // 5 – Perawatan luka (~126k)
            'alkes_luka' => [
                ['barang_id' => 32, 'qty' => 2],   // Betadine 60ml    @16.000
                ['barang_id' => 24, 'qty' => 10],  // Kassa Steril     @2.200
                ['barang_id' => 28, 'qty' => 2],   // Plester Luka     @25.000
                ['barang_id' => 33, 'qty' => 2],   // Alkohol 70%      @11.000
            ],
            // 6 – Kesehatan harian / alkes (~170k)
            'alkes_harian' => [
                ['barang_id' => 37, 'qty' => 3],   // Masker 3-Ply     @40.000
                ['barang_id' => 34, 'qty' => 2],   // Hand Sanitizer   @15.000
                ['barang_id' => 26, 'qty' => 1],   // Kapas Alkohol    @20.000
            ],
            // 7 – Obat umum / OTC (~165k)
            'obat_umum' => [
                ['barang_id' => 1,  'qty' => 20],  // Paracetamol      @1.500
                ['barang_id' => 3,  'qty' => 10],  // Ibuprofen        @2.500
                ['barang_id' => 6,  'qty' => 10],  // Omeprazole       @5.000
                ['barang_id' => 4,  'qty' => 20],  // Antasida         @1.000
                ['barang_id' => 5,  'qty' => 10],  // Cetirizine       @4.000
            ],
        ];
    }

    // 26 transaksi per hari dikalibrasi ke ~5 juta
    // weighted: 2×kronik_diabetes + 2×kronik_hipertensi + 2×kronik_campuran
    //         + 3×infeksi + 4×batuk_pilek + 3×alkes_luka + 3×alkes_harian + 7×obat_umum
    private array $jadwalHarian = [
        'kronik_diabetes', 'kronik_diabetes',
        'kronik_hipertensi', 'kronik_hipertensi',
        'kronik_campuran', 'kronik_campuran',
        'infeksi', 'infeksi', 'infeksi',
        'batuk_pilek', 'batuk_pilek', 'batuk_pilek', 'batuk_pilek',
        'alkes_luka', 'alkes_luka', 'alkes_luka',
        'alkes_harian', 'alkes_harian', 'alkes_harian',
        'obat_umum', 'obat_umum', 'obat_umum', 'obat_umum', 'obat_umum', 'obat_umum', 'obat_umum',
    ];

    private array $namaPembeli = [
        'Budi Santoso', 'Siti Rahayu', 'Agus Wijaya', 'Dewi Lestari', 'Hendra Kurniawan',
        'Rina Wati', 'Bambang Susilo', 'Yuni Astuti', 'Doni Prasetyo', 'Sri Mulyani',
        'Eko Saputro', 'Fitri Handayani', 'Wahyu Nugroho', 'Ani Purnama', 'Rudi Hermawan',
        'Linda Sari', 'Iwan Setiawan', 'Maya Anggraeni', 'Heru Santoso', 'Tuti Rahayu',
        'Arif Budiman', 'Nining Ratnasari', 'Joko Susanto', 'Endang Susilowati', 'Fajar Wibowo',
        'Sari Utami', 'Rizky Pratama', 'Wulandari', 'Catur Supriyadi', 'Elsa Permata',
        'Gunawan Hidayat', 'Hari Prabowo', 'Indah Pertiwi', 'Jamal Effendi', 'Karina Putri',
        'Lutfi Rahman', 'Mira Handayani', 'Nanda Saputra', 'Oktavia Sari', 'Panji Kuncoro',
        'Qori Aisyah', 'Rendra Bagas', 'Sinta Dewi', 'Teguh Santoso', 'Ulfah Nuraini',
        'Viko Erlangga', 'Wenny Setiasih', 'Xander Putra', 'Yayan Sopian', 'Zulfa Amalia',
    ];

    private array $nomorHp = [
        '08111234567', '08222345678', '08333456789', '08444567890', '08555678901',
        '08999887766', '08777654321', '08666543210', '08123456789', '08234567890',
    ];

    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('transaksi_ritel_item')->truncate();
        DB::table('transaksi_ritel')->truncate();
        DB::table('mutasi_stok')->where('tipe', 'keluar_ritel')->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $apotekerId = User::where('nama', 'like', '%Apoteker%')->value('id') ?? 5;
        $kasirId    = User::where('nama', 'like', '%Kasir%')->value('id') ?? 6;

        // Load stok saat ini (setelah GRN seeder)
        $barang = Barang::where('is_active', 1)->get()->keyBy('id');
        foreach ($barang as $id => $b) {
            $this->stokBerjalan[$id] = (int) $b->stok;
        }

        $pola     = $this->polaBeli();
        $metode   = ['tunai', 'tunai', 'tunai', 'transfer', 'transfer', 'kartu'];
        $ritelSeq = []; // nomor urut per tanggal

        for ($day = 1; $day <= 30; $day++) {
            $tglBase = Carbon::create(2025, 6, $day, 8, 0, 0);
            $tglStr  = $tglBase->format('Ymd');

            // Acak urutan jadwal harian agar tidak identik setiap hari
            $jadwalHari = $this->jadwalHarian;
            shuffle($jadwalHari);

            $urutan = 1;

            foreach ($jadwalHari as $namaTemplat) {
                $items = $pola[$namaTemplat];

                // Variasi kuantitas ±20%
                $itemsSeed = array_map(function ($item) {
                    $faktor = (80 + mt_rand(0, 40)) / 100;
                    return [
                        'barang_id' => $item['barang_id'],
                        'qty'       => max(1, (int) round($item['qty'] * $faktor)),
                    ];
                }, $items);

                // Hitung total harga
                $ritelItems = [];
                $totalHarga = 0;

                foreach ($itemsSeed as $item) {
                    $bid = $item['barang_id'];
                    if (!isset($barang[$bid])) continue;

                    $hargaSatuan = (float) $barang[$bid]->harga_jual;
                    $subtotal    = $item['qty'] * $hargaSatuan;

                    $ritelItems[] = [
                        'barang_id'   => $bid,
                        'qty'         => $item['qty'],
                        'harga_satuan' => $hargaSatuan,
                        'subtotal'    => $subtotal,
                    ];
                    $totalHarga += $subtotal;
                }

                if (empty($ritelItems) || $totalHarga <= 0) continue;

                // Waktu transaksi: 08:00–21:00 tersebar merata
                $menitOffset = (int) (($urutan / count($jadwalHari)) * 780); // 13 jam = 780 menit
                $menitJitter = mt_rand(-15, 15);
                $dibayarAt   = (clone $tglBase)->addMinutes($menitOffset + $menitJitter);
                $diserahkanAt = (clone $dibayarAt)->addMinutes(mt_rand(10, 30));

                // Metode pembayaran & kembalian
                $metodeBayar = $metode[array_rand($metode)];
                if ($metodeBayar === 'tunai') {
                    // Bulatkan ke atas kelipatan 1000 atau 5000
                    $kelipatan  = $totalHarga <= 50000 ? 1000 : 5000;
                    $totalBayar = ceil($totalHarga / $kelipatan) * $kelipatan;
                } else {
                    $totalBayar = $totalHarga; // transfer/kartu = exact
                }
                $kembalian = $totalBayar - $totalHarga;

                // Nomor ritel
                $nomorRitel = 'RIT-' . $tglStr . '-' . str_pad($urutan, 4, '0', STR_PAD_LEFT);

                // Pembeli
                $namaPembeli = $this->namaPembeli[($day * 26 + $urutan) % count($this->namaPembeli)];
                $nomorHp     = mt_rand(0, 3) === 0 ? null : $this->nomorHp[mt_rand(0, count($this->nomorHp) - 1)];

                // Buat transaksi
                $tr = TransaksiRitel::create([
                    'nomor_ritel'  => $nomorRitel,
                    'nama_pembeli' => $namaPembeli,
                    'nomor_hp'     => $nomorHp,
                    'pasien_id'    => null,
                    'apoteker_id'  => $apotekerId,
                    'kasir_id'     => $kasirId,
                    'sesi_kas_id'  => null,
                    'status'       => 'selesai',
                    'metode_bayar' => $metodeBayar,
                    'total_harga'  => $totalHarga,
                    'total_bayar'  => $totalBayar,
                    'kembalian'    => $kembalian,
                    'catatan'      => null,
                    'dibayar_at'   => $dibayarAt,
                    'diserahkan_at' => $diserahkanAt,
                    'created_at'   => $dibayarAt,
                    'updated_at'   => $diserahkanAt,
                ]);

                // Buat item + mutasi stok
                foreach ($ritelItems as $item) {
                    TransaksiRitelItem::create([
                        'transaksi_ritel_id' => $tr->id,
                        'barang_id'          => $item['barang_id'],
                        'jumlah'             => $item['qty'],
                        'harga_satuan'       => $item['harga_satuan'],
                        'subtotal'           => $item['subtotal'],
                        'catatan'            => null,
                    ]);

                    $bid        = $item['barang_id'];
                    $stokSblm   = $this->stokBerjalan[$bid] ?? 0;
                    $stokStlh   = max(0, $stokSblm - $item['qty']);
                    $hprPokok   = (float) $barang[$bid]->harga_pokok;

                    MutasiStok::create([
                        'barang_id'      => $bid,
                        'user_id'        => $kasirId,
                        'tipe'           => 'keluar_ritel',
                        'jumlah'         => $item['qty'],
                        'stok_sebelum'   => $stokSblm,
                        'stok_sesudah'   => $stokStlh,
                        'hpr_sebelum'    => $hprPokok,
                        'hpr_sesudah'    => $hprPokok,
                        'referensi_tipe' => 'transaksi_ritel',
                        'referensi_id'   => $tr->id,
                        'keterangan'     => 'Penjualan ritel ' . $nomorRitel,
                    ]);

                    $this->stokBerjalan[$bid] = $stokStlh;
                }

                $urutan++;
            }
        }

        // Sync stok akhir ke DB
        foreach ($this->stokBerjalan as $id => $stok) {
            Barang::where('id', $id)->update(['stok' => $stok]);
        }

        // Ringkasan
        $totalTrx  = TransaksiRitel::where('nomor_ritel', 'like', 'RIT-2025%')->count();
        $totalNil  = TransaksiRitel::where('nomor_ritel', 'like', 'RIT-2025%')->sum('total_harga');
        $rataHari  = $totalNil / 30;

        $this->command->info(sprintf(
            "✓ %d transaksi ritel Juni 2025 | Total: Rp %s | Rata-rata/hari: Rp %s",
            $totalTrx,
            number_format($totalNil, 0, ',', '.'),
            number_format($rataHari, 0, ',', '.')
        ));
    }
}

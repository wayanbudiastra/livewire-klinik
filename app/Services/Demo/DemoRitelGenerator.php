<?php

namespace App\Services\Demo;

use App\Models\Barang;
use App\Models\MutasiStok;
use App\Models\TransaksiRitel;
use App\Models\TransaksiRitelItem;
use App\Models\User;
use Carbon\Carbon;

class DemoRitelGenerator
{
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

    // Template belanja — base qty dikalibrasi ke ~5 juta/hari base
    private function polaBeli(): array
    {
        return [
            'kronik_diabetes' => [
                ['barang_id' => 7, 'qty' => 30],
                ['barang_id' => 8, 'qty' => 30],
                ['barang_id' => 6, 'qty' => 30],
                ['barang_id' => 5, 'qty' => 15],
            ],
            'kronik_hipertensi' => [
                ['barang_id' => 8, 'qty' => 30],
                ['barang_id' => 6, 'qty' => 30],
                ['barang_id' => 5, 'qty' => 30],
                ['barang_id' => 1, 'qty' => 30],
            ],
            'kronik_campuran' => [
                ['barang_id' => 7, 'qty' => 30],
                ['barang_id' => 8, 'qty' => 20],
                ['barang_id' => 5, 'qty' => 20],
                ['barang_id' => 1, 'qty' => 30],
            ],
            'infeksi' => [
                ['barang_id' => 2, 'qty' => 15],
                ['barang_id' => 1, 'qty' => 20],
                ['barang_id' => 3, 'qty' => 15],
                ['barang_id' => 4, 'qty' => 15],
                ['barang_id' => 5, 'qty' => 5],
            ],
            'batuk_pilek' => [
                ['barang_id' => 1, 'qty' => 20],
                ['barang_id' => 5, 'qty' => 15],
                ['barang_id' => 3, 'qty' => 10],
                ['barang_id' => 4, 'qty' => 10],
            ],
            'alkes_luka' => [
                ['barang_id' => 32, 'qty' => 2],
                ['barang_id' => 24, 'qty' => 10],
                ['barang_id' => 28, 'qty' => 2],
                ['barang_id' => 33, 'qty' => 2],
            ],
            'alkes_harian' => [
                ['barang_id' => 37, 'qty' => 3],
                ['barang_id' => 34, 'qty' => 2],
                ['barang_id' => 26, 'qty' => 1],
            ],
            'obat_umum' => [
                ['barang_id' => 1, 'qty' => 20],
                ['barang_id' => 3, 'qty' => 10],
                ['barang_id' => 6, 'qty' => 10],
                ['barang_id' => 4, 'qty' => 20],
                ['barang_id' => 5, 'qty' => 10],
            ],
        ];
    }

    // 26 transaksi/hari: 2+2+2+3+4+3+3+7 = 26 (dikalibrasi ke 5jt base)
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

    /**
     * Generate transaksi ritel untuk rentang tanggal & target tertentu.
     *
     * @param  \Carbon\Carbon  $dari
     * @param  \Carbon\Carbon  $sampai
     * @param  int             $targetHarianRp  Target omzet per hari (Rp)
     * @param  int             $userId          apoteker_id (kasir_id = userId juga)
     * @param  array           &$stokBerjalan   Stok tracking bersama antar generator
     * @param  callable|null   $onProgress      Callback per hari selesai
     * @return array           ['ritel_ids'=>[], 'total_harga'=>0.0, 'per_hari'=>[]]
     */
    public function generate(
        Carbon $dari,
        Carbon $sampai,
        int $targetHarianRp,
        int $userId,
        array &$stokBerjalan,
        ?callable $onProgress = null
    ): array {
        $barang      = Barang::where('is_active', 1)->get()->keyBy('id');
        $pola        = $this->polaBeli();
        $faktorSkala = $targetHarianRp / 5_000_000; // base 5jt/hari
        $metode      = ['tunai', 'tunai', 'tunai', 'transfer', 'transfer', 'kartu'];

        // Sequence tracker per tanggal untuk nomor ritel
        $seqRitel = [];

        $ritelIds   = [];
        $totalHarga = 0.0;
        $perHari    = [];

        $current = $dari->copy()->startOfDay();
        $end     = $sampai->copy()->endOfDay();
        $daySeq  = 0;

        while ($current->lte($end)) {
            $tglBase  = $current->copy()->setTime(8, 0, 0);
            $tglStr   = $current->format('Ymd');
            $nilaiHari = 0.0;
            $trxHari  = 0;

            // Init sequence untuk tanggal ini (lanjut dari yang sudah ada di DB)
            if (!isset($seqRitel[$tglStr])) {
                $prefix = 'RIT-' . $tglStr . '-';
                $last   = \App\Models\TransaksiRitel::where('nomor_ritel', 'like', $prefix . '%')
                            ->orderByDesc('nomor_ritel')->value('nomor_ritel');
                $seqRitel[$tglStr] = $last ? (int) substr($last, -4) : 0;
            }

            // Acak urutan jadwal harian
            $jadwalHari = $this->jadwalHarian;
            shuffle($jadwalHari);

            $urutanHari = 0;
            foreach ($jadwalHari as $namaTemplate) {
                $items = $pola[$namaTemplate];

                // Kalibrasi qty ke target dengan variasi ±20%
                $itemsSeed = array_map(function ($item) use ($faktorSkala) {
                    $faktor = $faktorSkala * ((80 + mt_rand(0, 40)) / 100);
                    return [
                        'barang_id' => $item['barang_id'],
                        'qty'       => max(1, (int) round($item['qty'] * $faktor)),
                    ];
                }, $items);

                // Hitung total & filter item yang barangnya ada
                $ritelItems = [];
                $total      = 0.0;

                foreach ($itemsSeed as $item) {
                    $bid = $item['barang_id'];
                    if (!isset($barang[$bid])) continue;

                    // Batasi qty agar stok tidak negatif (BR-10)
                    $stokTersedia = $stokBerjalan[$bid] ?? 0;
                    $qty          = min($item['qty'], max(0, $stokTersedia));
                    if ($qty <= 0) continue;

                    $hargaSatuan = (float) $barang[$bid]->harga_jual;
                    $subtotal    = $qty * $hargaSatuan;

                    $ritelItems[] = [
                        'barang_id'    => $bid,
                        'qty'          => $qty,
                        'harga_satuan' => $hargaSatuan,
                        'subtotal'     => $subtotal,
                    ];
                    $total += $subtotal;
                }

                if (empty($ritelItems) || $total <= 0) {
                    $urutanHari++;
                    continue;
                }

                // Waktu transaksi tersebar merata 08:00–21:00
                $jumlahTrx  = count($jadwalHari);
                $menitTotal = 780; // 13 jam = 780 menit
                $menitOffset = (int) (($urutanHari / $jumlahTrx) * $menitTotal);
                $menitJitter = mt_rand(-15, 15);
                $dibayarAt   = (clone $tglBase)->addMinutes($menitOffset + $menitJitter);
                $diserahkanAt = (clone $dibayarAt)->addMinutes(mt_rand(10, 30));

                $metodeBayar = $metode[array_rand($metode)];
                if ($metodeBayar === 'tunai') {
                    $kelipatan  = $total <= 50000 ? 1000 : 5000;
                    $totalBayar = (int) (ceil($total / $kelipatan) * $kelipatan);
                } else {
                    $totalBayar = (int) round($total);
                }
                $kembalian = $totalBayar - $total;

                $seqRitel[$tglStr]++;
                $nomorRitel  = 'RIT-' . $tglStr . '-' . str_pad($seqRitel[$tglStr], 4, '0', STR_PAD_LEFT);
                $namaPembeli = $this->namaPembeli[($daySeq * 26 + $urutanHari) % count($this->namaPembeli)];
                $nomorHp     = mt_rand(0, 3) === 0 ? null : $this->nomorHp[mt_rand(0, count($this->nomorHp) - 1)];

                $tr = TransaksiRitel::create([
                    'nomor_ritel'   => $nomorRitel,
                    'nama_pembeli'  => $namaPembeli,
                    'nomor_hp'      => $nomorHp,
                    'pasien_id'     => null,
                    'apoteker_id'   => $userId,
                    'kasir_id'      => $userId,
                    'sesi_kas_id'   => null,
                    'status'        => 'selesai',
                    'metode_bayar'  => $metodeBayar,
                    'total_harga'   => round($total, 2),
                    'total_bayar'   => $totalBayar,
                    'kembalian'     => round($kembalian, 2),
                    'catatan'       => null,
                    'dibayar_at'    => $dibayarAt,
                    'diserahkan_at' => $diserahkanAt,
                    'created_at'    => $dibayarAt,
                    'updated_at'    => $diserahkanAt,
                ]);

                foreach ($ritelItems as $item) {
                    TransaksiRitelItem::create([
                        'transaksi_ritel_id' => $tr->id,
                        'barang_id'          => $item['barang_id'],
                        'jumlah'             => $item['qty'],
                        'harga_satuan'       => $item['harga_satuan'],
                        'subtotal'           => $item['subtotal'],
                        'catatan'            => null,
                    ]);

                    $bid      = $item['barang_id'];
                    $stokSblm = $stokBerjalan[$bid] ?? 0;
                    $stokStlh = max(0, $stokSblm - $item['qty']);
                    $hprPokok = (float) $barang[$bid]->harga_pokok;

                    MutasiStok::create([
                        'barang_id'      => $bid,
                        'user_id'        => $userId,
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

                    $stokBerjalan[$bid] = $stokStlh;
                }

                $ritelIds[]  = $tr->id;
                $nilaiHari  += $total;
                $totalHarga += $total;
                $trxHari++;
                $urutanHari++;
            }

            $perHari[] = [
                'tanggal' => $current->toDateString(),
                'trx'     => $trxHari,
                'harga'   => $nilaiHari,
            ];

            if ($onProgress) {
                ($onProgress)([
                    'tipe'    => 'ritel',
                    'tanggal' => $current->toDateString(),
                    'trx'     => $trxHari,
                    'harga'   => $nilaiHari,
                ]);
            }

            $current->addDay();
            $daySeq++;
        }

        return [
            'ritel_ids'   => $ritelIds,
            'total_harga' => $totalHarga,
            'per_hari'    => $perHari,
        ];
    }
}

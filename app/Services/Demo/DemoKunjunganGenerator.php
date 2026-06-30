<?php

namespace App\Services\Demo;

use App\Models\AsesmenPerawat;
use App\Models\Barang;
use App\Models\Dokter;
use App\Models\ItemResep;
use App\Models\MutasiStok;
use App\Models\Pasien;
use App\Models\Perawat;
use App\Models\Resep;
use App\Models\SoapNote;
use App\Models\Tindakan;
use App\Models\Kunjungan;
use App\Models\User;
use Carbon\Carbon;

class DemoKunjunganGenerator
{
    // Bobot pola (harus total 100)
    private array $bobotPola = [
        'umum_ringan'     => 35,
        'kronik'          => 25,
        'tindakan_minor'  => 15,
        'anak'            => 15,
        'nebulisasi'      => 10,
    ];

    // ICD codes per pola
    private array $icdPola = [
        'umum_ringan'    => [['kode' => 'J06.9', 'nama' => 'ISPA bagian atas , tidak spesifik', 'is_primary' => true]],
        'kronik'         => [
            ['kode' => 'E11.9', 'nama' => 'Non - insulin-dependent diabetes mellitus tanpa komplikasi', 'is_primary' => true],
            ['kode' => 'I10',   'nama' => 'Esensial ( primer) hipertensi', 'is_primary' => false],
        ],
        'tindakan_minor' => [['kode' => 'S51.8', 'nama' => 'Luka terbuka dari bagian lain dari lengan', 'is_primary' => true]],
        'anak'           => [['kode' => 'R50.9', 'nama' => 'Demam , tidak spesifik', 'is_primary' => true]],
        'nebulisasi'     => [['kode' => 'J45.9', 'nama' => 'Asma , tidak spesifik', 'is_primary' => true]],
    ];

    private array $keluhanPola = [
        'umum_ringan'    => [
            'Batuk dan pilek sejak 3 hari, demam ringan, tenggorokan gatal dan perih',
            'Demam sejak kemarin, pilek, badan terasa pegal-pegal',
            'Radang tenggorokan, susah menelan, batuk kering',
        ],
        'kronik'         => [
            'Kontrol rutin DM dan hipertensi, minta perpanjangan obat',
            'Kepala terasa berat, sering haus dan BAK, minta cek gula darah',
            'Kontrol tekanan darah, obat habis',
        ],
        'tindakan_minor' => [
            'Luka robek di lengan kiri ±3 cm, perdarahan sudah berhenti',
            'Luka sayat di tangan kanan, butuh jahitan',
            'Luka lecet dalam di kaki, perlu dibersihkan dan dijahit',
        ],
        'anak'           => [
            'Anak demam 3 hari, tidak mau makan, rewel',
            'Demam naik turun sejak 2 hari, anak lemas',
            'Panas tinggi malam hari, anak menangis terus',
        ],
        'nebulisasi'     => [
            'Sesak napas, napas berbunyi ngik-ngik, batuk produktif',
            'Dada terasa berat, napas pendek-pendek sejak pagi',
            'Asma kambuh, butuh nebulisasi',
        ],
    ];

    // Poli per pola (poli_id)
    private array $poliPola = [
        'umum_ringan'    => 1,
        'kronik'         => 5,
        'tindakan_minor' => 1,
        'anak'           => 3,
        'nebulisasi'     => 1,
    ];

    // Master tindakan [id, jumlah] per pola
    private array $tindakanPola = [
        'umum_ringan'    => [[1, 1]],
        'kronik'         => [[1, 1], [11, 1]],
        'tindakan_minor' => [[1, 1], [7, 1]],
        'anak'           => [[1, 1]],
        'nebulisasi'     => [[1, 1], [10, 1]],
    ];

    // Resep per pola: [barang_id, jumlah, aturan_pakai]
    private array $resepPola = [
        'umum_ringan'    => [
            [1, 15, '3x1 tablet setelah makan'],
            [5,  5, '1x1 tablet malam hari'],
            [2, 15, '3x1 tablet setelah makan'],
        ],
        'kronik'         => [
            [7, 30, '2x1 tablet pagi dan malam'],
            [8, 30, '1x1 tablet pagi hari'],
            [6, 30, '1x1 tablet sebelum makan'],
            [5, 15, '1x1 tablet malam hari'],
        ],
        'tindakan_minor' => [
            [2, 15, '3x1 tablet setelah makan'],
            [3, 10, '3x1 tablet setelah makan'],
            [32, 1, 'Oleskan 2x sehari pada luka'],
            [24, 5, 'Ganti balut 1x sehari'],
        ],
        'anak'           => [
            [1, 10, '3x1 tablet jika demam'],
            [4, 10, '3x1 tablet setelah makan'],
            [5,  5, '1x1 tablet malam hari'],
        ],
        'nebulisasi'     => [
            [5, 10, '2x1 tablet'],
            [56, 1, 'Nebulisasi 2x sehari'],
        ],
    ];

    // Vitals baseline per pola
    private array $vitalsPola = [
        'umum_ringan'    => ['sistol' => 120, 'diastol' => 80, 'nadi' => 82, 'suhu' => 37.4, 'saturasi' => 98, 'bb' => 62, 'tb' => 165, 'gds' => null],
        'kronik'         => ['sistol' => 140, 'diastol' => 90, 'nadi' => 78, 'suhu' => 36.7, 'saturasi' => 99, 'bb' => 70, 'tb' => 162, 'gds' => 210],
        'tindakan_minor' => ['sistol' => 118, 'diastol' => 76, 'nadi' => 88, 'suhu' => 36.9, 'saturasi' => 99, 'bb' => 65, 'tb' => 170, 'gds' => null],
        'anak'           => ['sistol' => 100, 'diastol' => 70, 'nadi' => 95, 'suhu' => 38.2, 'saturasi' => 97, 'bb' => 22, 'tb' => 118, 'gds' => null],
        'nebulisasi'     => ['sistol' => 125, 'diastol' => 85, 'nadi' => 98, 'suhu' => 37.1, 'saturasi' => 94, 'bb' => 58, 'tb' => 160, 'gds' => null],
    ];

    /**
     * Generate kunjungan + asesmen + SOAP + resep + tindakan untuk rentang tanggal.
     *
     * @param  array  &$stokBerjalan     Stok tracking bersama
     * @param  bool   $includeResepStok  Buat mutasi_stok keluar_resep
     * @return array  ['kunjungan_ids'=>[], 'per_hari'=>[]]
     */
    public function generate(
        Carbon $dari,
        Carbon $sampai,
        int $jumlahPerHari,
        int $userId,
        array &$stokBerjalan,
        bool $includeResepStok = true,
        ?callable $onProgress = null
    ): array {
        $pasienIds      = Pasien::aktif()->pluck('id')->toArray();
        $barang         = Barang::where('is_active', 1)->get()->keyBy('id');
        $perawatId      = Perawat::first()?->id;   // FK ke perawat.id, nullable
        $apotekerId     = User::role('apoteker')->value('id') ?? $userId;
        $masterTindakan = \App\Models\MasterTindakan::aktif()->get()->keyBy('id');
        $dokterPerPoli  = $this->loadDokterPerPoli();

        if (empty($pasienIds)) {
            throw new \RuntimeException('Tidak ada pasien aktif. Tambahkan data pasien terlebih dahulu.');
        }

        $jadwalHarian = $this->buildJadwalHarian($jumlahPerHari);
        $seqAntrean   = [];
        $kunjunganIds = [];
        $perHari      = [];

        $current = $dari->copy()->startOfDay();
        $end     = $sampai->copy()->endOfDay();
        $dayIdx  = 0;

        while ($current->lte($end)) {
            $tglStr     = $current->toDateString();
            $jumlahHari = 0;

            $jadwalAcak = $jadwalHarian;
            shuffle($jadwalAcak);

            $urutanHari = 0;
            foreach ($jadwalAcak as $namaPola) {
                $poliId       = $this->poliPola[$namaPola];
                $dokter       = $this->ambilDokter($poliId, $dokterPerPoli);
                $dokterId     = $dokter?->id;
                $dokterUserId = $dokter?->user_id ?? $userId;

                $pasienId = $pasienIds[($dayIdx * $jumlahPerHari + $urutanHari) % count($pasienIds)];

                // Nomor antrean W-001 per poli per tanggal
                $poliKey = "{$tglStr}_{$poliId}";
                if (!isset($seqAntrean[$poliKey])) {
                    $existing = Kunjungan::whereDate('tanggal', $tglStr)
                        ->where('poli_id', $poliId)
                        ->where('nomor_antrean', 'like', 'W-%')
                        ->whereNotIn('status', ['dibatalkan'])
                        ->count();
                    $seqAntrean[$poliKey] = $existing;
                }
                $seqAntrean[$poliKey]++;
                $nomorAntrean = 'W-' . str_pad($seqAntrean[$poliKey], 3, '0', STR_PAD_LEFT);

                // Waktu kunjungan: 08:00–14:00
                $menitOffset  = (int) (($urutanHari / max($jumlahPerHari, 1)) * 360);
                $tglKunjungan = $current->copy()->setTime(8, 0, 0)->addMinutes($menitOffset + mt_rand(-8, 8));
                $tglPanggil   = (clone $tglKunjungan)->addMinutes(mt_rand(5, 30));

                // 1. Kunjungan
                $kunjungan = Kunjungan::create([
                    'appointment_id'     => null,
                    'nomor_antrean'      => $nomorAntrean,
                    'pasien_id'          => $pasienId,
                    'dokter_id'          => $dokterId,
                    'poli_id'            => $poliId,
                    'tanggal'            => $tglKunjungan,
                    'keluhan'            => $this->ambilKeluhan($namaPola),
                    'status'             => 'selesai',
                    'tipe_pembayaran'    => 'umum',
                    'waktu_panggil'      => $tglPanggil,
                    'asal_kedatangan'    => 'datang_sendiri',
                    'catatan_penting'    => null,
                    'pasien_asuransi_id' => null,
                ]);

                // 2. Asesmen perawat
                $vitals = $this->generateVitals($namaPola);
                AsesmenPerawat::create([
                    'kunjungan_id'  => $kunjungan->id,
                    'perawat_id'    => $perawatId,
                    'berat_badan'   => $vitals['bb'],
                    'tinggi_badan'  => $vitals['tb'],
                    'tekanan_darah' => "{$vitals['sistol']}/{$vitals['diastol']}",
                    'nadi'          => $vitals['nadi'],
                    'suhu'          => $vitals['suhu'],
                    'saturasi'      => $vitals['saturasi'],
                    'gds'           => $vitals['gds'],
                    'anamnesis_awal'=> $this->ambilKeluhan($namaPola),
                ]);

                // 3. SOAP Note
                $finalizedAt = (clone $tglPanggil)->addMinutes(mt_rand(20, 60));
                SoapNote::create([
                    'kunjungan_id' => $kunjungan->id,
                    'subjektif'    => $this->soapSubjektif($namaPola, $vitals),
                    'objektif'     => $this->soapObjektif($namaPola, $vitals),
                    'asesmen'      => $this->soapAsesmen($namaPola),
                    'plan'         => $this->soapPlan($namaPola),
                    'icd_codes'    => $this->icdPola[$namaPola],
                    'is_final'     => true,
                    'finalized_at' => $finalizedAt,
                    'finalized_by' => $dokterUserId,
                ]);

                // 4. Resep + ItemResep
                $resepItems = $this->resepPola[$namaPola] ?? [];
                if (!empty($resepItems)) {
                    $lockedAt = (clone $finalizedAt)->addMinutes(mt_rand(15, 30));
                    $resep = Resep::create([
                        'kunjungan_id'    => $kunjungan->id,
                        'dokter_id'       => $dokterId,
                        'status'          => 'diambil',
                        'catatan'         => null,
                        'is_locked'       => true,
                        'locked_by'       => $apotekerId,
                        'locked_at'       => $lockedAt,
                        'catatan_farmasi' => null,
                    ]);

                    foreach ($resepItems as [$barangId, $jumlah, $aturanPakai]) {
                        if (!isset($barang[$barangId])) continue;

                        ItemResep::create([
                            'resep_id'    => $resep->id,
                            'barang_id'   => $barangId,
                            'jumlah'      => $jumlah,
                            'aturan_pakai'=> $aturanPakai,
                            'catatan'     => null,
                        ]);

                        if ($includeResepStok) {
                            $stokSblm  = $stokBerjalan[$barangId] ?? 0;
                            $qtyKeluar = min($jumlah, max(0, $stokSblm));
                            if ($qtyKeluar > 0) {
                                $stokStlh = $stokSblm - $qtyKeluar;
                                $hprPokok = (float) $barang[$barangId]->harga_pokok;

                                MutasiStok::create([
                                    'barang_id'      => $barangId,
                                    'user_id'        => $apotekerId,
                                    'tipe'           => 'keluar_resep',
                                    'jumlah'         => $qtyKeluar,
                                    'stok_sebelum'   => $stokSblm,
                                    'stok_sesudah'   => $stokStlh,
                                    'hpr_sebelum'    => $hprPokok,
                                    'hpr_sesudah'    => $hprPokok,
                                    'referensi_tipe' => 'resep',
                                    'referensi_id'   => $resep->id,
                                    'keterangan'     => 'Pemakaian resep kunjungan ' . $nomorAntrean,
                                ]);

                                $stokBerjalan[$barangId] = $stokStlh;
                            }
                        }
                    }
                }

                // 5. Tindakan
                foreach ($this->tindakanPola[$namaPola] as [$masterTindakanId, $jumlahTindakan]) {
                    if (!isset($masterTindakan[$masterTindakanId])) continue;

                    Tindakan::create([
                        'kunjungan_id'       => $kunjungan->id,
                        'master_tindakan_id' => $masterTindakanId,
                        'pelaksana_id'       => $dokterUserId,
                        'jumlah'             => $jumlahTindakan,
                        'waktu_tindakan'     => (clone $tglPanggil)->addMinutes(10 + mt_rand(0, 15)),
                        'catatan'            => null,
                    ]);
                }

                $kunjunganIds[] = $kunjungan->id;
                $jumlahHari++;
                $urutanHari++;
            }

            $perHari[] = ['tanggal' => $tglStr, 'jumlah' => $jumlahHari];

            if ($onProgress) {
                ($onProgress)(['tipe' => 'kunjungan', 'tanggal' => $tglStr, 'jumlah' => $jumlahHari]);
            }

            $current->addDay();
            $dayIdx++;
        }

        return ['kunjungan_ids' => $kunjunganIds, 'per_hari' => $perHari];
    }

    // ── Helpers ────────────────────────────────────────────────────

    private function buildJadwalHarian(int $jumlahPerHari): array
    {
        $jadwal = [];
        $sisa   = $jumlahPerHari;

        foreach ($this->bobotPola as $pola => $bobot) {
            $n = (int) floor($jumlahPerHari * $bobot / 100);
            for ($i = 0; $i < $n; $i++) $jadwal[] = $pola;
            $sisa -= $n;
        }

        for ($i = 0; $i < $sisa; $i++) $jadwal[] = 'umum_ringan';

        return $jadwal;
    }

    private function loadDokterPerPoli(): array
    {
        $map = [];
        Dokter::with('dokterPoli')->get()->each(function (Dokter $d) use (&$map) {
            foreach ($d->dokterPoli as $dp) {
                $map[$dp->poli_id][] = $d;
            }
        });
        return $map;
    }

    private function ambilDokter(int $poliId, array $dokterPerPoli): ?Dokter
    {
        $list = $dokterPerPoli[$poliId] ?? [];
        if (!empty($list)) return $list[array_rand($list)];

        $semua = array_merge(...array_values($dokterPerPoli ?: [[]]));
        if (!empty($semua)) return $semua[array_rand($semua)];

        return null;
    }

    private function ambilKeluhan(string $pola): string
    {
        $list = $this->keluhanPola[$pola] ?? ['Keluhan umum'];
        return $list[array_rand($list)];
    }

    private function generateVitals(string $pola): array
    {
        $base = $this->vitalsPola[$pola];
        return [
            'sistol'   => $base['sistol'] + mt_rand(-10, 10),
            'diastol'  => $base['diastol'] + mt_rand(-5, 5),
            'nadi'     => $base['nadi'] + mt_rand(-8, 8),
            'suhu'     => round($base['suhu'] + (mt_rand(-3, 3) / 10), 1),
            'saturasi' => min(100, $base['saturasi'] + mt_rand(-1, 1)),
            'bb'       => $base['bb'] + mt_rand(-5, 5),
            'tb'       => $base['tb'],
            'gds'      => $base['gds'] ? $base['gds'] + mt_rand(-30, 30) : null,
        ];
    }

    private function soapSubjektif(string $pola, array $v): string
    {
        $keluhan = $this->ambilKeluhan($pola);
        return match ($pola) {
            'umum_ringan'    => "Pasien datang dengan keluhan {$keluhan}. Tidak ada sesak napas, tidak ada mual muntah.",
            'kronik'         => "Pasien datang untuk {$keluhan}. Sering haus dan BAK, kepala terasa berat, energi menurun.",
            'tindakan_minor' => "Pasien datang dengan keluhan {$keluhan}. Perdarahan aktif sudah dihentikan sebelum datang ke klinik.",
            'anak'           => "Pasien anak datang dibawa orang tua dengan keluhan {$keluhan}. Nafsu makan menurun, anak tampak lemas.",
            'nebulisasi'     => "Pasien datang dengan keluhan {$keluhan}. Riwayat asma sebelumnya (+).",
            default          => "Pasien datang dengan keluhan {$keluhan}.",
        };
    }

    private function soapObjektif(string $pola, array $v): string
    {
        $td   = "{$v['sistol']}/{$v['diastol']} mmHg";
        $nadi = "{$v['nadi']}x/mnt";
        $suhu = "{$v['suhu']} C";
        $spo2 = "{$v['saturasi']}%";
        $gds  = $v['gds'] ? " GDS {$v['gds']} mg/dL." : '';

        return match ($pola) {
            'umum_ringan'    => "KU: baik, CM. TD {$td}, Nadi {$nadi}, Suhu {$suhu}, SpO2 {$spo2}. Faring hiperemis, tonsil tidak membesar. Pulmo: vesikuler, ronki -/-.",
            'kronik'         => "KU: cukup, CM. TD {$td}, Nadi {$nadi}, Suhu {$suhu}, SpO2 {$spo2}.{$gds} Konjungtiva tidak anemis. Abdomen: supel, BU (+) normal.",
            'tindakan_minor' => "KU: baik, CM. TD {$td}, Nadi {$nadi}, Suhu {$suhu}, SpO2 {$spo2}. Status lokalis: luka robek kurang lebih 3 cm, tepi rata, tidak ada tanda infeksi.",
            'anak'           => "KU: tampak sakit sedang. TD {$td}, Nadi {$nadi}, Suhu {$suhu}, SpO2 {$spo2}. Anak rewel, mata tidak cekung. Turgor baik.",
            'nebulisasi'     => "KU: tampak sesak. TD {$td}, Nadi {$nadi}, Suhu {$suhu}, SpO2 {$spo2}. Auskultasi: wheezing +/+, ekspirasi memanjang.",
            default          => "KU: baik. TD {$td}, Nadi {$nadi}, Suhu {$suhu}, SpO2 {$spo2}.",
        };
    }

    private function soapAsesmen(string $pola): string
    {
        return match ($pola) {
            'umum_ringan'    => 'ISPA akut tidak spesifik (J06.9)',
            'kronik'         => 'DM Tipe 2 tidak terkontrol (E11.9) + Hipertensi esensial (I10)',
            'tindakan_minor' => 'Luka terbuka lengan bawah (S51.8)',
            'anak'           => 'Demam tidak spesifik (R50.9), observasi kemungkinan viral infection',
            'nebulisasi'     => 'Asma bronkial serangan ringan-sedang (J45.9)',
            default          => 'Pemeriksaan umum',
        };
    }

    private function soapPlan(string $pola): string
    {
        return match ($pola) {
            'umum_ringan'    => 'Istirahat cukup, minum air putih 2L/hari, obat sesuai resep. Kontrol bila tidak membaik dalam 3 hari.',
            'kronik'         => 'Lanjut terapi, perketat diet rendah gula dan garam, olahraga rutin 30 mnt/hari. Kontrol 1 bulan lagi.',
            'tindakan_minor' => 'Debridement dan penjahitan luka. Antibiotik, analgesik sesuai resep. Kontrol 3 hari lagi untuk evaluasi.',
            'anak'           => 'Kompres hangat jika demam, banyak minum cairan, parasetamol jika demam >38.5 C. Kontrol segera jika demam tidak turun 2 hari.',
            'nebulisasi'     => 'Nebulisasi sesuai protokol, evaluasi SpO2 post-nebulisasi. Obat bronkodilator oral dilanjutkan. Kontrol 2 hari lagi.',
            default          => 'Terapi simtomatik, obat sesuai resep, kontrol bila perlu.',
        };
    }
}

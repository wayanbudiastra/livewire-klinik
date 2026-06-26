<?php

namespace Database\Seeders;

use App\Models\AsesmenPerawat;
use App\Models\Barang;
use App\Models\IcdDiagnosis;
use App\Models\ItemResep;
use App\Models\Kunjungan;
use App\Models\Resep;
use App\Models\SoapNote;
use App\Models\Tindakan;
use App\Services\KunjunganService;
use App\Services\PasienService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder demo: 5 pasien poliklinik dengan alur lengkap
 * registrasi → asesmen perawat → SOAP (dgn diagnosa ICD-10) → resep obat → tindakan → status kunjungan "selesai".
 *
 * Jalankan terpisah: php artisan db:seed --class=RegistrasiPoliklinikSeeder
 * (tidak dimasukkan ke DatabaseSeeder utama agar tidak terduplikasi setiap fresh seed)
 */
class RegistrasiPoliklinikSeeder extends Seeder
{
    public function run(): void
    {
        $pasienService    = app(PasienService::class);
        $kunjunganService = app(KunjunganService::class);

        $apotekerUserId = 5; // Apoteker Rina

        $kasus = [
            [
                'pasien' => [
                    'nama' => 'Made Surya Pratama', 'tempat_lahir' => 'Denpasar',
                    'tanggal_lahir' => '1990-04-12', 'jenis_kelamin' => 'L',
                    'nik' => '5171041204900001', 'alamat' => 'Jl. Gatot Subroto No. 12, Denpasar',
                    'telepon' => '081234560001', 'golongan_darah' => 'O',
                ],
                'poli_id' => 1, 'dokter_id' => 1, 'dokter_user_id' => 3,
                'keluhan' => 'Demam, batuk, dan pilek sejak 3 hari yang lalu',
                'vitals' => ['berat_badan' => 68, 'tinggi_badan' => 170, 'tekanan_darah' => '120/80',
                    'nadi' => 88, 'suhu' => 38.2, 'saturasi' => 98, 'gds' => null,
                    'anamnesis_awal' => 'Pasien mengeluh demam naik turun, batuk berdahak, dan pilek. Belum minum obat.'],
                'soap' => [
                    's_cc_hpi' => 'Demam, batuk berdahak, dan pilek sejak 3 hari. Tidak ada sesak napas.',
                    's_past_medical' => 'Tidak ada riwayat penyakit kronis.',
                    's_past_surgical' => 'Tidak ada riwayat operasi.',
                    's_allergies' => 'Tidak ada riwayat alergi obat.',
                    's_other' => 'Nafsu makan menurun, tidur cukup.',
                    'o_physical_exam' => 'Tampak sakit ringan, compos mentis. Tenggorokan hiperemis.',
                    'o_systemic_exam' => 'Faring hiperemis, tonsil T1-T1. Paru: ronkhi minimal di basal kanan.',
                    'o_observation' => 'Suhu febris 38.2°C, lainnya dalam batas normal.',
                    'a_problems' => 'ISPA (Infeksi Saluran Pernapasan Akut)',
                    'a_progress_note' => 'Gejala khas ISPA tanpa tanda komplikasi pneumonia.',
                    'p_advice' => 'Istirahat cukup, minum air hangat, kontrol bila demam >3 hari atau sesak.',
                ],
                'icd' => 'J00',
                'tindakan' => [['kode' => 'T001', 'jumlah' => 1], ['kode' => 'T010', 'jumlah' => 1]],
                'resep' => [
                    ['kode' => 'OBT001', 'jumlah' => 10, 'aturan_pakai' => '3x1 tablet setelah makan'],
                    ['kode' => 'OBT002', 'jumlah' => 15, 'aturan_pakai' => '3x1 kapsul sampai habis'],
                    ['kode' => 'OBT005', 'jumlah' => 10, 'aturan_pakai' => '1x1 tablet malam hari'],
                ],
            ],
            [
                'pasien' => [
                    'nama' => 'Ni Kadek Ayu Lestari', 'tempat_lahir' => 'Gianyar',
                    'tanggal_lahir' => '1998-09-25', 'jenis_kelamin' => 'P',
                    'nik' => '5171042509980002', 'alamat' => 'Jl. Raya Sukawati No. 45, Gianyar',
                    'telepon' => '081234560002', 'golongan_darah' => 'B',
                ],
                'poli_id' => 1, 'dokter_id' => 2, 'dokter_user_id' => 9,
                'keluhan' => 'BAB cair lebih dari 5 kali sejak kemarin, disertai demam',
                'vitals' => ['berat_badan' => 52, 'tinggi_badan' => 158, 'tekanan_darah' => '100/70',
                    'nadi' => 96, 'suhu' => 37.8, 'saturasi' => 98, 'gds' => null,
                    'anamnesis_awal' => 'BAB cair >5x/hari sejak kemarin, tidak ada darah/lendir. Masih bisa minum.'],
                'soap' => [
                    's_cc_hpi' => 'Diare cair >5x sejak 1 hari, disertai demam ringan dan mual.',
                    's_past_medical' => 'Tidak ada riwayat penyakit kronis.',
                    's_past_surgical' => 'Tidak ada riwayat operasi.',
                    's_allergies' => 'Tidak ada riwayat alergi obat.',
                    's_other' => 'Riwayat makan makanan pedas di luar 2 hari lalu.',
                    'o_physical_exam' => 'Tampak sakit ringan-sedang, turgor kulit kembali agak lambat.',
                    'o_systemic_exam' => 'Bising usus meningkat. Abdomen supel, nyeri tekan epigastrium ringan.',
                    'o_observation' => 'Tanda dehidrasi ringan, suhu subfebris 37.8°C.',
                    'a_problems' => 'Diare akut dengan dehidrasi ringan',
                    'a_progress_note' => 'Suspek gastroenteritis akut, perlu rehidrasi dan observasi.',
                    'p_advice' => 'Perbanyak cairan oral, hindari makanan pedas/berlemak, kontrol bila tidak ada perbaikan dalam 2 hari.',
                ],
                'icd' => 'A09',
                'tindakan' => [['kode' => 'T001', 'jumlah' => 1], ['kode' => 'T006', 'jumlah' => 1]],
                'resep' => [
                    ['kode' => 'OBT001', 'jumlah' => 6, 'aturan_pakai' => '1x1 tablet bila demam'],
                    ['kode' => 'OBT002', 'jumlah' => 15, 'aturan_pakai' => '3x1 kapsul sampai habis'],
                ],
            ],
            [
                'pasien' => [
                    'nama' => 'I Wayan Suarjana', 'tempat_lahir' => 'Tabanan',
                    'tanggal_lahir' => '1965-01-30', 'jenis_kelamin' => 'L',
                    'nik' => '5171043001650003', 'alamat' => 'Jl. Pulau Bali No. 8, Tabanan',
                    'telepon' => '081234560003', 'golongan_darah' => 'A',
                ],
                'poli_id' => 1, 'dokter_id' => 1, 'dokter_user_id' => 3,
                'keluhan' => 'Kontrol tekanan darah tinggi, kadang pusing dan tengkuk berat',
                'vitals' => ['berat_badan' => 75, 'tinggi_badan' => 165, 'tekanan_darah' => '160/100',
                    'nadi' => 84, 'suhu' => 36.7, 'saturasi' => 97, 'gds' => 110,
                    'anamnesis_awal' => 'Pasien rutin kontrol hipertensi, mengaku kadang lupa minum obat.'],
                'soap' => [
                    's_cc_hpi' => 'Kontrol rutin hipertensi, mengeluh pusing dan tengkuk berat 2 hari terakhir.',
                    's_past_medical' => 'Hipertensi sejak 5 tahun lalu, rutin kontrol tidak teratur.',
                    's_past_surgical' => 'Tidak ada riwayat operasi.',
                    's_allergies' => 'Tidak ada riwayat alergi obat.',
                    's_other' => 'Riwayat konsumsi garam tinggi, jarang olahraga.',
                    'o_physical_exam' => 'Tampak sehat, kesadaran compos mentis.',
                    'o_systemic_exam' => 'Jantung: S1-S2 reguler, tidak ada murmur. Paru dalam batas normal.',
                    'o_observation' => 'Tekanan darah 160/100 mmHg, GDS dalam batas normal.',
                    'a_problems' => 'Hipertensi tidak terkontrol',
                    'a_progress_note' => 'Kepatuhan minum obat kurang, perlu edukasi dan penyesuaian dosis.',
                    'p_advice' => 'Minum obat teratur setiap hari, kurangi garam, kontrol ulang 2 minggu.',
                ],
                'icd' => 'I10',
                'tindakan' => [['kode' => 'T001', 'jumlah' => 1]],
                'resep' => [
                    ['kode' => 'OBT008', 'jumlah' => 30, 'aturan_pakai' => '1x1 tablet pagi hari'],
                ],
            ],
            [
                'pasien' => [
                    'nama' => 'Ni Luh Putu Wahyuni', 'tempat_lahir' => 'Singaraja',
                    'tanggal_lahir' => '2003-07-08', 'jenis_kelamin' => 'P',
                    'nik' => '5171040807030004', 'alamat' => 'Jl. Ahmad Yani No. 21, Singaraja',
                    'telepon' => '081234560004', 'golongan_darah' => 'AB',
                ],
                'poli_id' => 7, 'dokter_id' => 1, 'dokter_user_id' => 3,
                'keluhan' => 'Mata kanan merah, berair, dan terasa mengganjal sejak 2 hari',
                'vitals' => ['berat_badan' => 50, 'tinggi_badan' => 156, 'tekanan_darah' => '110/70',
                    'nadi' => 80, 'suhu' => 36.5, 'saturasi' => 99, 'gds' => null,
                    'anamnesis_awal' => 'Mata kanan merah dan berair sejak 2 hari, ada kotoran mata saat bangun pagi.'],
                'soap' => [
                    's_cc_hpi' => 'Mata kanan merah, berair, gatal, dan ada sekret mukopurulen sejak 2 hari.',
                    's_past_medical' => 'Tidak ada riwayat penyakit mata sebelumnya.',
                    's_past_surgical' => 'Tidak ada riwayat operasi.',
                    's_allergies' => 'Riwayat alergi debu.',
                    's_other' => 'Teman sekelas ada yang mengalami keluhan serupa.',
                    'o_physical_exam' => 'Konjungtiva bulbi OD hiperemis, sekret mukopurulen (+).',
                    'o_systemic_exam' => 'Visus ODS 6/6. Kornea jernih, tidak ada infiltrat.',
                    'o_observation' => 'Tidak ada gangguan penglihatan, tanda vital dalam batas normal.',
                    'a_problems' => 'Konjungtivitis mukopurulen mata kanan',
                    'a_progress_note' => 'Curiga konjungtivitis bakterial, kemungkinan menular dari kontak.',
                    'p_advice' => 'Jaga kebersihan tangan, hindari mengusap mata, kontrol bila tidak ada perbaikan 5 hari.',
                ],
                'icd' => 'H10.0',
                'tindakan' => [['kode' => 'T002', 'jumlah' => 1], ['kode' => 'T003', 'jumlah' => 1]],
                'resep' => [
                    ['kode' => 'OBT005', 'jumlah' => 10, 'aturan_pakai' => '1x1 tablet malam (antihistamin)'],
                ],
            ],
            [
                'pasien' => [
                    'nama' => 'I Made Dwi Cahyadi', 'tempat_lahir' => 'Klungkung',
                    'tanggal_lahir' => '1980-11-17', 'jenis_kelamin' => 'L',
                    'nik' => '5171041711800005', 'alamat' => 'Jl. Diponegoro No. 33, Klungkung',
                    'telepon' => '081234560005', 'golongan_darah' => 'O',
                ],
                'poli_id' => 1, 'dokter_id' => 2, 'dokter_user_id' => 9,
                'keluhan' => 'Nyeri ulu hati dan mual sejak 4 hari, terutama saat terlambat makan',
                'vitals' => ['berat_badan' => 70, 'tinggi_badan' => 168, 'tekanan_darah' => '118/76',
                    'nadi' => 82, 'suhu' => 36.6, 'saturasi' => 98, 'gds' => null,
                    'anamnesis_awal' => 'Nyeri ulu hati seperti terbakar, mual, sering terlambat makan karena kerja shift.'],
                'soap' => [
                    's_cc_hpi' => 'Nyeri ulu hati seperti terbakar sejak 4 hari, mual, kembung, memberat saat terlambat makan.',
                    's_past_medical' => 'Riwayat maag berulang dalam 1 tahun terakhir.',
                    's_past_surgical' => 'Tidak ada riwayat operasi.',
                    's_allergies' => 'Tidak ada riwayat alergi obat.',
                    's_other' => 'Sering konsumsi kopi dan makan tidak teratur karena kerja shift malam.',
                    'o_physical_exam' => 'Tampak sakit ringan, kesadaran compos mentis.',
                    'o_systemic_exam' => 'Nyeri tekan epigastrium (+), bising usus normal, tidak ada tanda peritonitis.',
                    'o_observation' => 'Tanda vital dalam batas normal.',
                    'a_problems' => 'Gastritis akut',
                    'a_progress_note' => 'Gejala khas dispepsia tipe nyeri ulu hati, kemungkinan terkait pola makan.',
                    'p_advice' => 'Makan teratur, hindari kopi dan makanan pedas/asam, kontrol bila nyeri menetap >1 minggu.',
                ],
                'icd' => 'K29.0',
                'tindakan' => [['kode' => 'T001', 'jumlah' => 1]],
                'resep' => [
                    ['kode' => 'OBT006', 'jumlah' => 14, 'aturan_pakai' => '2x1 kapsul sebelum makan'],
                    ['kode' => 'OBT004', 'jumlah' => 10, 'aturan_pakai' => '3x1 tablet bila perlu'],
                ],
            ],
        ];

        foreach ($kasus as $i => $k) {
            DB::transaction(function () use ($k, $pasienService, $kunjunganService, $apotekerUserId, $i) {
                // 1. Registrasi pasien baru
                $pasien = $pasienService->create(array_merge($k['pasien'], [
                    'tipe_pasien' => 'WNI',
                    'is_active'   => true,
                ]));

                // 2. Daftarkan kunjungan (status awal: menunggu)
                $noAntrean = $kunjunganService->generateNomorAntrean($k['poli_id'], now()->toDateString());
                $kunjungan = Kunjungan::create([
                    'nomor_antrean'   => $noAntrean,
                    'pasien_id'       => $pasien->id,
                    'dokter_id'       => $k['dokter_id'],
                    'poli_id'         => $k['poli_id'],
                    'tanggal'         => now(),
                    'keluhan'         => $k['keluhan'],
                    'status'          => 'menunggu',
                    'tipe_pembayaran' => 'umum',
                ]);

                // 3. Asesmen awal perawat (vitals)
                AsesmenPerawat::create(array_merge(['kunjungan_id' => $kunjungan->id], $k['vitals']));

                // 4. Panggil pasien ke ruang periksa
                $kunjunganService->panggilPasien($kunjungan->id);

                // 5. SOAP + diagnosa ICD-10, langsung difinalisasi
                $icd = IcdDiagnosis::where('kode', $k['icd'])->first();
                $diagnosa = $icd ? [[
                    'kode'       => $icd->kode,
                    'nama'       => $icd->nama,
                    'is_primary' => true,
                ]] : [];

                SoapNote::create(array_merge($k['soap'], [
                    'kunjungan_id' => $kunjungan->id,
                    'icd_codes'    => $diagnosa,
                    'is_final'     => true,
                    'finalized_at' => now(),
                    'finalized_by' => $k['dokter_user_id'],
                ]));

                // 6. Resep obat — langsung dianggap selesai diserahkan farmasi
                $resep = Resep::create([
                    'kunjungan_id' => $kunjungan->id,
                    'dokter_id'    => $k['dokter_id'],
                    'status'       => 'diambil',
                    'is_locked'    => true,
                    'locked_by'    => $apotekerUserId,
                    'locked_at'    => now(),
                ]);

                foreach ($k['resep'] as $r) {
                    $barang = Barang::where('kode', $r['kode'])->first();
                    if (! $barang) continue;

                    ItemResep::create([
                        'resep_id'     => $resep->id,
                        'barang_id'    => $barang->id,
                        'jumlah'       => $r['jumlah'],
                        'aturan_pakai' => $r['aturan_pakai'],
                    ]);

                    $barang->decrement('stok', min($r['jumlah'], $barang->stok));
                }

                // 7. Tindakan medis
                foreach ($k['tindakan'] as $t) {
                    $mt = \App\Models\MasterTindakan::where('kode', $t['kode'])->first();
                    if (! $mt) continue;

                    Tindakan::create([
                        'kunjungan_id'       => $kunjungan->id,
                        'master_tindakan_id' => $mt->id,
                        'pelaksana_id'       => $k['dokter_user_id'],
                        'jumlah'             => $t['jumlah'],
                        'waktu_tindakan'      => now(),
                    ]);
                }

                // 8. Tutup kunjungan — pemeriksaan selesai
                $kunjunganService->selesaiPemeriksaan($kunjungan->id);

                $this->command->info('✓ ['.($i + 1).'/5] '.$pasien->nama.' ('.$pasien->nomor_rm.') — kunjungan selesai.');
            });
        }

        $this->command->info('✅ RegistrasiPoliklinikSeeder selesai (5 pasien, registrasi → SOAP → resep → tindakan → selesai).');
    }
}

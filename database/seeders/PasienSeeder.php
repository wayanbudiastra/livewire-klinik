<?php

namespace Database\Seeders;

use App\Models\KontakDarurat;
use App\Models\Pasien;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;

class PasienSeeder extends Seeder
{
    public function run(): void
    {
        $fakerID = Faker::create('id_ID');
        $fakerEN = Faker::create('en_US');

        // Hubungan yang valid sesuai enum di DB
        $hubunganValid = ['ayah', 'ibu', 'anak', 'kakak', 'adik', 'kakek', 'nenek', 'paman', 'bibi', 'keponakan', 'teman', 'lainnya'];

        // Data awal yang sudah ada (RM-000001 s/d RM-000003)
        // Seeder ini dimulai dari RM-000004

        $data = []; // ← inisialisasi array sebelum loop

        for ($i = 5; $i <= 103; $i++) {
            $tipePasien   = rand(1, 10) <= 8 ? 'WNI' : 'WNA';
            $jenisKelamin = rand(0, 1) === 0 ? 'L' : 'P';
            $faker        = $tipePasien === 'WNI' ? $fakerID : $fakerEN;

            // Gunakan name() dengan gender — kompatibel dengan semua versi FakerPHP
            try {
                $namaPasien = $faker->name($jenisKelamin === 'L' ? 'male' : 'female');
            } catch (\Exception) {
                $namaPasien = $faker->name;
            }

            $pasien = [
                'nomor_rm'      => 'RM-' . str_pad($i, 6, '0', STR_PAD_LEFT),
                'nama'          => $namaPasien,
                'tempat_lahir'  => $faker->city,
                // Tanggal lahir antara 80 tahun lalu hingga 5 tahun lalu
                'tanggal_lahir' => $faker->dateTimeBetween('-80 years', '-5 years')->format('Y-m-d'),
                'jenis_kelamin' => $jenisKelamin,
                'tipe_pasien'   => $tipePasien,
                'alamat'        => $faker->streetAddress . ', ' . $faker->city,
                // Telepon format 08xx (10-12 digit total)
                'telepon'       => '08' . $faker->numerify(rand(0,1) ? '#########' : '##########'),
                'email'         => $faker->unique()->safeEmail,
                'is_active'     => true,
            ];

            // Field kondisional berdasarkan tipe pasien
            if ($tipePasien === 'WNI') {
                $pasien['nik']           = $fakerID->unique()->numerify('################');
                $pasien['golongan_darah']= $faker->randomElement(['A', 'B', 'AB', 'O', 'tidak_diketahui']);
                // 50% punya BPJS
                if (rand(0, 1)) {
                    $pasien['no_bpjs'] = $fakerID->unique()->numerify('000##########');
                }
            } else {
                // No. paspor: 2 huruf + 6 angka, uppercase, unik
                $pasien['no_paspor']  = strtoupper($fakerEN->unique()->lexify('??') . $fakerEN->numerify('######'));
                $pasien['negara_asal']= $fakerEN->country;
                // 70% WNA punya asuransi
                if (rand(1, 10) <= 7) {
                    $pasien['no_asuransi'] = 'INS-' . strtoupper($fakerEN->lexify('??')) . '-2024-' . $fakerEN->numerify('###');
                }
            }

            // Kontak Darurat Utama (sesuai jenis kelamin)
            $hubunganUtama   = $jenisKelamin === 'L' ? 'istri' : 'suami';
            try {
                $namaKontakUtama = $faker->name($jenisKelamin === 'L' ? 'female' : 'male');
            } catch (\Exception) {
                $namaKontakUtama = $faker->name;
            }

            $kontakDarurat = [
                [
                    'nama'       => $namaKontakUtama,
                    'nomor_hp'   => '08' . $faker->numerify('##########'),
                    'hubungan'   => $hubunganUtama,
                    'is_primary' => true,
                ],
            ];

            // 40% kemungkinan kontak darurat kedua
            if (rand(1, 10) <= 4) {
                $kontakDarurat[] = [
                    'nama'       => $faker->name,
                    'nomor_hp'   => '08' . $faker->numerify('##########'),
                    'hubungan'   => $faker->randomElement($hubunganValid),
                    'is_primary' => false,
                ];
            }

            $pasien['kontak'] = $kontakDarurat;
            $data[]           = $pasien;
        }

        // Simpan ke database
        $berhasil = 0;
        foreach ($data as $d) {
            $kontak = $d['kontak'];
            unset($d['kontak']);

            $pasien = Pasien::firstOrCreate(
                ['nomor_rm' => $d['nomor_rm']],
                $d
            );

            if ($pasien->kontakDarurat()->count() === 0) {
                foreach ($kontak as $k) {
                    $pasien->kontakDarurat()->create($k);
                }
            }

            $berhasil++;
            $this->command->info("✓ [{$berhasil}/100] {$d['nama']} [{$d['tipe_pasien']}] — {$d['nomor_rm']}");
        }

        $this->command->info("✅ PasienSeeder selesai ({$berhasil} data pasien ditambahkan).");
    }
}

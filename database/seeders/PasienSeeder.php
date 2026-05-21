<?php

namespace Database\Seeders;

use App\Models\KontakDarurat;
use App\Models\Pasien;
use Illuminate\Database\Seeder;

class PasienSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            [
                'nomor_rm'      => 'RM-000001',
                'nama'          => 'Budi Santoso',
                'tempat_lahir'  => 'Jakarta',
                'tanggal_lahir' => '1985-03-15',
                'jenis_kelamin' => 'L',
                'tipe_pasien'   => 'WNI',
                'nik'           => '3174051503850001',
                'alamat'        => 'Jl. Merdeka No. 12, Gambir, Jakarta Pusat',
                'telepon'       => '08123456789',
                'email'         => 'budi.santoso@email.com',
                'golongan_darah'=> 'A',
                'no_bpjs'       => '0001234567890',
                'kontak'        => [
                    ['nama' => 'Siti Santoso', 'nomor_hp' => '08129876543',
                     'hubungan' => 'istri', 'is_primary' => true],
                ],
            ],
            [
                'nomor_rm'      => 'RM-000002',
                'nama'          => 'John Smith',
                'tempat_lahir'  => 'New York',
                'tanggal_lahir' => '1990-07-22',
                'jenis_kelamin' => 'L',
                'tipe_pasien'   => 'WNA',
                'no_paspor'     => 'US123456',
                'negara_asal'   => 'Amerika Serikat',
                'alamat'        => 'Jl. Sunset Road No. 88, Kuta, Bali',
                'telepon'       => '081398765432',
                'no_asuransi'   => 'INS-US-2024-001',
                'kontak'        => [
                    ['nama' => 'Jane Smith', 'nomor_hp' => '081312345678',
                     'hubungan' => 'istri', 'is_primary' => true],
                ],
            ],
            [
                'nomor_rm'      => 'RM-000003',
                'nama'          => 'Ni Luh Ayu Dewi',
                'tempat_lahir'  => 'Denpasar',
                'tanggal_lahir' => '1995-11-08',
                'jenis_kelamin' => 'P',
                'tipe_pasien'   => 'WNI',
                'nik'           => '5171086811950001',
                'alamat'        => 'Jl. Raya Kuta No. 45, Banjar Pande, Denpasar',
                'telepon'       => '082145678901',
                'golongan_darah'=> 'O',
                'kontak'        => [
                    ['nama' => 'I Wayan Dewi',  'nomor_hp' => '082198765432',
                     'hubungan' => 'ayah', 'is_primary' => true],
                    ['nama' => 'Ni Made Sari',  'nomor_hp' => '082187654321',
                     'hubungan' => 'ibu',  'is_primary' => false],
                ],
            ],
        ];

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

            $this->command->info("✓ Pasien: {$d['nama']} [{$d['tipe_pasien']}] — {$d['nomor_rm']}");
        }

        $this->command->info('✅ PasienSeeder selesai (3 data).');
    }
}

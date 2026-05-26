<?php

namespace Database\Seeders;

use App\Models\Asuransi;
use Illuminate\Database\Seeder;

class AsuransiSeeder extends Seeder
{
    public function run(): void
    {
        $list = [
            [
                'kode'                 => 'ASR-001',
                'nama'                 => 'Prudential',
                'tipe'                 => 'swasta',
                'cover_prosedur'       => 100,
                'cover_laboratorium'   => 80,
                'cover_radiologi'      => 70,
                'cover_peralatan'      => 50,
                'plafon_per_kunjungan' => 5000000,
                'term_pembayaran_hari' => 30,
                'pic'                  => 'Customer Service',
                'telepon'              => '0215551234',
            ],
            [
                'kode'                 => 'ASR-002',
                'nama'                 => 'AXA Mandiri',
                'tipe'                 => 'swasta',
                'cover_prosedur'       => 90,
                'cover_laboratorium'   => 90,
                'cover_radiologi'      => 80,
                'cover_peralatan'      => 60,
                'plafon_per_kunjungan' => 3000000,
                'term_pembayaran_hari' => 45,
            ],
            [
                'kode'                 => 'ASR-003',
                'nama'                 => 'Asuransi Corporate PT Sehat',
                'tipe'                 => 'corporate',
                'cover_prosedur'       => 100,
                'cover_laboratorium'   => 100,
                'cover_radiologi'      => 100,
                'cover_peralatan'      => 100,
                'term_pembayaran_hari' => 60,
            ],
        ];

        foreach ($list as $a) {
            Asuransi::updateOrCreate(['kode' => $a['kode']], $a);
            $this->command->info("Asuransi: {$a['nama']}");
        }
    }
}

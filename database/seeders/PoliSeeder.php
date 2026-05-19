<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PoliSeeder extends Seeder
{
    public function run(): void
    {
        $poli = [
            ['nama' => 'Poli Umum',      'kode' => 'UMUM'],
            ['nama' => 'Poli Gigi',       'kode' => 'GIGI'],
            ['nama' => 'Poli Anak',       'kode' => 'ANAK'],
            ['nama' => 'Poli KIA',        'kode' => 'KIA'],
            ['nama' => 'Poli Dalam',      'kode' => 'DALAM'],
            ['nama' => 'Poli Bedah',      'kode' => 'BEDAH'],
            ['nama' => 'Poli Mata',       'kode' => 'MATA'],
            ['nama' => 'Poli THT',        'kode' => 'THT'],
            ['nama' => 'Poli Kulit',      'kode' => 'KULIT'],
            ['nama' => 'Poli Jantung',    'kode' => 'JANTUNG'],
        ];

        foreach ($poli as $p) {
            DB::table('poli')->insertOrIgnore([
                'nama'      => $p['nama'],
                'kode'      => $p['kode'],
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}

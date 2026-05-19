<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class KlinikSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('klinik')->insertOrIgnore([
            [
                'nama'     => 'Klinik Sehat Bersama',
                'alamat'   => 'Jl. Kesehatan No. 1, Denpasar, Bali',
                'telepon'  => '0361-123456',
                'email'    => 'info@kliniksehat.com',
                'logo'     => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}

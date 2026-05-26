<?php

namespace Database\Seeders;

use App\Models\ConfigBpjs;
use Illuminate\Database\Seeder;

class ConfigBpjsSeeder extends Seeder
{
    public function run(): void
    {
        ConfigBpjs::updateOrCreate(['id' => 1], [
            'kerjasama' => false,
            'is_active' => false,
        ]);
        $this->command->info('Config BPJS diinisialisasi (default: tidak kerjasama)');
    }
}

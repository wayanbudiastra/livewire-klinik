<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            UserSeeder::class,
            KlinikSeeder::class,
            PoliSeeder::class,
            // ObatSeeder::class, // tabel obat sudah dihapus, digabung ke barang
            MasterdataV2Seeder::class,
            DokterV3Seeder::class,
            // PasienSeeder::class,
            FarmasiSeeder::class,
            InventorySeeder::class,
            Icd10Seeder::class,
            PenunjangSeeder::class,
            SumberInformasiSeeder::class,
            ConfigBpjsSeeder::class,
            AsuransiSeeder::class,
            ChartOfAccountSeeder::class,
        ]);
    }
}

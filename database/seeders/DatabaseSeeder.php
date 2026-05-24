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
            ObatSeeder::class,
            MasterdataV2Seeder::class,
            DokterV3Seeder::class,
            PasienSeeder::class,
            FarmasiSeeder::class,
            InventorySeeder::class,
            Icd10Seeder::class,
            PenunjangSeeder::class,
        ]);
    }
}

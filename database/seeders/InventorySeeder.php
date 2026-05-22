<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;

class InventorySeeder extends Seeder
{
    public function run(): void
    {
        $suppliers = [
            ['kode' => 'SUP-001', 'nama' => 'Kimia Farma Trading & Distribution', 'tipe' => 'distributor', 'pic' => 'Budi Hartono', 'telepon' => '021-1234567', 'email' => 'order@kimiafarmaTD.com', 'lead_time_hari' => 3, 'top_hari' => 30],
            ['kode' => 'SUP-002', 'nama' => 'Enseval Putera Megatrading', 'tipe' => 'distributor', 'pic' => 'Sari Dewi', 'telepon' => '021-7654321', 'email' => 'sales@enseval.com', 'lead_time_hari' => 2, 'top_hari' => 21],
            ['kode' => 'SUP-003', 'nama' => 'PT Indo Farma', 'tipe' => 'prinsipal', 'pic' => 'Ahmad Fauzi', 'telepon' => '021-9876543', 'email' => 'procurement@indofarma.id', 'lead_time_hari' => 5, 'top_hari' => 45],
            ['kode' => 'SUP-004', 'nama' => 'Bina San Prima', 'tipe' => 'distributor', 'pic' => 'Rina Kusuma', 'telepon' => '022-1122334', 'email' => 'order@bsp.co.id', 'lead_time_hari' => 3, 'top_hari' => 30],
        ];

        foreach ($suppliers as $s) {
            Supplier::firstOrCreate(['kode' => $s['kode']], array_merge($s, ['is_active' => true]));
        }

        $this->command->info('✓ Supplier: ' . count($suppliers) . ' data');
        $this->command->info('✅ InventorySeeder selesai.');
    }
}

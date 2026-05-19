<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ObatSeeder extends Seeder
{
    public function run(): void
    {
        $obat = [
            ['kode' => 'OBT001', 'nama' => 'Paracetamol 500mg', 'generik' => 'Paracetamol', 'satuan' => 'tablet', 'stok' => 500, 'harga' => 1500, 'harga_beli' => 800, 'kategori' => 'Analgesik'],
            ['kode' => 'OBT002', 'nama' => 'Amoxicillin 500mg', 'generik' => 'Amoxicillin', 'satuan' => 'kapsul', 'stok' => 300, 'harga' => 3500, 'harga_beli' => 2000, 'kategori' => 'Antibiotik'],
            ['kode' => 'OBT003', 'nama' => 'Ibuprofen 400mg',   'generik' => 'Ibuprofen',   'satuan' => 'tablet', 'stok' => 200, 'harga' => 2500, 'harga_beli' => 1500, 'kategori' => 'Analgesik'],
            ['kode' => 'OBT004', 'nama' => 'Antasida Doen',     'generik' => 'Antasida',     'satuan' => 'tablet', 'stok' => 400, 'harga' => 1000, 'harga_beli' => 600,  'kategori' => 'Antasida'],
            ['kode' => 'OBT005', 'nama' => 'Cetirizine 10mg',   'generik' => 'Cetirizine',   'satuan' => 'tablet', 'stok' => 150, 'harga' => 4000, 'harga_beli' => 2500, 'kategori' => 'Antihistamin'],
            ['kode' => 'OBT006', 'nama' => 'Omeprazole 20mg',   'generik' => 'Omeprazole',   'satuan' => 'kapsul', 'stok' => 250, 'harga' => 5000, 'harga_beli' => 3000, 'kategori' => 'Antasida'],
            ['kode' => 'OBT007', 'nama' => 'Metformin 500mg',   'generik' => 'Metformin',    'satuan' => 'tablet', 'stok' => 300, 'harga' => 2000, 'harga_beli' => 1200, 'kategori' => 'Antidiabetik'],
            ['kode' => 'OBT008', 'nama' => 'Amlodipine 5mg',    'generik' => 'Amlodipine',   'satuan' => 'tablet', 'stok' => 200, 'harga' => 3000, 'harga_beli' => 1800, 'kategori' => 'Antihipertensi'],
        ];

        foreach ($obat as $o) {
            DB::table('obat')->insertOrIgnore([
                'kode'        => $o['kode'],
                'nama'        => $o['nama'],
                'generik'     => $o['generik'],
                'satuan'      => $o['satuan'],
                'stok'        => $o['stok'],
                'harga'       => $o['harga'],
                'harga_beli'  => $o['harga_beli'],
                'kategori'    => $o['kategori'],
                'is_active'   => true,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }
    }
}

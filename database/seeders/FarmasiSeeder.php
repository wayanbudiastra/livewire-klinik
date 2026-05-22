<?php

namespace Database\Seeders;

use App\Models\LokasiGudang;
use App\Models\Satuan;
use Illuminate\Database\Seeder;

class FarmasiSeeder extends Seeder
{
    public function run(): void
    {
        // ── Satuan ────────────────────────────────────────────
        $satuanList = [
            'Tablet', 'Kapsul', 'Botol', 'Box', 'Strip',
            'Pcs', 'Ampul', 'Vial', 'Sachet', 'Tube', 'ml',
        ];

        foreach ($satuanList as $s) {
            Satuan::firstOrCreate(['nama' => $s], ['is_active' => true]);
        }
        $this->command->info('✓ Satuan: ' . count($satuanList) . ' item');

        // ── Lokasi Gudang ─────────────────────────────────────
        $gudangList = [
            ['kode' => 'GD-UTAMA', 'nama' => 'Gudang Utama'],
            ['kode' => 'APT-RJ',   'nama' => 'Apotek Rawat Jalan'],
            ['kode' => 'APT-IGD',  'nama' => 'Apotek IGD'],
            ['kode' => 'APT-RI',   'nama' => 'Apotek Rawat Inap'],
        ];

        foreach ($gudangList as $g) {
            LokasiGudang::firstOrCreate(['kode' => $g['kode']], array_merge($g, ['is_active' => true]));
        }
        $this->command->info('✓ Lokasi Gudang: ' . count($gudangList) . ' lokasi');
        $this->command->info('✅ FarmasiSeeder selesai.');
    }
}

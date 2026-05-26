<?php

namespace Database\Seeders;

use App\Models\SumberInformasi;
use Illuminate\Database\Seeder;

class SumberInformasiSeeder extends Seeder
{
    public function run(): void
    {
        $sumber = [
            ['kode' => 'google',    'nama' => 'Google / Pencarian Web',  'kategori' => 'digital',       'icon' => '🔍', 'urutan' => 1,  'butuh_keterangan' => false],
            ['kode' => 'facebook',  'nama' => 'Facebook',                'kategori' => 'sosial_media',  'icon' => '📘', 'urutan' => 2,  'butuh_keterangan' => false],
            ['kode' => 'instagram', 'nama' => 'Instagram',               'kategori' => 'sosial_media',  'icon' => '📷', 'urutan' => 3,  'butuh_keterangan' => false],
            ['kode' => 'tiktok',    'nama' => 'TikTok',                  'kategori' => 'sosial_media',  'icon' => '🎵', 'urutan' => 4,  'butuh_keterangan' => false],
            ['kode' => 'referensi', 'nama' => 'Referensi Teman/Keluarga','kategori' => 'word_of_mouth', 'icon' => '👥', 'urutan' => 5,  'butuh_keterangan' => false],
            ['kode' => 'spanduk',   'nama' => 'Spanduk / Brosur',        'kategori' => 'offline',       'icon' => '📋', 'urutan' => 6,  'butuh_keterangan' => false],
            ['kode' => 'whatsapp',  'nama' => 'WhatsApp / Broadcast',    'kategori' => 'digital',       'icon' => '💬', 'urutan' => 7,  'butuh_keterangan' => false],
            ['kode' => 'lainnya',   'nama' => 'Lainnya',                 'kategori' => 'lainnya',       'icon' => '➕', 'urutan' => 99, 'butuh_keterangan' => true],
        ];

        foreach ($sumber as $s) {
            SumberInformasi::updateOrCreate(['kode' => $s['kode']], array_merge($s, ['is_active' => true]));
            $this->command->info("✓ {$s['icon']} {$s['nama']}");
        }
    }
}

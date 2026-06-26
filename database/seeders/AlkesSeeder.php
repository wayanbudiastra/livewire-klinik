<?php

namespace Database\Seeders;

use App\Models\Barang;
use Illuminate\Database\Seeder;

/**
 * Masterdata 50 item Alkes (alat kesehatan & bahan medis habis pakai)
 * untuk kebutuhan umum poliklinik (suntik, perawatan luka, diagnostik, APD, dll).
 *
 * Jalankan terpisah: php artisan db:seed --class=AlkesSeeder
 */
class AlkesSeeder extends Seeder
{
    public function run(): void
    {
        $alkes = [
            ['nama' => 'Spuit 1ml',                          'kategori' => 'Disposable',     'satuan' => 'pcs', 'pokok' => 700,    'jual' => 1200,   'stok' => 300, 'min' => 50],
            ['nama' => 'Spuit 3ml',                          'kategori' => 'Disposable',     'satuan' => 'pcs', 'pokok' => 800,    'jual' => 1500,   'stok' => 300, 'min' => 50],
            ['nama' => 'Spuit 5ml',                          'kategori' => 'Disposable',     'satuan' => 'pcs', 'pokok' => 900,    'jual' => 1700,   'stok' => 250, 'min' => 40],
            ['nama' => 'Spuit 10ml',                         'kategori' => 'Disposable',     'satuan' => 'pcs', 'pokok' => 1200,   'jual' => 2200,   'stok' => 200, 'min' => 30],
            ['nama' => 'Spuit 20ml',                         'kategori' => 'Disposable',     'satuan' => 'pcs', 'pokok' => 1800,   'jual' => 3200,   'stok' => 100, 'min' => 20],
            ['nama' => 'Spuit Insulin 1ml',                  'kategori' => 'Disposable',     'satuan' => 'pcs', 'pokok' => 1500,   'jual' => 2800,   'stok' => 100, 'min' => 20],
            ['nama' => 'Jarum Suntik 23G',                   'kategori' => 'Disposable',     'satuan' => 'pcs', 'pokok' => 500,    'jual' => 900,    'stok' => 300, 'min' => 50],
            ['nama' => 'Jarum Suntik 25G',                   'kategori' => 'Disposable',     'satuan' => 'pcs', 'pokok' => 500,    'jual' => 900,    'stok' => 300, 'min' => 50],
            ['nama' => 'IV Catheter (Abocath) No. 18',       'kategori' => 'Infus',          'satuan' => 'pcs', 'pokok' => 4500,   'jual' => 7500,   'stok' => 150, 'min' => 30],
            ['nama' => 'IV Catheter (Abocath) No. 20',       'kategori' => 'Infus',          'satuan' => 'pcs', 'pokok' => 4500,   'jual' => 7500,   'stok' => 150, 'min' => 30],
            ['nama' => 'IV Catheter (Abocath) No. 22',       'kategori' => 'Infus',          'satuan' => 'pcs', 'pokok' => 4500,   'jual' => 7500,   'stok' => 150, 'min' => 30],
            ['nama' => 'Infus Set Dewasa',                   'kategori' => 'Infus',          'satuan' => 'pcs', 'pokok' => 6000,   'jual' => 10000,  'stok' => 120, 'min' => 25],
            ['nama' => 'Infus Set Anak (Mikro)',             'kategori' => 'Infus',          'satuan' => 'pcs', 'pokok' => 7000,   'jual' => 11500,  'stok' => 80,  'min' => 20],
            ['nama' => 'Cairan Infus RL 500ml',              'kategori' => 'Infus',          'satuan' => 'botol','pokok' => 8000,  'jual' => 13000,  'stok' => 100, 'min' => 20],
            ['nama' => 'Cairan Infus NaCl 0.9% 500ml',       'kategori' => 'Infus',          'satuan' => 'botol','pokok' => 8000,  'jual' => 13000,  'stok' => 100, 'min' => 20],
            ['nama' => 'Kassa Steril 16x16cm',               'kategori' => 'Perawatan Luka', 'satuan' => 'pcs', 'pokok' => 1200,   'jual' => 2200,   'stok' => 300, 'min' => 50],
            ['nama' => 'Kassa Gulung (Roll Gauze)',          'kategori' => 'Perawatan Luka', 'satuan' => 'roll','pokok' => 4500,   'jual' => 8000,   'stok' => 150, 'min' => 30],
            ['nama' => 'Kapas Alkohol (Alcohol Swab)',       'kategori' => 'Perawatan Luka', 'satuan' => 'box', 'pokok' => 12000,  'jual' => 20000,  'stok' => 100, 'min' => 20],
            ['nama' => 'Plester Gulung (Micropore) 2.5cm',   'kategori' => 'Perawatan Luka', 'satuan' => 'roll','pokok' => 5000,   'jual' => 9000,   'stok' => 150, 'min' => 30],
            ['nama' => 'Plester Luka (Hansaplast)',          'kategori' => 'Perawatan Luka', 'satuan' => 'box', 'pokok' => 15000,  'jual' => 25000,  'stok' => 100, 'min' => 20],
            ['nama' => 'Underpad / Alas Perlak Sekali Pakai','kategori' => 'Perawatan Luka', 'satuan' => 'pcs', 'pokok' => 3500,   'jual' => 6000,   'stok' => 100, 'min' => 20],
            ['nama' => 'Kapas Bulat (Cotton Ball)',          'kategori' => 'Perawatan Luka', 'satuan' => 'pak', 'pokok' => 8000,   'jual' => 14000,  'stok' => 100, 'min' => 20],
            ['nama' => 'Lidi Waten (Cotton Bud)',            'kategori' => 'Perawatan Luka', 'satuan' => 'pak', 'pokok' => 5000,   'jual' => 9000,   'stok' => 100, 'min' => 20],
            ['nama' => 'Betadine Solution 60ml',             'kategori' => 'Antiseptik',     'satuan' => 'botol','pokok' => 9000,  'jual' => 16000,  'stok' => 80,  'min' => 15],
            ['nama' => 'Alkohol 70% 100ml',                  'kategori' => 'Antiseptik',     'satuan' => 'botol','pokok' => 6000,  'jual' => 11000,  'stok' => 80,  'min' => 15],
            ['nama' => 'Hand Sanitizer 100ml',               'kategori' => 'Antiseptik',     'satuan' => 'botol','pokok' => 8000,  'jual' => 15000,  'stok' => 80,  'min' => 15],
            ['nama' => 'Sarung Tangan Steril No. 7 (Pair)',  'kategori' => 'APD',            'satuan' => 'pasang','pokok' => 4000, 'jual' => 7000,   'stok' => 100, 'min' => 20],
            ['nama' => 'Sarung Tangan Non-Steril (Nitrile)', 'kategori' => 'APD',            'satuan' => 'box', 'pokok' => 45000,  'jual' => 75000,  'stok' => 60,  'min' => 10],
            ['nama' => 'Masker Bedah 3-Ply',                 'kategori' => 'APD',            'satuan' => 'box', 'pokok' => 25000,  'jual' => 40000,  'stok' => 80,  'min' => 15],
            ['nama' => 'Masker N95',                         'kategori' => 'APD',            'satuan' => 'pcs', 'pokok' => 8000,   'jual' => 15000,  'stok' => 60,  'min' => 10],
            ['nama' => 'Apron Plastik Sekali Pakai',         'kategori' => 'APD',            'satuan' => 'pcs', 'pokok' => 5000,   'jual' => 9000,   'stok' => 60,  'min' => 10],
            ['nama' => 'Face Shield',                        'kategori' => 'APD',            'satuan' => 'pcs', 'pokok' => 7000,   'jual' => 13000,  'stok' => 50,  'min' => 10],
            ['nama' => 'Tongue Spatel Kayu',                 'kategori' => 'Diagnostik',     'satuan' => 'pak', 'pokok' => 7000,   'jual' => 12000,  'stok' => 100, 'min' => 20],
            ['nama' => 'Termometer Digital',                 'kategori' => 'Diagnostik',     'satuan' => 'pcs', 'pokok' => 25000,  'jual' => 45000,  'stok' => 30,  'min' => 5],
            ['nama' => 'Tensimeter Digital (Sphygmomanometer)','kategori'=>'Diagnostik',      'satuan' => 'pcs', 'pokok' => 180000, 'jual' => 280000, 'stok' => 10,  'min' => 3],
            ['nama' => 'Stetoskop',                          'kategori' => 'Diagnostik',     'satuan' => 'pcs', 'pokok' => 120000, 'jual' => 200000, 'stok' => 10,  'min' => 3],
            ['nama' => 'Pulse Oximeter',                     'kategori' => 'Diagnostik',     'satuan' => 'pcs', 'pokok' => 90000,  'jual' => 150000, 'stok' => 15,  'min' => 5],
            ['nama' => 'Strip Gula Darah (Glucose Test Strip)','kategori'=>'Diagnostik',     'satuan' => 'box', 'pokok' => 80000,  'jual' => 130000, 'stok' => 40,  'min' => 10],
            ['nama' => 'Lancet (Jarum Cek Gula Darah)',      'kategori' => 'Diagnostik',     'satuan' => 'box', 'pokok' => 25000,  'jual' => 40000,  'stok' => 40,  'min' => 10],
            ['nama' => 'Elektroda EKG (EKG Pad)',             'kategori' => 'Diagnostik',     'satuan' => 'box', 'pokok' => 35000,  'jual' => 60000,  'stok' => 30,  'min' => 5],
            ['nama' => 'Kateter Urine (Folley Catheter) 16Fr','kategori'=>'Kateter & Drainase','satuan' => 'pcs', 'pokok' => 15000, 'jual' => 25000,  'stok' => 30,  'min' => 5],
            ['nama' => 'Urine Bag (Kantong Penampung Urine)','kategori' => 'Kateter & Drainase','satuan' => 'pcs', 'pokok' => 8000, 'jual' => 14000,  'stok' => 40,  'min' => 10],
            ['nama' => 'NGT (Nasogastric Tube) 16Fr',        'kategori' => 'Kateter & Drainase','satuan' => 'pcs', 'pokok' => 12000, 'jual' => 20000,  'stok' => 30,  'min' => 5],
            ['nama' => 'Surgical Blade No. 11',              'kategori' => 'Bedah Minor',    'satuan' => 'pcs', 'pokok' => 2500,   'jual' => 4500,   'stok' => 60,  'min' => 15],
            ['nama' => 'Surgical Blade No. 15',              'kategori' => 'Bedah Minor',    'satuan' => 'pcs', 'pokok' => 2500,   'jual' => 4500,   'stok' => 60,  'min' => 15],
            ['nama' => 'Benang Jahit Silk 3.0',              'kategori' => 'Bedah Minor',    'satuan' => 'pcs', 'pokok' => 15000,  'jual' => 25000,  'stok' => 30,  'min' => 10],
            ['nama' => 'Benang Jahit Catgut 2.0',            'kategori' => 'Bedah Minor',    'satuan' => 'pcs', 'pokok' => 15000,  'jual' => 25000,  'stok' => 30,  'min' => 10],
            ['nama' => 'Masker Nebulizer Dewasa',            'kategori' => 'Respirasi',      'satuan' => 'pcs', 'pokok' => 12000,  'jual' => 20000,  'stok' => 40,  'min' => 10],
            ['nama' => 'Masker Nebulizer Anak',              'kategori' => 'Respirasi',      'satuan' => 'pcs', 'pokok' => 12000,  'jual' => 20000,  'stok' => 40,  'min' => 10],
            ['nama' => 'Nasal Kanul Oksigen',                'kategori' => 'Respirasi',      'satuan' => 'pcs', 'pokok' => 8000,   'jual' => 14000,  'stok' => 40,  'min' => 10],
        ];

        $i = 0;
        foreach ($alkes as $a) {
            $i++;
            $kode = 'ALK-' . str_pad($i, 3, '0', STR_PAD_LEFT);

            Barang::firstOrCreate(
                ['kode' => $kode],
                [
                    'nama'         => $a['nama'],
                    'jenis'        => 'alkes',
                    'kategori'     => $a['kategori'],
                    'satuan'       => $a['satuan'],
                    'stok'         => $a['stok'],
                    'stok_minimum' => $a['min'],
                    'harga_pokok'  => $a['pokok'],
                    'harga_jual'   => $a['jual'],
                    'butuh_resep'  => false,
                    'is_paten'     => false,
                    'is_active'    => true,
                ]
            );

            $this->command->info("✓ [{$i}/" . count($alkes) . "] {$kode} — {$a['nama']}");
        }

        $this->command->info('✅ AlkesSeeder selesai (' . count($alkes) . ' item alkes ditambahkan).');
    }
}

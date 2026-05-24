<?php

namespace Database\Seeders;

use App\Models\ItemPenunjang;
use Illuminate\Database\Seeder;

class PenunjangSeeder extends Seeder
{
    public function run(): void
    {
        $lab = [
            ['kode' => 'LAB001', 'nama' => 'Darah Rutin (Complete Blood Count)',        'tarif' => 45000,  'tarif_bpjs' => 35000],
            ['kode' => 'LAB002', 'nama' => 'Gula Darah Sewaktu (GDS)',                  'tarif' => 20000,  'tarif_bpjs' => 15000],
            ['kode' => 'LAB003', 'nama' => 'Gula Darah Puasa (GDP)',                    'tarif' => 20000,  'tarif_bpjs' => 15000],
            ['kode' => 'LAB004', 'nama' => 'HbA1c',                                     'tarif' => 85000,  'tarif_bpjs' => 70000],
            ['kode' => 'LAB005', 'nama' => 'Kolesterol Total',                           'tarif' => 30000,  'tarif_bpjs' => 25000],
            ['kode' => 'LAB006', 'nama' => 'Trigliserida',                               'tarif' => 35000,  'tarif_bpjs' => 28000],
            ['kode' => 'LAB007', 'nama' => 'HDL Kolesterol',                             'tarif' => 35000,  'tarif_bpjs' => 28000],
            ['kode' => 'LAB008', 'nama' => 'LDL Kolesterol',                             'tarif' => 35000,  'tarif_bpjs' => 28000],
            ['kode' => 'LAB009', 'nama' => 'Profil Lipid Lengkap',                       'tarif' => 120000, 'tarif_bpjs' => 95000],
            ['kode' => 'LAB010', 'nama' => 'Asam Urat',                                  'tarif' => 30000,  'tarif_bpjs' => 25000],
            ['kode' => 'LAB011', 'nama' => 'Kreatinin',                                  'tarif' => 30000,  'tarif_bpjs' => 25000],
            ['kode' => 'LAB012', 'nama' => 'Ureum / BUN',                                'tarif' => 30000,  'tarif_bpjs' => 25000],
            ['kode' => 'LAB013', 'nama' => 'Fungsi Ginjal (Ureum + Kreatinin)',          'tarif' => 55000,  'tarif_bpjs' => 45000],
            ['kode' => 'LAB014', 'nama' => 'SGOT (AST)',                                 'tarif' => 30000,  'tarif_bpjs' => 25000],
            ['kode' => 'LAB015', 'nama' => 'SGPT (ALT)',                                 'tarif' => 30000,  'tarif_bpjs' => 25000],
            ['kode' => 'LAB016', 'nama' => 'Fungsi Hati Lengkap (SGOT + SGPT + GGT)',   'tarif' => 85000,  'tarif_bpjs' => 70000],
            ['kode' => 'LAB017', 'nama' => 'Bilirubin Total',                            'tarif' => 35000,  'tarif_bpjs' => 28000],
            ['kode' => 'LAB018', 'nama' => 'Urinalisis Rutin',                           'tarif' => 30000,  'tarif_bpjs' => 25000],
            ['kode' => 'LAB019', 'nama' => 'Kultur Urine',                               'tarif' => 120000, 'tarif_bpjs' => 90000],
            ['kode' => 'LAB020', 'nama' => 'Widal Test',                                 'tarif' => 45000,  'tarif_bpjs' => 35000],
            ['kode' => 'LAB021', 'nama' => 'Rapid Test Malaria',                         'tarif' => 55000,  'tarif_bpjs' => 45000],
            ['kode' => 'LAB022', 'nama' => 'Anti-HIV (Rapid)',                           'tarif' => 75000,  'tarif_bpjs' => 55000],
            ['kode' => 'LAB023', 'nama' => 'HBsAg (Rapid)',                              'tarif' => 50000,  'tarif_bpjs' => 40000],
            ['kode' => 'LAB024', 'nama' => 'Anti-HCV',                                   'tarif' => 80000,  'tarif_bpjs' => 65000],
            ['kode' => 'LAB025', 'nama' => 'TSH',                                        'tarif' => 95000,  'tarif_bpjs' => 75000],
            ['kode' => 'LAB026', 'nama' => 'FT4',                                        'tarif' => 95000,  'tarif_bpjs' => 75000],
            ['kode' => 'LAB027', 'nama' => 'Prothrombin Time (PT)',                      'tarif' => 60000,  'tarif_bpjs' => 50000],
            ['kode' => 'LAB028', 'nama' => 'APTT',                                       'tarif' => 65000,  'tarif_bpjs' => 55000],
            ['kode' => 'LAB029', 'nama' => 'D-Dimer',                                    'tarif' => 150000, 'tarif_bpjs' => 120000],
            ['kode' => 'LAB030', 'nama' => 'CRP (C-Reactive Protein)',                   'tarif' => 75000,  'tarif_bpjs' => 60000],
            ['kode' => 'LAB031', 'nama' => 'LED (Laju Endap Darah)',                     'tarif' => 25000,  'tarif_bpjs' => 20000],
            ['kode' => 'LAB032', 'nama' => 'Swab PCR COVID-19',                          'tarif' => 250000, 'tarif_bpjs' => null],
        ];

        $radiologi = [
            ['kode' => 'RAD001', 'nama' => 'Rontgen Thorax PA',                          'tarif' => 80000,  'tarif_bpjs' => 65000],
            ['kode' => 'RAD002', 'nama' => 'Rontgen Thorax AP + Lateral',                'tarif' => 110000, 'tarif_bpjs' => 90000],
            ['kode' => 'RAD003', 'nama' => 'Rontgen Abdomen 3 Posisi',                   'tarif' => 120000, 'tarif_bpjs' => 95000],
            ['kode' => 'RAD004', 'nama' => 'Rontgen Ekstremitas Superior (Tangan/Siku)', 'tarif' => 75000,  'tarif_bpjs' => 60000],
            ['kode' => 'RAD005', 'nama' => 'Rontgen Ekstremitas Inferior (Lutut/Kaki)',  'tarif' => 75000,  'tarif_bpjs' => 60000],
            ['kode' => 'RAD006', 'nama' => 'Rontgen Vertebra Lumbal (Pinggang)',         'tarif' => 90000,  'tarif_bpjs' => 75000],
            ['kode' => 'RAD007', 'nama' => 'Rontgen Vertebra Cervical (Leher)',          'tarif' => 90000,  'tarif_bpjs' => 75000],
            ['kode' => 'RAD008', 'nama' => 'Rontgen Kepala',                             'tarif' => 85000,  'tarif_bpjs' => 70000],
            ['kode' => 'RAD009', 'nama' => 'USG Abdomen Lengkap',                        'tarif' => 200000, 'tarif_bpjs' => 165000],
            ['kode' => 'RAD010', 'nama' => 'USG Abdomen Atas (Hepatobilier)',            'tarif' => 150000, 'tarif_bpjs' => 120000],
            ['kode' => 'RAD011', 'nama' => 'USG Abdomen Bawah (Vesica Urinaria)',        'tarif' => 150000, 'tarif_bpjs' => 120000],
            ['kode' => 'RAD012', 'nama' => 'USG Mammae',                                 'tarif' => 200000, 'tarif_bpjs' => 165000],
            ['kode' => 'RAD013', 'nama' => 'USG Tiroid',                                 'tarif' => 180000, 'tarif_bpjs' => 150000],
            ['kode' => 'RAD014', 'nama' => 'USG Muskuloskeletal',                        'tarif' => 180000, 'tarif_bpjs' => 150000],
            ['kode' => 'RAD015', 'nama' => 'USG Obstetri (Kehamilan)',                   'tarif' => 200000, 'tarif_bpjs' => 165000],
            ['kode' => 'RAD016', 'nama' => 'CT Scan Kepala Tanpa Kontras',               'tarif' => 750000, 'tarif_bpjs' => 600000],
            ['kode' => 'RAD017', 'nama' => 'CT Scan Kepala Dengan Kontras',              'tarif' => 950000, 'tarif_bpjs' => 750000],
            ['kode' => 'RAD018', 'nama' => 'CT Scan Thorax',                             'tarif' => 850000, 'tarif_bpjs' => 700000],
            ['kode' => 'RAD019', 'nama' => 'CT Scan Abdomen',                            'tarif' => 900000, 'tarif_bpjs' => 730000],
            ['kode' => 'RAD020', 'nama' => 'MRI Kepala Tanpa Kontras',                  'tarif' => 1500000,'tarif_bpjs' => 1200000],
            ['kode' => 'RAD021', 'nama' => 'MRI Vertebra',                               'tarif' => 1500000,'tarif_bpjs' => 1200000],
            ['kode' => 'RAD022', 'nama' => 'MRI Lutut',                                  'tarif' => 1500000,'tarif_bpjs' => 1200000],
            ['kode' => 'RAD023', 'nama' => 'Foto Panoramik Gigi',                        'tarif' => 150000, 'tarif_bpjs' => 120000],
            ['kode' => 'RAD024', 'nama' => 'EKG (12 Lead)',                              'tarif' => 60000,  'tarif_bpjs' => 50000],
        ];

        $now = now();
        $labRows = array_map(fn ($item) => array_merge($item, [
            'kategori'  => 'lab',
            'is_active' => true,
            'created_at'=> $now,
            'updated_at'=> $now,
        ]), $lab);

        $radRows = array_map(fn ($item) => array_merge($item, [
            'kategori'  => 'radiologi',
            'is_active' => true,
            'created_at'=> $now,
            'updated_at'=> $now,
        ]), $radiologi);

        ItemPenunjang::upsert(
            array_merge($labRows, $radRows),
            ['kode'],
            ['nama', 'tarif', 'tarif_bpjs', 'is_active', 'updated_at']
        );

        $total = count($labRows) + count($radRows);
        $this->command->info("✓ Item Penunjang: {$total} item ({$labRows[0]['kategori']} ".count($labRows).", radiologi ".count($radRows).")");
    }
}

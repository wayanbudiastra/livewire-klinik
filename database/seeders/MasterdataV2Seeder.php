<?php

namespace Database\Seeders;

use App\Models\ItemPenunjang;
use App\Models\MasterTindakan;
use App\Models\PeralatanMedis;
use App\Models\Poli;
use Illuminate\Database\Seeder;

class MasterdataV2Seeder extends Seeder
{
    public function run(): void
    {
        $poliMap = Poli::pluck('id', 'kode')->toArray();

        // ── Tindakan (lokal, mapping ke poli) ───────────────
        $tindakan = [
            ['kode'=>'T001','nama'=>'Pemeriksaan Fisik Umum', 'tarif'=>50000,  'poli'=>['UMUM','ANAK','KIA']],
            ['kode'=>'T002','nama'=>'Pemeriksaan Visus',      'tarif'=>75000,  'poli'=>['MATA']],
            ['kode'=>'T003','nama'=>'Tonometri',              'tarif'=>100000, 'poli'=>['MATA']],
            ['kode'=>'T004','nama'=>'Ekstraksi Gigi',         'tarif'=>150000, 'poli'=>['GIGI']],
            ['kode'=>'T005','nama'=>'Tambal Gigi Composite',  'tarif'=>200000, 'poli'=>['GIGI']],
            ['kode'=>'T006','nama'=>'Pemasangan Infus',        'tarif'=>85000,  'poli'=>['UMUM','BEDAH','ANAK','KIA']],
            ['kode'=>'T007','nama'=>'Jahit Luka',              'tarif'=>120000, 'poli'=>['UMUM','BEDAH']],
            ['kode'=>'T008','nama'=>'Sirkumsisi',              'tarif'=>500000, 'poli'=>['BEDAH']],
            ['kode'=>'T009','nama'=>'USG Obstetri',            'tarif'=>250000, 'poli'=>['KIA']],
            ['kode'=>'T010','nama'=>'Nebulisasi',              'tarif'=>60000,  'poli'=>['UMUM','ANAK']],
        ];

        foreach ($tindakan as $t) {
            $poliKodes = $t['poli'];
            unset($t['poli']);

            $item = MasterTindakan::firstOrCreate(
                ['kode' => $t['kode']],
                array_merge($t, ['kategori' => 'tindakan'])
            );

            $poliIds = collect($poliKodes)
                ->map(fn ($k) => $poliMap[$k] ?? null)
                ->filter()
                ->toArray();

            if (! empty($poliIds)) {
                $item->poli()->syncWithoutDetaching($poliIds);
            }
        }

        $this->command->info('✓ Seeded ' . count($tindakan) . ' Tindakan + mapping poli');

        // ── Item Lab (global) ────────────────────────────────
        $labs = [
            ['kode'=>'L001','nama'=>'Darah Lengkap',          'tarif'=>85000,  'satuan_waktu'=>'2 jam'],
            ['kode'=>'L002','nama'=>'Urinalisis',             'tarif'=>45000,  'satuan_waktu'=>'1 jam'],
            ['kode'=>'L003','nama'=>'Gula Darah Sewaktu',     'tarif'=>30000,  'satuan_waktu'=>'30 menit'],
            ['kode'=>'L004','nama'=>'HbA1C',                  'tarif'=>120000, 'satuan_waktu'=>'3 jam'],
            ['kode'=>'L005','nama'=>'Fungsi Ginjal',          'tarif'=>95000,  'satuan_waktu'=>'2 jam'],
            ['kode'=>'L006','nama'=>'Fungsi Hati SGOT/SGPT',  'tarif'=>95000,  'satuan_waktu'=>'2 jam'],
            ['kode'=>'L007','nama'=>'Profil Lipid',           'tarif'=>110000, 'satuan_waktu'=>'3 jam'],
            ['kode'=>'L008','nama'=>'Kultur Darah',           'tarif'=>250000, 'satuan_waktu'=>'5 hari kerja'],
        ];

        foreach ($labs as $l) {
            ItemPenunjang::firstOrCreate(['kode' => $l['kode']], array_merge($l, ['kategori' => 'lab']));
        }

        $this->command->info('✓ Seeded ' . count($labs) . ' Item Lab');

        // ── Item Radiologi (global) ──────────────────────────
        $rads = [
            ['kode'=>'R001','nama'=>'Foto Thorax PA',       'tarif'=>150000,  'satuan_waktu'=>'1 jam'],
            ['kode'=>'R002','nama'=>'USG Abdomen',          'tarif'=>300000,  'satuan_waktu'=>'30 menit'],
            ['kode'=>'R003','nama'=>'CT-Scan Kepala',       'tarif'=>900000,  'satuan_waktu'=>'2 jam'],
            ['kode'=>'R004','nama'=>'MRI Lumbal',           'tarif'=>2500000, 'satuan_waktu'=>'2 jam'],
            ['kode'=>'R005','nama'=>'EKG 12 Lead',          'tarif'=>120000,  'satuan_waktu'=>'30 menit'],
            ['kode'=>'R006','nama'=>'Foto Panoramik Gigi',  'tarif'=>200000,  'satuan_waktu'=>'30 menit'],
        ];

        foreach ($rads as $r) {
            ItemPenunjang::firstOrCreate(['kode' => $r['kode']], array_merge($r, ['kategori' => 'radiologi']));
        }

        $this->command->info('✓ Seeded ' . count($rads) . ' Item Radiologi');

        // ── Peralatan Medis (global) ─────────────────────────
        $peralatan = [
            ['kode'=>'A001','nama'=>'Oxymeter',           'merk'=>'Contec',       'nomor_seri'=>'CX8001'],
            ['kode'=>'A002','nama'=>'Tensimeter Digital',  'merk'=>'Omron',        'nomor_seri'=>'OM7200'],
            ['kode'=>'A003','nama'=>'Nebulizer',           'merk'=>'Omron',        'nomor_seri'=>'NEB001'],
            ['kode'=>'A004','nama'=>'ECG Monitor 12 Lead', 'merk'=>'GE Healthcare','nomor_seri'=>'GE1200'],
            ['kode'=>'A005','nama'=>'Glucometer',          'merk'=>'Accu-Check',   'nomor_seri'=>'AC4500'],
            ['kode'=>'A006','nama'=>'Infusion Pump',       'merk'=>'Terumo',       'nomor_seri'=>'TE2200'],
        ];

        foreach ($peralatan as $p) {
            PeralatanMedis::firstOrCreate(['kode' => $p['kode']], $p);
        }

        $this->command->info('✓ Seeded ' . count($peralatan) . ' Peralatan Medis');
        $this->command->info('✅ MasterdataV2Seeder selesai.');
    }
}

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Isi kolom icd10.kategori dengan nama bab (chapter) resmi ICD-10 WHO,
 * berdasarkan rentang kode -- lihat prd/seeder_icd10_who_resmi.md FR-4.
 *
 * Pemetaan 22 bab ini murni berdasarkan rentang kode (A00-B99, dst), yang
 * sudah stabil & baku sejak ICD-10 dipublikasikan WHO -- tidak butuh akses
 * live ke WHO untuk memvalidasi pemetaan ini, beda dengan nama_en per-kode
 * yang butuh QA sampling (§9).
 */
class Icd10KategoriBackfill extends Command
{
    protected $signature = 'icd:kategori-backfill
                            {--only-missing : Cuma isi baris yang kategori-nya masih kosong, jangan timpa yang sudah ada}
                            {--dry-run      : Tampilkan ringkasan tanpa menulis ke database}';

    protected $description = 'Isi ulang icd10.kategori dengan nama bab resmi WHO ICD-10 berdasarkan rentang kode';

    /** @var array<int, array{0:string,1:string}> Rentang kode per bab, urut sesuai bab resmi WHO. */
    private const BAB = [
        ['A00', 'B99', 'I Certain infectious and parasitic diseases (A00-B99)'],
        ['C00', 'D48', 'II Neoplasms (C00-D48)'],
        ['D50', 'D89', 'III Diseases of the blood and blood-forming organs and certain disorders involving the immune mechanism (D50-D89)'],
        ['E00', 'E90', 'IV Endocrine, nutritional and metabolic diseases (E00-E90)'],
        ['F00', 'F99', 'V Mental and behavioural disorders (F00-F99)'],
        ['G00', 'G99', 'VI Diseases of the nervous system (G00-G99)'],
        ['H00', 'H59', 'VII Diseases of the eye and adnexa (H00-H59)'],
        ['H60', 'H95', 'VIII Diseases of the ear and mastoid process (H60-H95)'],
        ['I00', 'I99', 'IX Diseases of the circulatory system (I00-I99)'],
        ['J00', 'J99', 'X Diseases of the respiratory system (J00-J99)'],
        ['K00', 'K93', 'XI Diseases of the digestive system (K00-K93)'],
        ['L00', 'L99', 'XII Diseases of the skin and subcutaneous tissue (L00-L99)'],
        ['M00', 'M99', 'XIII Diseases of the musculoskeletal system and connective tissue (M00-M99)'],
        ['N00', 'N99', 'XIV Diseases of the genitourinary system (N00-N99)'],
        ['O00', 'O99', 'XV Pregnancy, childbirth and the puerperium (O00-O99)'],
        ['P00', 'P96', 'XVI Certain conditions originating in the perinatal period (P00-P96)'],
        ['Q00', 'Q99', 'XVII Congenital malformations, deformations and chromosomal abnormalities (Q00-Q99)'],
        ['R00', 'R99', 'XVIII Symptoms, signs and abnormal clinical and laboratory findings, not elsewhere classified (R00-R99)'],
        ['S00', 'T98', 'XIX Injury, poisoning and certain other consequences of external causes (S00-T98)'],
        ['V01', 'Y98', 'XX External causes of morbidity and mortality (V01-Y98)'],
        ['Z00', 'Z99', 'XXI Factors influencing health status and contact with health services (Z00-Z99)'],
        ['U00', 'U99', 'XXII Codes for special purposes (U00-U99)'],
    ];

    public function handle(): int
    {
        $onlyMissing = (bool) $this->option('only-missing');
        $dryRun      = (bool) $this->option('dry-run');

        $query = DB::table('icd10');
        if ($onlyMissing) {
            $query->where(function ($q) {
                $q->whereNull('kategori')->orWhere('kategori', '');
            });
        }

        $rows  = $query->get(['id', 'kode', 'kategori']);
        $total = $rows->count();
        $this->info("Baris yang diproses: {$total}" . ($onlyMissing ? ' (hanya yang kategori masih kosong)' : ' (seluruh baris)'));

        $ringkasan = [];
        $tidakDikenali = [];
        $berubah = 0;
        $updates = [];

        foreach ($rows as $row) {
            $bab = $this->tentukanBab($row->kode);

            if ($bab === null) {
                $tidakDikenali[] = $row->kode;
                continue;
            }

            $ringkasan[$bab] = ($ringkasan[$bab] ?? 0) + 1;

            if ($row->kategori !== $bab) {
                $berubah++;
                $updates[] = ['id' => $row->id, 'kategori' => $bab];
            }
        }

        $this->newLine();
        $this->table(
            ['Bab WHO', 'Jumlah Kode'],
            collect($ringkasan)->map(fn ($jumlah, $bab) => [$bab, $jumlah])->values()
        );

        if (!empty($tidakDikenali)) {
            $this->warn(count($tidakDikenali) . ' kode tidak cocok pola bab manapun (format tidak standar), dilewati:');
            $this->line(implode(', ', array_slice($tidakDikenali, 0, 20)) . (count($tidakDikenali) > 20 ? ' ...' : ''));
        }

        $this->newLine();
        $this->info("Baris yang kategori-nya akan berubah: {$berubah}");

        if ($dryRun) {
            $this->warn("DRY RUN -- tidak ada perubahan ditulis ke database.");
            return self::SUCCESS;
        }

        if ($berubah === 0) {
            $this->info("Tidak ada yang perlu diperbarui.");
            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar(count($updates));
        $bar->start();
        foreach (array_chunk($updates, 500) as $chunk) {
            foreach ($chunk as $u) {
                DB::table('icd10')->where('id', $u['id'])->update(['kategori' => $u['kategori']]);
                $bar->advance();
            }
        }
        $bar->finish();
        $this->newLine();

        $this->info("✓ Kategori berhasil diperbarui untuk {$berubah} baris.");
        return self::SUCCESS;
    }

    private function tentukanBab(string $kode): ?string
    {
        $kode = strtoupper(trim($kode));

        if (!preg_match('/^([A-Z]\d{2})/', $kode, $m)) {
            return null;
        }
        $prefix3 = $m[1]; // mis. "A09", "I48" dari "A09" atau "I48.9"

        foreach (self::BAB as [$awal, $akhir, $namaBab]) {
            if ($prefix3 >= $awal && $prefix3 <= $akhir) {
                return $namaBab;
            }
        }

        return null;
    }
}

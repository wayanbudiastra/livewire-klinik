<?php

namespace App\Console\Commands;

use App\Models\Icd10ImportLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Isi kolom icd10.kategori dengan kategori level BLOK (bukan cuma bab),
 * berdasarkan referensi di database/seeders/data/icd10_blok_who.csv --
 * lihat prd/seeder_icd10_who_resmi.md FR-4 (update: diganti ke granularitas
 * blok atas permintaan user, sebelumnya cuma 22 kategori bab).
 *
 * Sumber CSV itu strukturnya ICD-10-CM (ejaan Amerika, ada kode rentang
 * seperti I1A/J4A/QA0 yang tidak ada di WHO ICD-10 murni) -- SENGAJA dipakai
 * cuma sebagai teks pengelompokan/kategori, BUKAN pengganti nama_en per-kode
 * yang sudah diverifikasi terpisah (lihat Icd10KoreksiManual.php). Keputusan
 * ini dikonfirmasi user secara eksplisit.
 */
class Icd10KategoriBackfill extends Command
{
    protected $signature = 'icd:kategori-backfill
                            {--only-missing : Cuma isi baris yang kategori-nya masih kosong, jangan timpa yang sudah ada}
                            {--dry-run      : Tampilkan ringkasan tanpa menulis ke database}
                            {--file=        : Path ke CSV sumber blok (default: database/seeders/data/icd10_blok_who.csv)}';

    protected $description = 'Isi ulang icd10.kategori dengan kategori level blok dari database/seeders/data/icd10_blok_who.csv';

    /** @var array<int, array{0:string,1:string,2:string,3:string}> Blok [awal, akhir, nama, bab]. */
    private array $blok = [];

    /** @var array<int, array{0:string,1:string,2:string,3:string}> Bab (fallback kalau tidak ada blok yang cocok). */
    private array $bab = [];

    public function handle(): int
    {
        $onlyMissing = (bool) $this->option('only-missing');
        $dryRun      = (bool) $this->option('dry-run');
        $file        = $this->option('file') ?: database_path('seeders/data/icd10_blok_who.csv');

        if (!file_exists($file)) {
            $this->error("File sumber tidak ditemukan: {$file}");
            return self::FAILURE;
        }

        $this->muatCsv($file);
        $this->info('Referensi dimuat: ' . count($this->blok) . ' blok, ' . count($this->bab) . ' bab, dari ' . basename($file));

        $query = DB::table('icd10');
        if ($onlyMissing) {
            $query->where(function ($q) {
                $q->whereNull('kategori')->orWhere('kategori', '');
            });
        }

        $rows  = $query->get(['id', 'kode', 'kategori']);
        $total = $rows->count();
        $this->info("Baris yang diproses: {$total}" . ($onlyMissing ? ' (hanya yang kategori masih kosong)' : ' (seluruh baris)'));

        $pakaiBlok    = 0;
        $pakaiBabSaja = 0;
        $tidakDikenali = [];
        $berubah = 0;
        $updates = [];
        $ringkasan = [];

        foreach ($rows as $row) {
            [$kategori, $sumberLevel] = $this->tentukanKategori($row->kode);

            if ($kategori === null) {
                $tidakDikenali[] = $row->kode;
                continue;
            }

            if ($sumberLevel === 'blok') $pakaiBlok++;
            else $pakaiBabSaja++;

            $ringkasan[$kategori] = ($ringkasan[$kategori] ?? 0) + 1;

            if ($row->kategori !== $kategori) {
                $berubah++;
                $updates[] = ['id' => $row->id, 'kategori' => $kategori];
            }
        }

        $this->newLine();
        $this->info('Jumlah kategori blok unik yang dipakai: ' . count($ringkasan));
        $this->table(
            ['Kategori (contoh 15 pertama)', 'Jumlah Kode'],
            collect($ringkasan)->take(15)->map(fn ($jumlah, $kat) => [$kat, $jumlah])->values()
        );
        if (count($ringkasan) > 15) {
            $this->line('... dan ' . (count($ringkasan) - 15) . ' kategori lainnya.');
        }

        $this->newLine();
        $this->info("Kode yang cocok level blok (spesifik): {$pakaiBlok}");
        $this->info("Kode yang cuma cocok level bab (fallback, tidak ada blok pas): {$pakaiBabSaja}");

        if (!empty($tidakDikenali)) {
            $this->warn(count($tidakDikenali) . ' kode tidak cocok pola manapun (format tidak standar), dilewati:');
            $this->line(implode(', ', array_slice($tidakDikenali, 0, 20)) . (count($tidakDikenali) > 20 ? ' ...' : ''));
        }

        $this->newLine();
        $this->info("Baris yang kategori-nya akan berubah: {$berubah}");

        if ($dryRun) {
            $this->warn('DRY RUN -- tidak ada perubahan ditulis ke database.');
            return self::SUCCESS;
        }

        if ($berubah > 0) {
            $bar = $this->output->createProgressBar(count($updates));
            $bar->start();
            foreach ($updates as $u) {
                DB::table('icd10')->where('id', $u['id'])->update(['kategori' => $u['kategori']]);
                $bar->advance();
            }
            $bar->finish();
            $this->newLine();
        }

        Icd10ImportLog::create([
            'sumber'            => 'database/seeders/data/icd10_blok_who.csv (kategori level blok, sumber ICD-10-CM -- dipakai murni sebagai label grouping, bukan nama_en per-kode)',
            'sumber_url'        => null,
            'versi_who'         => 'N/A -- kategori/grouping saja, bukan klaim akurasi WHO per-kode',
            'mode'              => 'kategori-backfill-blok',
            'jumlah_baris'      => $berubah,
            'jumlah_baru'       => 0,
            'jumlah_diperbarui' => $berubah,
            'catatan_qa'        => "Kategori diganti dari level bab (22) ke level blok ({$pakaiBlok} kode blok-spesifik, {$pakaiBabSaja} fallback bab, " . count($tidakDikenali) . ' tidak dikenali).',
            'dijalankan_oleh'   => auth()->id(),
        ]);

        $this->info("✓ Kategori berhasil diperbarui untuk {$berubah} baris. Tercatat di icd10_import_log.");
        return self::SUCCESS;
    }

    private function muatCsv(string $file): void
    {
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        array_shift($lines); // buang header "Chapter,Code Range,Category Name,Description"

        $babTerlihat = [];

        foreach ($lines as $line) {
            $parts = explode(',', $line, 4);
            if (count($parts) < 3) continue;

            [$bab, $range, $nama] = $parts;
            $bab = trim($bab);
            $range = trim($range);
            $nama = trim($nama);

            if (!str_contains($range, '-')) continue;
            [$awal, $akhir] = explode('-', $range, 2);

            $entri = [$awal, $akhir, $nama, $bab];

            // Baris PERTAMA untuk tiap bab = ringkasan level bab (rentang lebar,
            // mis. A00-B99) -- dipakai sebagai fallback, bukan target utama.
            if (!isset($babTerlihat[$bab])) {
                $babTerlihat[$bab] = true;
                $this->bab[] = $entri;
                continue;
            }

            $this->blok[] = $entri;
        }
    }

    /** @return array{0: ?string, 1: string} [kategori terformat, 'blok'|'bab'] */
    private function tentukanKategori(string $kode): array
    {
        $kode = strtoupper(trim($kode));

        if (!preg_match('/^([A-Z]\d{2})/', $kode, $m)) {
            return [null, ''];
        }
        $prefix3 = $m[1];

        foreach ($this->blok as [$awal, $akhir, $nama, $bab]) {
            if ($prefix3 >= $awal && $prefix3 <= $akhir) {
                return ["{$bab} — {$nama} ({$awal}-{$akhir})", 'blok'];
            }
        }

        foreach ($this->bab as [$awal, $akhir, $nama, $bab]) {
            if ($prefix3 >= $awal && $prefix3 <= $akhir) {
                return ["{$bab} — {$nama} ({$awal}-{$akhir})", 'bab'];
            }
        }

        return [null, ''];
    }
}

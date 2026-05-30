<?php

namespace App\Console\Commands;

use App\Models\Klinik;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportIcd10 extends Command
{
    protected $signature = 'icd:import
                            {--lang=id        : Bahasa aktif: id (Indonesia), en (English), both (simpan keduanya, aktif id)}
                            {--mode=upsert    : Mode import: upsert (tambah/perbarui) atau replace (hapus semua dulu)}
                            {--file=          : Path ke file JSON (default: base_path/master_icd_x.json)}';

    protected $description = 'Import data ICD-10 dari file master_icd_x.json ke database';

    public function handle(): int
    {
        $lang = $this->option('lang');
        $mode = $this->option('mode');
        $file = $this->option('file') ?: base_path('master_icd_x.json');

        if (!in_array($lang, ['id', 'en', 'both'])) {
            $this->error("Opsi --lang tidak valid. Gunakan: id, en, atau both.");
            return self::FAILURE;
        }

        if (!in_array($mode, ['upsert', 'replace'])) {
            $this->error("Opsi --mode tidak valid. Gunakan: upsert atau replace.");
            return self::FAILURE;
        }

        // ── Validasi file ──────────────────────────────────────────
        if (!file_exists($file)) {
            $this->error("File tidak ditemukan: {$file}");
            $this->line("Letakkan file master_icd_x.json di root project atau tentukan path dengan --file=");
            return self::FAILURE;
        }

        $this->info("Membaca file: {$file}");
        $raw  = file_get_contents($file);
        $data = json_decode($raw, true);

        if (!is_array($data) || empty($data)) {
            $this->error("File JSON kosong atau format tidak valid.");
            return self::FAILURE;
        }

        $total = count($data);
        $this->info("Total kode ditemukan : {$total}");
        $this->info("Bahasa aktif         : {$lang}");
        $this->info("Mode import          : {$mode}");
        $this->newLine();

        // ── Replace mode ───────────────────────────────────────────
        if ($mode === 'replace') {
            if (!$this->confirm("Mode REPLACE akan menghapus seluruh data ICD-10 yang ada. Lanjutkan?", true)) {
                $this->warn("Import dibatalkan.");
                return self::SUCCESS;
            }
            $this->warn("Menghapus data lama...");
            DB::table('icd10')->truncate();
        }

        // ── Proses import ──────────────────────────────────────────
        $bar = $this->output->createProgressBar($total);
        $bar->setFormat(" %current%/%max% [%bar%] %percent:3s%% — %message%");
        $bar->setMessage('Memulai...');
        $bar->start();

        $imported = 0;
        $skipped  = 0;
        $batch    = [];
        $now      = now();

        foreach ($data as $row) {
            $kode   = strtoupper(trim($row['kode_icd']      ?? ''));
            $namaEn = trim($row['nama_icd']       ?? '');
            $namaId = trim($row['nama_icd_indo']  ?? '');

            if ($kode === '' || ($namaEn === '' && $namaId === '')) {
                $skipped++;
                $bar->advance();
                continue;
            }

            $namaAktif = match ($lang) {
                'en'    => $namaEn ?: $namaId,
                default => $namaId ?: $namaEn,   // 'id' atau 'both'
            };

            $batch[] = [
                'kode'       => $kode,
                'nama'       => $namaAktif,
                'nama_en'    => $namaEn ?: null,
                'nama_id'    => $namaId ?: null,
                'kategori'   => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $imported++;

            if (count($batch) >= 500) {
                DB::table('icd10')->upsert($batch, ['kode'], ['nama', 'nama_en', 'nama_id', 'kategori', 'updated_at']);
                $bar->setMessage("Memproses batch...");
                $bar->advance(count($batch));
                $batch = [];
            }
        }

        // Sisa batch
        if (!empty($batch)) {
            DB::table('icd10')->upsert($batch, ['kode'], ['nama', 'nama_en', 'nama_id', 'kategori', 'updated_at']);
            $bar->advance(count($batch));
        }

        $bar->setMessage("Selesai.");
        $bar->finish();
        $this->newLine(2);

        // ── Simpan setting bahasa ke klinik ────────────────────────
        $bahasaSetting = ($lang === 'en') ? 'en' : 'id';
        $klinik = Klinik::first();
        if ($klinik) {
            $klinik->update(['bahasa_icd' => $bahasaSetting]);
        }

        // ── Ringkasan ──────────────────────────────────────────────
        $this->table(
            ['Keterangan', 'Jumlah'],
            [
                ['Total data di file',    $total],
                ['Berhasil diimpor',      $imported],
                ['Dilewati (data kosong)', $skipped],
                ['Bahasa aktif',          $bahasaSetting === 'id' ? 'Indonesia' : 'International (EN)'],
                ['Total di database',     DB::table('icd10')->count()],
            ]
        );

        $this->info("✓ Import ICD-10 selesai.");
        return self::SUCCESS;
    }
}

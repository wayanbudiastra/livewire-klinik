<?php

namespace App\Console\Commands;

use App\Models\Icd10ImportLog;
use App\Models\Klinik;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportIcd10 extends Command
{
    protected $signature = 'icd:import
                            {--lang=id        : Bahasa aktif: id (Indonesia), en (English), both (simpan keduanya, aktif id)}
                            {--mode=upsert    : Mode import: upsert (tambah/perbarui) atau replace (hapus semua dulu)}
                            {--file=          : Path ke file JSON (default: base_path/master_icd_x.json)}
                            {--sumber=        : Nama sumber data (wajib kalau akan menimpa nama_en) -- lihat prd/seeder_icd10_who_resmi.md §5}
                            {--sumber-url=    : URL/referensi sumber (opsional)}
                            {--versi=         : Versi/edisi WHO yang dirujuk (wajib kalau akan menimpa nama_en)}
                            {--catatan-qa=    : Catatan hasil QA sampling (opsional, lihat §9)}
                            {--dry-run        : Tampilkan ringkasan perubahan tanpa menulis ke database}
                            {--set-bahasa     : Ikut ubah klinik.bahasa_icd sesuai --lang (default: tidak diubah otomatis)}';

    protected $description = 'Import data ICD-10 dari file master_icd_x.json ke database';

    public function handle(): int
    {
        $lang        = $this->option('lang');
        $mode        = $this->option('mode');
        $file        = $this->option('file') ?: base_path('master_icd_x.json');
        $sumber      = $this->option('sumber');
        $sumberUrl   = $this->option('sumber-url');
        $versi       = $this->option('versi');
        $catatanQa   = $this->option('catatan-qa');
        $dryRun      = (bool) $this->option('dry-run');
        $setBahasa   = (bool) $this->option('set-bahasa');

        if (!in_array($lang, ['id', 'en', 'both'])) {
            $this->error("Opsi --lang tidak valid. Gunakan: id, en, atau both.");
            return self::FAILURE;
        }

        if (!in_array($mode, ['upsert', 'replace'])) {
            $this->error("Opsi --mode tidak valid. Gunakan: upsert atau replace.");
            return self::FAILURE;
        }

        // ── Wajib isi sumber/versi kalau akan menimpa nama_en (bukan dry-run) ──
        // Lihat prd/seeder_icd10_who_resmi.md FR-2: jangan sampai data ICD-10
        // (yang dipakai langsung di dokumen Resume Medis bilingual) berubah
        // tanpa jejak dari mana asalnya -- masalah yang sama persis dengan
        // kondisi as-is sebelum PRD ini ditulis.
        if (!$dryRun && (empty($sumber) || empty($versi))) {
            $this->error("--sumber dan --versi wajib diisi supaya perubahan nama_en tercatat jelas asalnya (lihat prd/seeder_icd10_who_resmi.md §5).");
            $this->line("Contoh: php artisan icd:import --sumber=\"WHO ICD-10 Volume 1 (2019 edition)\" --versi=2019");
            $this->line("Atau jalankan dulu dengan --dry-run untuk lihat ringkasan tanpa perlu isi sumber/versi.");
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
        $this->info("Mode import          : {$mode}" . ($dryRun ? ' (DRY RUN -- tidak menulis ke DB)' : ''));
        $this->newLine();

        // ── Replace mode ───────────────────────────────────────────
        if ($mode === 'replace' && !$dryRun) {
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
        $baru     = 0;
        $diperbarui = 0;
        $batch    = [];
        $now      = now();
        $existing = DB::table('icd10')->pluck('nama_en', 'kode')->all();
        $diffContoh = [];

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

            if (!array_key_exists($kode, $existing)) {
                $baru++;
            } elseif ($existing[$kode] !== $namaEn && count($diffContoh) < 15) {
                $diffContoh[] = [$kode, $existing[$kode] ?? '(kosong)', $namaEn];
                $diperbarui++;
            } elseif ($existing[$kode] !== $namaEn) {
                $diperbarui++;
            }

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

            if (!$dryRun && count($batch) >= 500) {
                DB::table('icd10')->upsert($batch, ['kode'], ['nama', 'nama_en', 'nama_id', 'updated_at']);
                $bar->setMessage("Memproses batch...");
                $bar->advance(count($batch));
                $batch = [];
            } elseif ($dryRun) {
                $bar->advance();
            }
        }

        // Sisa batch
        if (!$dryRun && !empty($batch)) {
            DB::table('icd10')->upsert($batch, ['kode'], ['nama', 'nama_en', 'nama_id', 'updated_at']);
            $bar->advance(count($batch));
        }

        $bar->setMessage("Selesai.");
        $bar->finish();
        $this->newLine(2);

        if ($dryRun && !empty($diffContoh)) {
            $this->line("Contoh perubahan nama_en (maks 15 ditampilkan):");
            $this->table(['Kode', 'nama_en lama', 'nama_en baru'], $diffContoh);
        }

        // ── Simpan setting bahasa ke klinik (hanya kalau diminta eksplisit) ──
        $bahasaSetting = ($lang === 'en') ? 'en' : 'id';
        if (!$dryRun && $setBahasa) {
            $klinik = Klinik::first();
            if ($klinik) {
                $klinik->update(['bahasa_icd' => $bahasaSetting]);
            }
        }

        // ── Catat ke icd10_import_log ──────────────────────────────
        if (!$dryRun) {
            Icd10ImportLog::create([
                'sumber'            => $sumber,
                'sumber_url'        => $sumberUrl,
                'versi_who'         => $versi,
                'mode'              => $mode,
                'jumlah_baris'      => $imported,
                'jumlah_baru'       => $baru,
                'jumlah_diperbarui' => $diperbarui,
                'catatan_qa'        => $catatanQa,
                'dijalankan_oleh'   => auth()->id(),
            ]);
        }

        // ── Ringkasan ──────────────────────────────────────────────
        $this->table(
            ['Keterangan', 'Jumlah'],
            [
                ['Total data di file',     $total],
                ['Berhasil diimpor',       $imported],
                ['  - baris baru',         $baru],
                ['  - baris diperbarui',   $diperbarui],
                ['Dilewati (data kosong)', $skipped],
                ['Bahasa aktif',           $bahasaSetting === 'id' ? 'Indonesia' : 'International (EN)'],
                ['bahasa_icd diubah?',     $setBahasa && !$dryRun ? 'Ya' : 'Tidak (pakai --set-bahasa kalau perlu)'],
                ['Total di database',      $dryRun ? DB::table('icd10')->count() . ' (belum berubah -- dry run)' : DB::table('icd10')->count()],
            ]
        );

        if ($dryRun) {
            $this->warn("DRY RUN -- tidak ada perubahan ditulis ke database. Jalankan tanpa --dry-run untuk eksekusi sungguhan.");
        } else {
            $this->info("✓ Import ICD-10 selesai. Tercatat di icd10_import_log.");
        }

        return self::SUCCESS;
    }
}

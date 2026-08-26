<?php

namespace App\Console\Commands;

use App\Models\Icd10ImportLog;
use App\Models\Klinik;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Ganti bahasa AKTIF ICD-10 (kolom icd10.nama yang benar-benar dipakai
 * IcdDiagnosis::search() & muncul di pencarian diagnosa SOAP Note) --
 * cuma nyalin dari nama_en/nama_id yang SUDAH ada di database, TIDAK
 * re-import dari master_icd_x.json.
 *
 * PENTING: master_icd_x.json di disk masih berisi data lama SEBELUM
 * koreksi manual (bug apostrof, dst -- lihat Icd10KoreksiManual.php).
 * Kalau ganti bahasa dilakukan lewat `icd:import --lang=en` ulang, semua
 * koreksi itu akan REGRESI balik ke rusak karena file JSON-nya sendiri
 * tidak pernah diperbarui. Command ini sengaja baca dari kolom nama_en/
 * nama_id di DB (yang sudah terkoreksi), bukan dari file.
 */
class Icd10SetBahasaAktif extends Command
{
    protected $signature = 'icd:set-bahasa-aktif
                            {bahasa : Bahasa aktif baru: id atau en}
                            {--dry-run : Tampilkan ringkasan tanpa menulis ke database}';

    protected $description = 'Ganti kolom icd10.nama aktif ke nama_en atau nama_id (dari data yang sudah ada di DB, bukan re-import file)';

    public function handle(): int
    {
        $bahasa = $this->argument('bahasa');
        $dryRun = (bool) $this->option('dry-run');

        if (!in_array($bahasa, ['id', 'en'])) {
            $this->error("Argumen bahasa harus 'id' atau 'en'.");
            return self::FAILURE;
        }

        $kolomSumber = $bahasa === 'en' ? 'nama_en' : 'nama_id';

        $total       = DB::table('icd10')->count();
        $adaSumber   = DB::table('icd10')->whereNotNull($kolomSumber)->where($kolomSumber, '!=', '')->count();
        $tidakAda    = $total - $adaSumber;
        $akanBerubah = DB::table('icd10')
            ->whereNotNull($kolomSumber)->where($kolomSumber, '!=', '')
            ->whereColumn('nama', '!=', $kolomSumber)
            ->count();

        $this->info("Bahasa aktif baru: {$bahasa} (sumber kolom: {$kolomSumber})");
        $this->info("Total baris icd10: {$total}");
        $this->info("Punya {$kolomSumber}: {$adaSumber}");
        $this->warn("TIDAK punya {$kolomSumber} (nama akan DIPERTAHANKAN apa adanya, tidak dikosongkan): {$tidakAda}");
        if ($tidakAda > 0) {
            $kode = DB::table('icd10')
                ->where(function ($q) use ($kolomSumber) {
                    $q->whereNull($kolomSumber)->orWhere($kolomSumber, '');
                })
                ->pluck('kode')->implode(', ');
            $this->line("  Kode: {$kode}");
        }
        $this->info("Baris yang kolom nama-nya akan berubah: {$akanBerubah}");

        if ($dryRun) {
            $this->warn('DRY RUN -- tidak ada perubahan ditulis ke database.');
            return self::SUCCESS;
        }

        DB::table('icd10')
            ->whereNotNull($kolomSumber)->where($kolomSumber, '!=', '')
            ->update(['nama' => DB::raw("`{$kolomSumber}`")]);

        $klinik = Klinik::first();
        if ($klinik) {
            $klinik->update(['bahasa_icd' => $bahasa]);
        }

        Icd10ImportLog::create([
            'sumber'            => "Ganti bahasa aktif ke '{$bahasa}' -- disalin dari kolom {$kolomSumber} yang sudah ada di DB (bukan re-import file, supaya tidak regresi koreksi manual sebelumnya)",
            'sumber_url'        => null,
            'versi_who'         => null,
            'mode'              => 'set-bahasa-aktif',
            'jumlah_baris'      => $akanBerubah,
            'jumlah_baru'       => 0,
            'jumlah_diperbarui' => $akanBerubah,
            'catatan_qa'        => "{$tidakAda} baris tidak punya {$kolomSumber}, nama lama dipertahankan (tidak dikosongkan): lihat log console untuk daftar kode.",
            'dijalankan_oleh'   => auth()->id(),
        ]);

        $this->info("✓ Bahasa aktif ICD-10 diganti ke '{$bahasa}'. klinik.bahasa_icd diperbarui. Tercatat di icd10_import_log.");
        return self::SUCCESS;
    }
}

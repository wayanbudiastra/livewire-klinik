<?php

namespace Tests\Feature;

use App\Models\IcdDiagnosis;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regresi untuk prd/seeder_icd10_who_resmi.md -- perluasan command icd:import,
 * command baru icd:kategori-backfill & icd:koreksi-manual.
 *
 * Pakai DatabaseTransactions -- lihat catatan yang sama di
 * SensitiveActionAuthorizationTest.php soal kenapa bukan RefreshDatabase.
 * Beberapa test di sini SENGAJA memanggil command yang jalan di atas tabel
 * icd10 penuh (bukan data isolasi) karena command-nya memang dirancang
 * begitu (tanpa filter per-baris) -- transaksi tetap menjamin rollback,
 * dan sebagian besar test di sini idempotency-check (harus 0 perubahan)
 * yang sudah pasti aman walau jalan di data penuh.
 */
class Icd10SeederWhoTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function icd_import_menolak_jalan_tanpa_sumber_dan_versi(): void
    {
        $exit = Artisan::call('icd:import', ['--lang' => 'en']);
        $this->assertSame(1, $exit, 'Command harus FAILURE (1) tanpa --sumber/--versi');
        $this->assertStringContainsString('wajib diisi', Artisan::output());
    }

    /** @test */
    public function icd_import_dry_run_tidak_menulis_ke_database(): void
    {
        $fixture = tempnam(sys_get_temp_dir(), 'icd_fixture_') . '.json';
        file_put_contents($fixture, json_encode([
            ['kode_icd' => 'ZZ99', 'nama_icd' => 'Test dry run code', 'nama_icd_indo' => 'Kode uji dry run'],
        ]));

        $jumlahSebelum = DB::table('icd10')->count();

        Artisan::call('icd:import', [
            '--file'    => $fixture,
            '--lang'    => 'en',
            '--dry-run' => true,
        ]);

        $this->assertSame($jumlahSebelum, DB::table('icd10')->count());
        $this->assertNull(DB::table('icd10')->where('kode', 'ZZ99')->first());

        @unlink($fixture);
    }

    /** @test */
    public function icd_import_dengan_sumber_versi_mencatat_ke_import_log(): void
    {
        $fixture = tempnam(sys_get_temp_dir(), 'icd_fixture_') . '.json';
        file_put_contents($fixture, json_encode([
            ['kode_icd' => 'ZZ98', 'nama_icd' => 'Another test code', 'nama_icd_indo' => 'Kode uji lain'],
        ]));

        Artisan::call('icd:import', [
            '--file'   => $fixture,
            '--lang'   => 'en',
            '--sumber' => 'Test fixture',
            '--versi'  => 'test',
        ]);

        $this->assertNotNull(DB::table('icd10')->where('kode', 'ZZ98')->first());
        $log = DB::table('icd10_import_log')->where('sumber', 'Test fixture')->latest('id')->first();
        $this->assertNotNull($log, 'Import dengan --sumber/--versi harus tercatat di icd10_import_log');
        $this->assertSame('test', $log->versi_who);

        @unlink($fixture);
    }

    /** @test */
    public function kategori_backfill_idempotent_pada_data_yang_sudah_dikoreksi(): void
    {
        // Data existing sudah di-backfill lewat icd:kategori-backfill sebelumnya --
        // jalanin lagi harus 0 perubahan (buktiin idempotent & hasil sebelumnya benar).
        Artisan::call('icd:kategori-backfill', ['--dry-run' => true]);
        $this->assertStringContainsString(
            'Baris yang kategori-nya akan berubah: 0',
            Artisan::output()
        );
    }

    /** @test */
    public function kategori_backfill_memetakan_kode_ke_bab_who_yang_benar(): void
    {
        Artisan::call('icd:kategori-backfill');

        $this->assertSame(
            'I Certain infectious and parasitic diseases (A00-B99)',
            DB::table('icd10')->where('kode', 'A09')->value('kategori')
        );
        $this->assertSame(
            'IX Diseases of the circulatory system (I00-I99)',
            DB::table('icd10')->where('kode', 'I10')->value('kategori')
        );
        $this->assertSame(
            'XXI Factors influencing health status and contact with health services (Z00-Z99)',
            DB::table('icd10')->where('kode', 'Z00.0')->value('kategori')
        );
    }

    /** @test */
    public function koreksi_manual_sudah_diterapkan_dan_idempotent(): void
    {
        // Baris eponim posesif (Parkinson's, dst) harus sudah benar sekarang.
        $this->assertSame("Parkinson's disease", DB::table('icd10')->where('kode', 'G20')->value('nama_en'));
        $this->assertSame("Down's syndrome, unspecified", DB::table('icd10')->where('kode', 'Q90.9')->value('nama_en'));
        $this->assertSame("Non-Hodgkin's lymphoma, unspecified type", DB::table('icd10')->where('kode', 'C85.9')->value('nama_en'));

        // Jalanin lagi harus 0 baris berubah (idempotent).
        Artisan::call('icd:koreksi-manual', ['--dry-run' => true]);
        $this->assertStringContainsString('Total baris nama_en diperbaiki (apostrof): 0', Artisan::output());
    }

    /** @test */
    public function pencarian_diagnosa_tetap_berfungsi_setelah_koreksi_data(): void
    {
        $hasil = IcdDiagnosis::search('Parkinson');
        $this->assertGreaterThan(0, $hasil->count());
        $this->assertTrue($hasil->contains('kode', 'G20'));
    }
}

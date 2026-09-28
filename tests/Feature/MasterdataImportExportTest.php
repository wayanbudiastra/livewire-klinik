<?php

namespace Tests\Feature;

use App\Exports\Masterdata\MasterdataTemplateExport;
use App\Livewire\Pengaturan\Masterdata\PenunjangTable;
use App\Livewire\Pengaturan\Masterdata\PeralatanTable;
use App\Livewire\Pengaturan\Masterdata\TindakanTable;
use App\Models\ItemPenunjang;
use App\Models\MasterTindakan;
use App\Models\PeralatanMedis;
use App\Models\Poli;
use App\Models\User;
use App\Services\MasterdataService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Maatwebsite\Excel\Excel as ExcelType;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

/**
 * Fitur baru: upload & download template XLS untuk Master Data Klinis
 * (Tindakan, Laboratorium, Radiologi, Peralatan Medis).
 *
 * - Download: route pengaturan.masterdata.{tindakan,penunjang,peralatan}.template,
 *   digate permission:masterdata.view.
 * - Upload: MasterdataService::importTindakan()/importPenunjang()/importPeralatan()
 *   -- SENGAJA 2 proses terpisah berdasarkan $mode ('baru'/'update'), BUKAN
 *   upsert gabungan:
 *     - mode 'baru': hanya membuat baris baru, kode yang sudah ada di DB
 *       dilewati (tidak diubah).
 *     - mode 'update': hanya mengubah baris yang kodenya sudah ada, kode
 *       yang belum terdaftar dilewati (tidak dibuat baru).
 *   Dipanggil dari TindakanTable/PenunjangTable/PeralatanTable
 *   (authorize masterdata.create), tombol "Upload Data Baru" dan
 *   "Update Data" terpisah di toolbar tiap tab, pola preview-lalu-
 *   konfirmasi sama dgn IcdManager.
 *
 * Pakai DatabaseTransactions -- bukan RefreshDatabase.
 */
class MasterdataImportExportTest extends TestCase
{
    use DatabaseTransactions;

    private function buatUserDenganRole(string $role): User
    {
        $user = User::create([
            'nama' => ucfirst($role) . ' Test ' . uniqid(),
            'email' => strtolower($role) . '-' . uniqid() . '@example.test',
            'password' => Hash::make('password'), 'is_active' => true,
        ]);
        $user->assignRole($role);
        return $user;
    }

    private function buatXlsxUpload(array $headings, array $rows, string $filename = 'data.xlsx'): UploadedFile
    {
        $bytes = Excel::raw(new MasterdataTemplateExport($headings, $rows, 'Test'), ExcelType::XLSX);
        return UploadedFile::fake()->createWithContent($filename, $bytes);
    }

    // ── Download Template ────────────────────────────────────────────

    /** @test */
    public function admin_bisa_download_ketiga_template(): void
    {
        $admin = $this->buatUserDenganRole('admin');
        $this->actingAs($admin);

        $this->get(route('pengaturan.masterdata.tindakan.template'))->assertOk();
        $this->get(route('pengaturan.masterdata.penunjang.template', 'lab'))->assertOk();
        $this->get(route('pengaturan.masterdata.penunjang.template', 'radiologi'))->assertOk();
        $this->get(route('pengaturan.masterdata.peralatan.template'))->assertOk();
    }

    /** @test */
    public function role_tanpa_masterdata_view_ditolak_download_template(): void
    {
        // 'keuangan' tidak punya masterdata.view.
        $keuangan = $this->buatUserDenganRole('keuangan');
        $this->actingAs($keuangan);

        $this->get(route('pengaturan.masterdata.tindakan.template'))->assertForbidden();
    }

    // ── Import Tindakan ───────────────────────────────────────────────

    /** @test */
    public function import_tindakan_mode_baru_hanya_membuat_baris_baru_kode_lama_dilewati(): void
    {
        $poli = Poli::create(['nama' => 'Umum Test', 'kode' => 'UMUMTEST', 'is_active' => true]);
        $existing = MasterTindakan::create([
            'kode' => 'TX-OLD', 'nama' => 'Nama Lama', 'tarif' => 10000, 'kategori' => 'tindakan', 'is_active' => true,
        ]);

        $rows = [
            ['TX-OLD', 'Nama Yang Dicoba Diubah', '', 99999, '', '', 'UMUMTEST', 'Y'], // kode sudah ada -> dilewati
            ['TX-NEW', 'Tindakan Baru', '', 15000, '', '', 'UMUMTEST', 'Y'],           // kode baru -> dibuat
        ];

        $hasil = app(MasterdataService::class)->importTindakan($rows, 'baru');

        $this->assertSame(1, $hasil['imported']);
        $this->assertSame(0, $hasil['updated']);
        $this->assertSame(1, $hasil['skipped']);
        $this->assertNotEmpty($hasil['errors']);

        // TX-OLD TIDAK boleh berubah sama sekali.
        $this->assertSame('Nama Lama', $existing->fresh()->nama);
        $this->assertSame(10000.0, (float) $existing->fresh()->tarif);

        $this->assertDatabaseHas('master_tindakan', ['kode' => 'TX-NEW', 'nama' => 'Tindakan Baru']);
    }

    /** @test */
    public function import_tindakan_mode_update_hanya_mengubah_kode_yang_sudah_ada(): void
    {
        $poli = Poli::create(['nama' => 'Umum Test', 'kode' => 'UMUMTEST', 'is_active' => true]);
        $existing = MasterTindakan::create([
            'kode' => 'TX-OLD', 'nama' => 'Nama Lama', 'tarif' => 10000, 'kategori' => 'tindakan', 'is_active' => true,
        ]);
        $existing->poli()->sync([$poli->id]);

        $rows = [
            ['TX-OLD', 'Nama Sudah Diupdate', 'Deskripsi baru', 20000, 5000, 30000, 'UMUMTEST', 'Y'], // ada -> diupdate
            ['TX-NOTFOUND', 'Kode Belum Ada', '', 15000, '', '', 'UMUMTEST', 'Y'],                    // belum ada -> dilewati
        ];

        $hasil = app(MasterdataService::class)->importTindakan($rows, 'update');

        $this->assertSame(0, $hasil['imported']);
        $this->assertSame(1, $hasil['updated']);
        $this->assertSame(1, $hasil['skipped']);
        $this->assertNotEmpty($hasil['errors']);

        $this->assertSame('Nama Sudah Diupdate', $existing->fresh()->nama);
        $this->assertSame(20000.0, (float) $existing->fresh()->tarif);
        $this->assertDatabaseMissing('master_tindakan', ['kode' => 'TX-NOTFOUND']);
    }

    /** @test */
    public function import_tindakan_melewati_baris_dengan_kode_poli_tidak_ditemukan(): void
    {
        $rows = [
            ['TX-NOPOLI', 'Tindakan Tanpa Poli Valid', '', 15000, '', '', 'TIDAKADA', 'Y'],
        ];

        $hasil = app(MasterdataService::class)->importTindakan($rows, 'baru');

        $this->assertSame(0, $hasil['imported']);
        $this->assertSame(1, $hasil['skipped']);
        $this->assertNotEmpty($hasil['errors']);
        $this->assertDatabaseMissing('master_tindakan', ['kode' => 'TX-NOPOLI']);
    }

    /** @test */
    public function upload_xlsx_tindakan_mode_baru_lewat_komponen_livewire_berhasil_impor(): void
    {
        Poli::create(['nama' => 'Anak Test', 'kode' => 'ANAKTEST', 'is_active' => true]);
        $admin = $this->buatUserDenganRole('admin');
        $this->actingAs($admin);

        $file = $this->buatXlsxUpload(
            ['Kode', 'Nama', 'Deskripsi', 'Tarif', 'Tarif BPJS', 'Tarif WNA', 'Kode Poli', 'Status Aktif'],
            [['TX-LW', 'Tindakan Via Livewire', '', 25000, '', '', 'ANAKTEST', 'Y']]
        );

        Livewire::test(TindakanTable::class)
            ->call('openImportModal', 'baru')
            ->assertSet('importMode', 'baru')
            ->set('importFile', $file)
            ->assertSet('importState', 'preview')
            ->assertSet('previewRowCount', 1)
            ->call('doImport')
            ->assertSet('importState', 'done');

        $this->assertDatabaseHas('master_tindakan', ['kode' => 'TX-LW', 'nama' => 'Tindakan Via Livewire']);
    }

    /** @test */
    public function role_tanpa_masterdata_create_ditolak_buka_import_modal_tindakan(): void
    {
        // 'kasir' tidak punya masterdata.create.
        $kasir = $this->buatUserDenganRole('kasir');
        $this->actingAs($kasir);

        Livewire::test(TindakanTable::class)
            ->call('openImportModal', 'baru')
            ->assertForbidden();
    }

    // ── Import Penunjang (Lab/Radiologi) ─────────────────────────────

    /** @test */
    public function import_penunjang_mode_baru_dan_update_berjalan_terpisah(): void
    {
        $existing = ItemPenunjang::create([
            'kode' => 'LAB-OLD', 'nama' => 'Nama Lama', 'kategori' => 'lab', 'tarif' => 10000, 'is_active' => true,
        ]);

        // Mode baru: LAB-OLD dilewati, LAB-NEW dibuat.
        $hasilBaru = app(MasterdataService::class)->importPenunjang([
            ['LAB-OLD', 'Nama Yang Dicoba Diubah', '', 99999, '', '', '', 'Y'],
            ['LAB-NEW', 'Item Baru', '', 30000, '', '', '', 'Y'],
        ], 'lab', 'baru');

        $this->assertSame(1, $hasilBaru['imported']);
        $this->assertSame(0, $hasilBaru['updated']);
        $this->assertSame(1, $hasilBaru['skipped']);
        $this->assertSame('Nama Lama', $existing->fresh()->nama); // tidak berubah

        // Mode update: LAB-OLD diupdate, kode fiktif dilewati.
        $hasilUpdate = app(MasterdataService::class)->importPenunjang([
            ['LAB-OLD', 'Nama Diupdate', '', 20000, '', '', 'hari', 'Y'],
            ['LAB-TIDAKADA', 'Kode Belum Ada', '', 10000, '', '', '', 'Y'],
        ], 'lab', 'update');

        $this->assertSame(0, $hasilUpdate['imported']);
        $this->assertSame(1, $hasilUpdate['updated']);
        $this->assertSame(1, $hasilUpdate['skipped']);
        $this->assertSame('Nama Diupdate', $existing->fresh()->nama);
        $this->assertDatabaseMissing('item_penunjang', ['kode' => 'LAB-TIDAKADA']);
    }

    /** @test */
    public function upload_xlsx_radiologi_mode_baru_lewat_komponen_livewire_berhasil_impor(): void
    {
        $admin = $this->buatUserDenganRole('admin');
        $this->actingAs($admin);

        $file = $this->buatXlsxUpload(
            ['Kode', 'Nama', 'Deskripsi', 'Tarif', 'Tarif BPJS', 'Tarif WNA', 'Satuan Waktu', 'Status Aktif'],
            [['RAD-LW', 'Item Radiologi Via Livewire', '', 40000, '', '', '', 'Y']]
        );

        Livewire::test(PenunjangTable::class, ['kategori' => 'radiologi'])
            ->call('openImportModal', 'baru')
            ->set('importFile', $file)
            ->assertSet('importState', 'preview')
            ->call('doImport')
            ->assertSet('importState', 'done');

        $this->assertDatabaseHas('item_penunjang', ['kode' => 'RAD-LW', 'kategori' => 'radiologi']);
    }

    // ── Import Peralatan ──────────────────────────────────────────────

    /** @test */
    public function import_peralatan_mode_baru_dan_update_berjalan_terpisah(): void
    {
        $existing = PeralatanMedis::create([
            'kode' => 'ALT-OLD', 'nama' => 'Alat Lama', 'status' => 'tersedia', 'is_active' => true,
        ]);

        $hasilBaru = app(MasterdataService::class)->importPeralatan([
            ['ALT-OLD', 'Nama Yang Dicoba Diubah', '', '', '', 'Y'],
            ['ALT-NEW', 'Alat Baru', 'Merk Y', 'SN-NEW-1', '', 'Y'],
        ], 'baru');

        $this->assertSame(1, $hasilBaru['imported']);
        $this->assertSame(0, $hasilBaru['updated']);
        $this->assertSame(1, $hasilBaru['skipped']);
        $this->assertSame('Alat Lama', $existing->fresh()->nama);

        $hasilUpdate = app(MasterdataService::class)->importPeralatan([
            ['ALT-OLD', 'Alat Sudah Diupdate', 'Merk X', 'SN-OLD-1', '', 'Y'],
            ['ALT-TIDAKADA', 'Kode Belum Ada', '', '', '', 'Y'],
        ], 'update');

        $this->assertSame(0, $hasilUpdate['imported']);
        $this->assertSame(1, $hasilUpdate['updated']);
        $this->assertSame(1, $hasilUpdate['skipped']);
        $this->assertSame('Alat Sudah Diupdate', $existing->fresh()->nama);
        $this->assertDatabaseMissing('peralatan_medis', ['kode' => 'ALT-TIDAKADA']);
    }

    /** @test */
    public function import_peralatan_melewati_nomor_seri_yang_dipakai_alat_lain(): void
    {
        PeralatanMedis::create([
            'kode' => 'ALT-A', 'nama' => 'Alat A', 'nomor_seri' => 'SN-DUPLIKAT', 'status' => 'tersedia', 'is_active' => true,
        ]);

        $rows = [
            ['ALT-B', 'Alat B', '', 'SN-DUPLIKAT', '', 'Y'],
        ];

        $hasil = app(MasterdataService::class)->importPeralatan($rows, 'baru');

        $this->assertSame(0, $hasil['imported']);
        $this->assertSame(1, $hasil['skipped']);
        $this->assertDatabaseMissing('peralatan_medis', ['kode' => 'ALT-B']);
    }

    /** @test */
    public function upload_xlsx_peralatan_mode_baru_lewat_komponen_livewire_berhasil_impor(): void
    {
        $admin = $this->buatUserDenganRole('admin');
        $this->actingAs($admin);

        $file = $this->buatXlsxUpload(
            ['Kode', 'Nama', 'Merk', 'Nomor Seri', 'Deskripsi', 'Status Aktif'],
            [['ALT-LW', 'Alat Via Livewire', 'Merk Z', '', '', 'Y']]
        );

        Livewire::test(PeralatanTable::class)
            ->call('openImportModal', 'baru')
            ->set('importFile', $file)
            ->assertSet('importState', 'preview')
            ->call('doImport')
            ->assertSet('importState', 'done');

        $this->assertDatabaseHas('peralatan_medis', ['kode' => 'ALT-LW', 'nama' => 'Alat Via Livewire']);
    }

    /** @test */
    public function upload_xlsx_peralatan_mode_update_lewat_komponen_livewire_hanya_ubah_yang_ada(): void
    {
        $existing = PeralatanMedis::create([
            'kode' => 'ALT-UPD', 'nama' => 'Alat Sebelum Update', 'status' => 'tersedia', 'is_active' => true,
        ]);
        $admin = $this->buatUserDenganRole('admin');
        $this->actingAs($admin);

        $file = $this->buatXlsxUpload(
            ['Kode', 'Nama', 'Merk', 'Nomor Seri', 'Deskripsi', 'Status Aktif'],
            [['ALT-UPD', 'Alat Setelah Update', 'Merk Baru', '', '', 'Y']]
        );

        Livewire::test(PeralatanTable::class)
            ->call('openImportModal', 'update')
            ->assertSet('importMode', 'update')
            ->set('importFile', $file)
            ->assertSet('importState', 'preview')
            ->call('doImport')
            ->assertSet('importState', 'done');

        $this->assertSame('Alat Setelah Update', $existing->fresh()->nama);
    }
}

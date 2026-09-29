<?php

namespace Tests\Feature;

use App\Exports\Masterdata\MasterdataTemplateExport;
use App\Livewire\Inventory\Barang\BarangTable;
use App\Models\Barang;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Inventory\BarangImportService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Maatwebsite\Excel\Excel as ExcelType;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

/**
 * Fitur upload & download template XLS utk Master Barang (Inventory) --
 * pola sama dgn Master Data Klinis: proses terpisah 'baru'/'update'
 * (BUKAN upsert gabungan), Obat & Bahan Habis Pakai ikut markup otomatis
 * berbasis harga modal (harga jual di kolom file diabaikan kalau HPR
 * diisi), Alkes & Lainnya tetap pakai harga jual dari file apa adanya.
 * Stok TIDAK ikut berubah lewat mode update (cuma diisi utk baris baru).
 *
 * Pakai DatabaseTransactions -- bukan RefreshDatabase.
 */
class BarangImportExportTest extends TestCase
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
    public function apoteker_bisa_download_template_barang(): void
    {
        $apoteker = $this->buatUserDenganRole('apoteker');
        $this->actingAs($apoteker);

        $this->get(route('inventory.barang.template'))->assertOk();
    }

    /** @test */
    public function role_tanpa_obat_view_ditolak_download_template(): void
    {
        // 'dokter' tidak punya obat.view.
        $dokter = $this->buatUserDenganRole('dokter');
        $this->actingAs($dokter);

        $this->get(route('inventory.barang.template'))->assertForbidden();
    }

    // ── Import mode 'baru' vs 'update' terpisah ──────────────────────

    /** @test */
    public function import_mode_baru_hanya_membuat_baris_baru_kode_lama_dilewati(): void
    {
        $existing = Barang::create([
            'kode' => 'BRG-OLD', 'nama' => 'Nama Lama', 'jenis' => 'alkes',
            'satuan' => 'Pcs', 'stok' => 5, 'harga_pokok' => 1000, 'harga_jual' => 2000, 'is_active' => true,
        ]);

        $rows = [
            ['BRG-OLD', 'Nama Yang Dicoba Diubah', 'alkes', 'Pcs', 50, 5, 1000, 9999, '', 'Y'],
            ['BRG-NEW', 'Barang Baru', 'alkes', 'Box', 20, 5, 3000, 5000, '', 'Y'],
        ];

        $hasil = app(BarangImportService::class)->importBarang($rows, 'baru');

        $this->assertSame(1, $hasil['imported']);
        $this->assertSame(0, $hasil['updated']);
        $this->assertSame(1, $hasil['skipped']);
        $this->assertSame('Nama Lama', $existing->fresh()->nama); // tidak berubah

        $baru = Barang::where('kode', 'BRG-NEW')->first();
        $this->assertNotNull($baru);
        $this->assertSame(20, $baru->stok);
        $this->assertSame(5000.0, (float) $baru->harga_jual); // alkes -- pakai apa adanya dari file
    }

    /** @test */
    public function import_mode_update_hanya_mengubah_kode_yang_sudah_ada_dan_tidak_ubah_stok(): void
    {
        $existing = Barang::create([
            'kode' => 'BRG-UPD', 'nama' => 'Nama Lama', 'jenis' => 'alkes',
            'satuan' => 'Pcs', 'stok' => 77, 'harga_pokok' => 1000, 'harga_jual' => 2000, 'is_active' => true,
        ]);

        $rows = [
            ['BRG-UPD', 'Nama Sudah Diupdate', 'alkes', 'Pcs', 999, 5, 1500, 3000, '', 'Y'],
            ['BRG-NOTFOUND', 'Kode Belum Ada', 'alkes', 'Pcs', 10, 5, 1000, 2000, '', 'Y'],
        ];

        $hasil = app(BarangImportService::class)->importBarang($rows, 'update');

        $this->assertSame(0, $hasil['imported']);
        $this->assertSame(1, $hasil['updated']);
        $this->assertSame(1, $hasil['skipped']);

        $fresh = $existing->fresh();
        $this->assertSame('Nama Sudah Diupdate', $fresh->nama);
        $this->assertSame(3000.0, (float) $fresh->harga_jual);
        $this->assertSame(77, $fresh->stok); // stok TIDAK berubah walau kolom "Stok Awal" diisi 999 di file

        $this->assertDatabaseMissing('barang', ['kode' => 'BRG-NOTFOUND']);
    }

    // ── Markup otomatis Obat/BHP saat import, Alkes tetap manual ────

    /** @test */
    public function import_obat_dengan_hpr_menghitung_harga_jual_otomatis_dan_mengabaikan_kolom_harga_jual(): void
    {
        $rows = [
            ['OBT-IMPORT', 'Obat Import', 'obat', 'Tablet', 100, 10, 10000, 99999, '', 'Y'],
        ];

        $hasil = app(BarangImportService::class)->importBarang($rows, 'baru');

        $this->assertSame(1, $hasil['imported']);
        $obat = Barang::where('kode', 'OBT-IMPORT')->first();
        $this->assertSame(16000.0, (float) $obat->harga_jual); // 10000 x 1.6, bukan 99999
        $this->assertSame(24000.0, (float) $obat->harga_wna);  // 10000 x 2.4
    }

    /** @test */
    public function import_alkes_tetap_pakai_harga_jual_dari_file_walau_ada_hpr(): void
    {
        $rows = [
            ['ALK-IMPORT', 'Alkes Import', 'alkes', 'Pcs', 50, 10, 10000, 15000, '', 'Y'],
        ];

        $hasil = app(BarangImportService::class)->importBarang($rows, 'baru');

        $this->assertSame(1, $hasil['imported']);
        $this->assertSame(15000.0, (float) Barang::where('kode', 'ALK-IMPORT')->value('harga_jual'));
    }

    /** @test */
    public function import_dengan_kode_supplier_valid_menghubungkan_supplier_utama(): void
    {
        $supplier = Supplier::create(['kode' => 'SUPTEST', 'nama' => 'Supplier Test', 'tipe' => 'distributor']);

        $rows = [
            ['BRG-SUP', 'Barang Dgn Supplier', 'lainnya', 'Pcs', 10, 5, 1000, 2000, 'SUPTEST', 'Y'],
        ];

        app(BarangImportService::class)->importBarang($rows, 'baru');

        $barang = Barang::where('kode', 'BRG-SUP')->first();
        $this->assertSame($supplier->id, $barang->supplier_utama_id);
    }

    /** @test */
    public function import_melewati_baris_dengan_jenis_tidak_valid(): void
    {
        $rows = [
            ['BRG-INVALID', 'Barang Invalid', 'jenis_ngawur', 'Pcs', 10, 5, 1000, 2000, '', 'Y'],
        ];

        $hasil = app(BarangImportService::class)->importBarang($rows, 'baru');

        $this->assertSame(0, $hasil['imported']);
        $this->assertSame(1, $hasil['skipped']);
        $this->assertDatabaseMissing('barang', ['kode' => 'BRG-INVALID']);
    }

    // ── Lewat komponen Livewire ────────────────────────────────────────

    /** @test */
    public function upload_xlsx_barang_lewat_komponen_livewire_berhasil_impor(): void
    {
        $apoteker = $this->buatUserDenganRole('apoteker');
        $this->actingAs($apoteker);

        $file = $this->buatXlsxUpload(
            ['Kode', 'Nama Barang', 'Jenis', 'Satuan', 'Stok Awal', 'Stok Minimum', 'HPR', 'Harga Jual', 'Kode Supplier', 'Status Aktif'],
            [['BRG-LW', 'Barang Via Livewire', 'lainnya', 'Pcs', 15, 5, 1000, 2500, '', 'Y']]
        );

        Livewire::test(BarangTable::class)
            ->call('openImportModal', 'baru')
            ->assertSet('importMode', 'baru')
            ->set('importFile', $file)
            ->assertSet('importState', 'preview')
            ->assertSet('previewRowCount', 1)
            ->call('doImport')
            ->assertSet('importState', 'done');

        $this->assertDatabaseHas('barang', ['kode' => 'BRG-LW', 'nama' => 'Barang Via Livewire']);
    }

    /** @test */
    public function role_tanpa_obat_create_ditolak_buka_import_modal(): void
    {
        // Role custom hanya dgn obat.view, tanpa obat.create.
        $role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'gudang_view_only_barang']);
        $role->syncPermissions(['obat.view']);
        $user = $this->buatUserDenganRole('gudang_view_only_barang');
        $this->actingAs($user);

        Livewire::test(BarangTable::class)
            ->call('openImportModal', 'baru')
            ->assertForbidden();
    }
}

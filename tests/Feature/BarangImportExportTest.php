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

    /**
     * Bug lama di Barang::generateKode() -- baru ketahuan sekarang saat
     * fitur auto-kode dipakai utk banyak baris sekaligus (lihat test
     * setelah ini). orderByDesc('kode') sebelumnya diambil dari SEMUA kode
     * di tabel, bukan cuma yg berformat "BRG-", jadi kalau ada kode lain
     * yg urut alfabet lebih besar (mis. "OBT999" > "BRG-000001" krn
     * 'O' > 'B') -- yg SELALU terjadi di sistem ini krn data existing
     * semuanya pakai skema kode lain, bukan "BRG-" -- hasilnya SELALU
     * "BRG-000001" dibuat ulang-ulang, tidak pernah maju ke 000002 dst.
     */
    /** @test */
    public function generate_kode_tidak_terganggu_kode_lain_yang_urut_alfabet_lebih_besar(): void
    {
        // "ZZZ..." sengaja dipilih supaya PASTI urut alfabet lebih besar dari
        // "BRG-" ('Z' > 'B') tanpa resiko bentrok dgn kode OBT999 milik data
        // demo/template yang mungkin sudah ada di DB (unik via rand()).
        Barang::create([
            'kode' => 'ZZZ' . rand(1000, 9999), 'nama' => 'Barang Skema Kode Lain', 'jenis' => 'obat',
            'satuan' => 'Tablet', 'stok' => 0, 'harga_pokok' => 0, 'harga_jual' => 0, 'is_active' => true,
        ]);

        $kode1 = Barang::generateKode();
        Barang::create([
            'kode' => $kode1, 'nama' => 'Barang BRG Pertama', 'jenis' => 'alkes',
            'satuan' => 'Pcs', 'stok' => 0, 'harga_pokok' => 0, 'harga_jual' => 0, 'is_active' => true,
        ]);

        $kode2 = Barang::generateKode();

        $this->assertNotSame($kode1, $kode2, 'generateKode() harus maju ke nomor berikutnya, tidak boleh stuck di nomor yang sama.');
        $this->assertMatchesRegularExpression('/^BRG-\d{6}$/', $kode1);
        $this->assertMatchesRegularExpression('/^BRG-\d{6}$/', $kode2);
    }

    /**
     * Permintaan user: kolom Kode bisa dikosongkan saat upload (khusus mode
     * 'baru') supaya user tidak perlu hapal/cek manual kode terakhir --
     * dibuatkan otomatis sama persis spt tombol "Tambah Barang Baru" di
     * form (Barang::generateKode(), format BRG-NNNNNN).
     */
    /** @test */
    public function import_mode_baru_dengan_kode_kosong_dibuatkan_otomatis(): void
    {
        $rows = [
            ['', 'Barang Tanpa Kode', 'alkes', 'Pcs', 10, 5, 1000, 2000, '', 'Y'],
        ];

        $hasil = app(BarangImportService::class)->importBarang($rows, 'baru');

        $this->assertSame(1, $hasil['imported']);
        $this->assertSame(0, $hasil['skipped']);
        $this->assertNotEmpty($hasil['warnings'], 'Harus ada warning yg memberi tahu kode otomatis apa yang dipakai.');

        $baru = Barang::where('nama', 'Barang Tanpa Kode')->first();
        $this->assertNotNull($baru);
        $this->assertMatchesRegularExpression('/^BRG-\d{6}$/', $baru->kode);
    }

    /** @test */
    public function import_mode_baru_dengan_beberapa_baris_kode_kosong_dapat_kode_berbeda_beda(): void
    {
        $rows = [
            ['', 'Barang Kosong Satu', 'alkes', 'Pcs', 10, 5, 1000, 2000, '', 'Y'],
            ['', 'Barang Kosong Dua', 'alkes', 'Pcs', 10, 5, 1000, 2000, '', 'Y'],
        ];

        $hasil = app(BarangImportService::class)->importBarang($rows, 'baru');

        $this->assertSame(2, $hasil['imported']);

        $kode1 = Barang::where('nama', 'Barang Kosong Satu')->value('kode');
        $kode2 = Barang::where('nama', 'Barang Kosong Dua')->value('kode');
        $this->assertNotSame($kode1, $kode2, 'Dua baris kode kosong dlm 1 batch tidak boleh dapat kode yang sama.');
    }

    /** @test */
    public function import_mode_update_dengan_kode_kosong_dilewati_krn_kode_wajib_jadi_kunci_cari(): void
    {
        $rows = [
            ['', 'Barang Tanpa Kode', 'alkes', 'Pcs', 10, 5, 1000, 2000, '', 'Y'],
        ];

        $hasil = app(BarangImportService::class)->importBarang($rows, 'update');

        $this->assertSame(0, $hasil['updated']);
        $this->assertSame(1, $hasil['skipped']);
        $this->assertDatabaseMissing('barang', ['nama' => 'Barang Tanpa Kode']);
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
    public function import_jenis_bhp_dikonversi_otomatis_jadi_bahan_habis_pakai(): void
    {
        $rows = [
            ['BRG-BHP', 'Kassa Steril BHP', 'bhp', 'Box', 10, 5, 5000, 10000, '', 'Y'],
        ];

        $hasil = app(BarangImportService::class)->importBarang($rows, 'baru');

        $this->assertSame(1, $hasil['imported']);
        $this->assertDatabaseHas('barang', [
            'kode' => 'BRG-BHP',
            'jenis' => 'bahan_habis_pakai',
        ]);
    }

    /**
     * Bug production (laporan user): nama obat paten yg mencantumkan
     * komposisi lengkap dlm kurung bisa tembus >150 karakter (kolom nama
     * waktu itu varchar(150)) -- SQLSTATE[22001] mentah bikin SELURUH
     * batch (termasuk baris2 lain yg valid) ikut gagal krn 1 transaksi.
     * Sekarang kolom dilebarkan ke 255 + baris yg masih kepanjangan
     * (>255) di-skip SENDIRI tanpa menggagalkan baris lain di batch yg sama.
     */
    /** @test */
    public function import_melewati_baris_dengan_nama_terlalu_panjang_tanpa_menggagalkan_baris_lain(): void
    {
        $namaKepanjangan = str_repeat('A', 256); // 1 char lewat batas 255

        $rows = [
            ['BRG-PANJANG', $namaKepanjangan, 'alkes', 'Pcs', 10, 5, 1000, 2000, '', 'Y'],
            ['BRG-NORMAL', 'Barang Normal', 'alkes', 'Pcs', 10, 5, 1000, 2000, '', 'Y'],
        ];

        $hasil = app(BarangImportService::class)->importBarang($rows, 'baru');

        $this->assertSame(1, $hasil['imported'], 'Baris yg valid (BRG-NORMAL) harus tetap berhasil diimpor.');
        $this->assertSame(1, $hasil['skipped']);
        $this->assertDatabaseMissing('barang', ['kode' => 'BRG-PANJANG']);
        $this->assertDatabaseHas('barang', ['kode' => 'BRG-NORMAL']);
    }

    /** @test */
    public function import_nama_pas_255_karakter_tetap_berhasil(): void
    {
        $namaPas = str_repeat('B', 255); // pas di batas, tidak boleh ikut dilewati

        $rows = [
            ['BRG-PAS255', $namaPas, 'alkes', 'Pcs', 10, 5, 1000, 2000, '', 'Y'],
        ];

        $hasil = app(BarangImportService::class)->importBarang($rows, 'baru');

        $this->assertSame(1, $hasil['imported']);
        $this->assertSame(0, $hasil['skipped']);
        $this->assertSame($namaPas, Barang::where('kode', 'BRG-PAS255')->first()->nama);
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

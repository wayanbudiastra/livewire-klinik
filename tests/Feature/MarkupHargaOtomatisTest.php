<?php

namespace Tests\Feature;

use App\Livewire\Farmasi\ObatForm;
use App\Livewire\Inventory\Barang\BarangForm;
use App\Livewire\Pengaturan\HargaWna;
use App\Livewire\Pengaturan\Masterdata\PenunjangForm;
use App\Models\Barang;
use App\Models\GoodsReceipt;
use App\Models\GrItem;
use App\Models\ItemPenunjang;
use App\Models\KonfigurasiMarkupHarga;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Inventory\PenerimaanService;
use App\Services\MasterdataService;
use App\Services\Harga\MarkupHargaService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Fitur baru: markup harga jual OTOMATIS berbasis harga modal, per
 * kategori -- Obat & BHP (default 2.4x WNA / 1.6x KTP dari modal) dan Lab
 * (default 2.0x WNA / 1.3x KTP dari modal). Radiologi, Tindakan, Alkes,
 * dan jenis Barang 'lainnya' TETAP manual (tidak ikut sistem ini).
 *
 * Perilaku:
 * - Dihitung OTOMATIS setiap kali harga modal berubah (GR verifikasi utk
 *   Obat/BHP, form Obat/Barang/Lab saat modal diketik ulang).
 * - Field harga jual & WNA TETAP bisa diedit manual sesudahnya -- tapi
 *   kalau modal diubah lagi, dihitung ulang lagi (bukan mempertahankan
 *   override manual sebelumnya).
 * - Validasi harga di bawah modal: PERINGATAN saja (notify type warning +
 *   activity log), TETAP bisa disimpan, tidak diblokir.
 * - Akses halaman Pengaturan > Markup Harga Jual: permission
 *   harga.markup.manage (bisa dibagikan lewat Hak Akses Tambahan),
 *   bukan lagi hardcode role super_admin.
 *
 * Pakai DatabaseTransactions -- bukan RefreshDatabase.
 */
class MarkupHargaOtomatisTest extends TestCase
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

    private function buatSupplier(): Supplier
    {
        return Supplier::create(['kode' => 'SUP-' . uniqid(), 'nama' => 'Supplier Test ' . uniqid(), 'tipe' => 'distributor']);
    }

    // ── MarkupHargaService dasar ─────────────────────────────────────

    /** @test */
    public function hitung_mengalikan_modal_dengan_multiplier_kategori(): void
    {
        $hasil = app(MarkupHargaService::class)->hitung('obat_bhp', 10000);

        $this->assertSame(16000.0, $hasil['ktp']); // default 1.6x
        $this->assertSame(24000.0, $hasil['wna']); // default 2.4x
    }

    /** @test */
    public function hitung_lab_pakai_multiplier_berbeda_dari_obat_bhp(): void
    {
        $hasil = app(MarkupHargaService::class)->hitung('lab', 10000);

        $this->assertSame(13000.0, $hasil['ktp']); // default 1.3x
        $this->assertSame(20000.0, $hasil['wna']); // default 2.0x
    }

    /** @test */
    public function hitung_modal_nol_atau_null_menghasilkan_null(): void
    {
        $service = app(MarkupHargaService::class);
        $this->assertNull($service->hitung('obat_bhp', null)['ktp']);
        $this->assertNull($service->hitung('obat_bhp', 0)['ktp']);
    }

    // ── Hook GR (Moving Average) -- Obat/BHP otomatis, Alkes manual ──

    /** @test */
    public function verifikasi_gr_obat_menghitung_ulang_harga_jual_dan_wna_otomatis(): void
    {
        $supplier = $this->buatSupplier();
        $obat = Barang::create([
            'kode' => 'OBT-' . uniqid(), 'nama' => 'Obat Test', 'jenis' => 'obat',
            'satuan' => 'Tablet', 'stok' => 0, 'harga_pokok' => 0, 'harga_jual' => 5000,
        ]);
        $gr = GoodsReceipt::create([
            'nomor_gr' => 'GR-TEST-' . uniqid(), 'supplier_id' => $supplier->id,
            'diterima_oleh' => $this->buatUserDenganRole('apoteker')->id,
            'tanggal_terima' => now(), 'status' => 'draft', 'total_nilai' => 100000,
        ]);
        GrItem::create([
            'goods_receipt_id' => $gr->id, 'barang_id' => $obat->id, 'jumlah_terima' => 10,
            'harga_satuan' => 10000, 'subtotal' => 100000,
        ]);

        app(PenerimaanService::class)->verifikasiGr($gr, $this->buatUserDenganRole('apoteker')->id);

        $fresh = $obat->fresh();
        $this->assertSame(10000.0, (float) $fresh->harga_pokok); // stok lama 0 -> HPR = harga beli
        $this->assertSame(16000.0, (float) $fresh->harga_jual);  // 10000 x 1.6
        $this->assertSame(24000.0, (float) $fresh->harga_wna);   // 10000 x 2.4
    }

    /** @test */
    public function verifikasi_gr_alkes_tidak_ikut_hitung_otomatis(): void
    {
        $supplier = $this->buatSupplier();
        $alkes = Barang::create([
            'kode' => 'ALK-' . uniqid(), 'nama' => 'Alkes Test', 'jenis' => 'alkes',
            'satuan' => 'Pcs', 'stok' => 0, 'harga_pokok' => 0, 'harga_jual' => 5000, 'harga_wna' => 7000,
        ]);
        $gr = GoodsReceipt::create([
            'nomor_gr' => 'GR-TEST-' . uniqid(), 'supplier_id' => $supplier->id,
            'diterima_oleh' => $this->buatUserDenganRole('apoteker')->id,
            'tanggal_terima' => now(), 'status' => 'draft', 'total_nilai' => 100000,
        ]);
        GrItem::create([
            'goods_receipt_id' => $gr->id, 'barang_id' => $alkes->id, 'jumlah_terima' => 10,
            'harga_satuan' => 10000, 'subtotal' => 100000,
        ]);

        app(PenerimaanService::class)->verifikasiGr($gr, $this->buatUserDenganRole('apoteker')->id);

        $fresh = $alkes->fresh();
        $this->assertSame(10000.0, (float) $fresh->harga_pokok); // modal tetap ter-update
        $this->assertSame(5000.0, (float) $fresh->harga_jual);   // harga jual TIDAK berubah (manual)
        $this->assertSame(7000.0, (float) $fresh->harga_wna);    // harga WNA TIDAK berubah (manual)
    }

    // ── BarangForm (Inventory > Data Barang) ─────────────────────────

    /** @test */
    public function barang_form_hitung_ulang_otomatis_saat_harga_pokok_diubah_utk_jenis_obat(): void
    {
        $apoteker = $this->buatUserDenganRole('apoteker');
        $this->actingAs($apoteker);

        Livewire::test(BarangForm::class)
            ->call('openCreate')
            ->set('jenis', 'obat')
            ->set('harga_pokok', '10000')
            ->assertSet('harga_jual', '16000')
            ->assertSet('harga_wna', '24000');
    }

    /** @test */
    public function barang_form_tidak_hitung_otomatis_utk_jenis_alkes(): void
    {
        $apoteker = $this->buatUserDenganRole('apoteker');
        $this->actingAs($apoteker);

        Livewire::test(BarangForm::class)
            ->call('openCreate')
            ->set('jenis', 'alkes')
            ->set('harga_jual', '5000')
            ->set('harga_pokok', '10000')
            ->assertSet('harga_jual', '5000'); // tidak berubah
    }

    /** @test */
    public function barang_form_tetap_bisa_disimpan_dgn_peringatan_kalau_harga_dibawah_modal(): void
    {
        $apoteker = $this->buatUserDenganRole('apoteker');
        $this->actingAs($apoteker);

        Livewire::test(BarangForm::class)
            ->call('openCreate')
            ->set('kode', 'BLW' . rand(1000, 9999))
            ->set('nama', 'Obat Rugi Test')
            ->set('jenis', 'alkes') // alkes supaya harga_jual tidak ketimpa otomatis
            ->set('satuan', 'Pcs')
            ->set('harga_pokok', '10000')
            ->set('harga_jual', '5000') // di bawah modal, sengaja
            ->call('save')
            ->assertDispatched('notify', type: 'warning');

        $this->assertDatabaseHas('barang', ['nama' => 'Obat Rugi Test', 'harga_jual' => 5000]);
    }

    // ── ObatForm (Farmasi > Stok Obat) ────────────────────────────────

    /** @test */
    public function obat_form_hitung_ulang_otomatis_saat_harga_beli_diubah(): void
    {
        $apoteker = $this->buatUserDenganRole('apoteker');
        $this->actingAs($apoteker);

        Livewire::test(ObatForm::class)
            ->call('openCreate')
            ->set('jenis_barang', 'obat')
            ->set('harga_beli', '10000')
            ->assertSet('harga', '16000')
            ->assertSet('harga_wna', '24000');
    }

    /** @test */
    public function obat_form_tidak_hitung_otomatis_utk_jenis_alkes(): void
    {
        $apoteker = $this->buatUserDenganRole('apoteker');
        $this->actingAs($apoteker);

        Livewire::test(ObatForm::class)
            ->call('openCreate')
            ->set('jenis_barang', 'alkes')
            ->set('harga', '5000')
            ->set('harga_beli', '10000')
            ->assertSet('harga', '5000');
    }

    // ── PenunjangForm (Lab otomatis, Radiologi manual) ────────────────

    /** @test */
    public function penunjang_form_lab_hitung_ulang_otomatis_saat_harga_modal_diubah(): void
    {
        $admin = $this->buatUserDenganRole('admin');
        $this->actingAs($admin);

        Livewire::test(PenunjangForm::class)
            ->call('openCreate', 'lab')
            ->set('harga_modal', '10000')
            ->assertSet('tarif', '13000')
            ->assertSet('tarif_wna', '20000');
    }

    /** @test */
    public function penunjang_form_radiologi_tidak_ikut_markup_otomatis(): void
    {
        $admin = $this->buatUserDenganRole('admin');
        $this->actingAs($admin);

        Livewire::test(PenunjangForm::class)
            ->call('openCreate', 'radiologi')
            ->assertSet('ikutMarkupOtomatis', false);
    }

    /** @test */
    public function penunjang_form_lab_tetap_bisa_disimpan_dgn_peringatan_kalau_tarif_dibawah_modal(): void
    {
        $admin = $this->buatUserDenganRole('admin');
        $this->actingAs($admin);

        Livewire::test(PenunjangForm::class)
            ->call('openCreate', 'lab')
            ->set('kode', 'LAB-BELOW-' . uniqid())
            ->set('nama', 'Lab Rugi Test')
            ->set('harga_modal', '10000')
            ->set('tarif', '5000') // ketimpa otomatis dulu jadi 13000, lalu diset manual lagi ke bawah modal
            ->call('save')
            ->assertDispatched('notify', type: 'warning');

        $this->assertDatabaseHas('item_penunjang', ['nama' => 'Lab Rugi Test', 'tarif' => 5000]);
    }

    // ── Import Penunjang (Lab) dgn kolom Harga Modal ──────────────────

    /** @test */
    public function import_lab_dengan_harga_modal_menghitung_tarif_otomatis_dan_mengabaikan_kolom_tarif(): void
    {
        // Kolom: 0=Kode,1=Nama,2=Deskripsi,3=Tarif,4=TarifBPJS,5=TarifWNA,6=SatuanWaktu,7=StatusAktif,8=HargaModal
        $rows = [
            ['LAB-IMPORT', 'Item Lab Import', '', 99999, '', 99999, '', 'Y', 10000],
        ];

        $hasil = app(MasterdataService::class)->importPenunjang($rows, 'lab', 'baru');

        $this->assertSame(1, $hasil['imported']);
        $item = ItemPenunjang::where('kode', 'LAB-IMPORT')->first();
        $this->assertSame(13000.0, (float) $item->tarif);     // dihitung dari modal, bukan 99999
        $this->assertSame(20000.0, (float) $item->tarif_wna);
        $this->assertSame(10000.0, (float) $item->harga_modal);
    }

    /** @test */
    public function import_lab_tanpa_harga_modal_tetap_pakai_tarif_yang_diberikan(): void
    {
        $rows = [
            ['LAB-MANUAL', 'Item Lab Manual', '', 45000, '', '', '', 'Y'], // tanpa kolom ke-9
        ];

        $hasil = app(MasterdataService::class)->importPenunjang($rows, 'lab', 'baru');

        $this->assertSame(1, $hasil['imported']);
        $this->assertSame(45000.0, (float) ItemPenunjang::where('kode', 'LAB-MANUAL')->value('tarif'));
    }

    // ── Halaman Pengaturan > Markup Harga Jual ────────────────────────

    /** @test */
    public function role_tanpa_permission_ditolak_akses_halaman_markup(): void
    {
        // 'apoteker' tidak punya harga.markup.manage secara default.
        $apoteker = $this->buatUserDenganRole('apoteker');
        $this->actingAs($apoteker);

        Livewire::test(HargaWna::class)->assertForbidden();
        $this->get(route('pengaturan.harga-wna'))->assertForbidden();
    }

    /** @test */
    public function user_yang_diberi_hak_akses_tambahan_bisa_akses_halaman_markup(): void
    {
        $apoteker = $this->buatUserDenganRole('apoteker');
        $apoteker->givePermissionTo('harga.markup.manage'); // simulasi "Hak Akses Tambahan"
        $this->actingAs($apoteker);

        Livewire::test(HargaWna::class)->assertOk();
        $this->get(route('pengaturan.harga-wna'))->assertOk();
    }

    /** @test */
    public function admin_super_admin_tetap_bisa_akses_lewat_gate_before(): void
    {
        $superAdmin = $this->buatUserDenganRole('super_admin');
        $this->actingAs($superAdmin);

        Livewire::test(HargaWna::class)->assertOk();
    }

    /** @test */
    public function simpan_markup_otomatis_menyimpan_multiplier_baru(): void
    {
        $superAdmin = $this->buatUserDenganRole('super_admin');
        $this->actingAs($superAdmin);

        Livewire::test(HargaWna::class)
            ->set('markupObatBhpKtp', '1.7')
            ->set('markupObatBhpWna', '2.5')
            ->set('markupLabKtp', '1.4')
            ->set('markupLabWna', '2.1')
            ->call('simpanMarkupOtomatis');

        $this->assertSame(1.7, (float) KonfigurasiMarkupHarga::where('kategori', 'obat_bhp')->value('multiplier_ktp'));
        $this->assertSame(2.5, (float) KonfigurasiMarkupHarga::where('kategori', 'obat_bhp')->value('multiplier_wna'));
        $this->assertSame(1.4, (float) KonfigurasiMarkupHarga::where('kategori', 'lab')->value('multiplier_ktp'));
    }

    /** @test */
    public function hitung_ulang_semua_menimpa_harga_existing_dgn_multiplier_terbaru(): void
    {
        $superAdmin = $this->buatUserDenganRole('super_admin');
        $this->actingAs($superAdmin);

        $obat = Barang::create([
            'kode' => 'BKF' . rand(1000, 9999), 'nama' => 'Obat Backfill', 'jenis' => 'obat',
            'satuan' => 'Tablet', 'stok' => 10, 'harga_pokok' => 10000, 'harga_jual' => 11000, // harga lama, beda dari rumus baru
        ]);

        Livewire::test(HargaWna::class)->call('hitungUlangSemua');

        $fresh = $obat->fresh();
        $this->assertSame(16000.0, (float) $fresh->harga_jual); // ketimpa jadi 10000 x 1.6
        $this->assertSame(24000.0, (float) $fresh->harga_wna);
    }
}

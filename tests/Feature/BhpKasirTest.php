<?php

namespace Tests\Feature;

use App\Livewire\Kasir\TagihanPasien;
use App\Models\Akuntansi\JurnalPending;
use App\Models\Barang;
use App\Models\Dokter;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Kunjungan;
use App\Models\MutasiStok;
use App\Models\Pasien;
use App\Models\PemakaianAlkes;
use App\Models\Poli;
use App\Models\SesiKas;
use App\Models\User;
use App\Services\Kasir\BhpKasirService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Input BHP oleh kasir dari panel "Tambah Komponen Tagihan"
 * (docs/PERANCANGAN_BHP_KASIR.md): disimpan sebagai pemakaian_alkes sumber
 * 'kasir' (tetap ada saat tagihan dimuat ulang), stok baru dipotong saat
 * LUNAS, jurnal HPP 5-1100/1-1300, dan stok kembali saat billing dibatalkan.
 *
 * Pakai DatabaseTransactions -- bukan RefreshDatabase.
 */
class BhpKasirTest extends TestCase
{
    use DatabaseTransactions;

    private User $kasir;
    private Kunjungan $kunjungan;
    private Barang $bhp;

    protected function setUp(): void
    {
        parent::setUp();

        $this->kasir = User::create(['nama' => 'Kasir ' . uniqid(), 'email' => uniqid() . '@x.test', 'password' => Hash::make('p'), 'is_active' => true]);
        $this->kasir->assignRole('kasir');
        SesiKas::create(['user_id' => $this->kasir->id, 'tanggal' => now()->toDateString(), 'dibuka_pada' => now(), 'saldo_awal' => 0, 'status' => 'buka']);

        $du = User::create(['nama' => 'Dr ' . uniqid(), 'email' => uniqid() . '@x.test', 'password' => Hash::make('p'), 'is_active' => true]);
        $dokter = Dokter::create(['user_id' => $du->id]);
        $poli   = Poli::create(['nama' => 'Poli ' . uniqid(), 'kode' => 'P' . rand(1000, 9999), 'is_active' => true]);
        $pasien = Pasien::create(['nomor_rm' => 'RM-' . uniqid(), 'nama' => 'Pasien Uji', 'tempat_lahir' => 'X', 'tanggal_lahir' => '1990-01-01', 'jenis_kelamin' => 'L', 'alamat' => 'x', 'telepon' => '08']);
        $this->kunjungan = Kunjungan::create([
            'nomor_antrean' => 'W-' . rand(100, 999), 'pasien_id' => $pasien->id, 'dokter_id' => $dokter->id,
            'poli_id' => $poli->id, 'tanggal' => now(), 'status' => 'selesai',
        ]);

        $this->bhp = Barang::create([
            'kode' => 'BHP' . rand(1000, 9999), 'nama' => 'Kasa Steril Uji', 'jenis' => 'bahan_habis_pakai',
            'satuan' => 'pcs', 'stok' => 10, 'harga_pokok' => 1000, 'harga_jual' => 2500, 'is_active' => true,
        ]);

        $this->actingAs($this->kasir);
    }

    private function buka()
    {
        return Livewire::test(TagihanPasien::class)->call('selectKunjungan', $this->kunjungan->id);
    }

    private function bayar($c): void
    {
        $c->set('metodePembayaran', 'tunai')->set('jumlahTunai', '9999999')->call('prosesPembayaran');
    }

    /** @test */
    public function tab_bhp_hanya_menampilkan_bhp_aktif_yang_ada_stok(): void
    {
        Barang::create(['kode' => 'OBT' . rand(1000, 9999), 'nama' => 'Obat Uji', 'jenis' => 'obat', 'satuan' => 'tab', 'stok' => 5, 'harga_pokok' => 1, 'harga_jual' => 2, 'is_active' => true]);
        Barang::create(['kode' => 'BHP' . rand(1000, 9999), 'nama' => 'BHP Kosong Uji', 'jenis' => 'bahan_habis_pakai', 'satuan' => 'pcs', 'stok' => 0, 'harga_pokok' => 1, 'harga_jual' => 2, 'is_active' => true]);

        $nama = collect(app(BhpKasirService::class)->daftar('Uji', false))->pluck('nama');

        $this->assertTrue($nama->contains('Kasa Steril Uji'));
        $this->assertFalse($nama->contains('Obat Uji'));
        $this->assertFalse($nama->contains('BHP Kosong Uji'));
    }

    /** @test */
    public function tambah_bhp_masuk_tagihan_tetap_ada_saat_muat_ulang_dan_stok_belum_berkurang(): void
    {
        $c = $this->buka()->call('addBhpItem', $this->bhp->id, 3);
        $c->call('fetchTagihan'); // muat ulang tagihan

        $item = InvoiceItem::where('nama_item', 'Kasa Steril Uji')->first();
        $this->assertNotNull($item, 'Item BHP harus tetap ada setelah tagihan dimuat ulang');
        $this->assertSame('alkes', $item->jenis);
        $this->assertEquals(3, (float) $item->qty);
        $this->assertEquals(7500, (float) $item->subtotal);

        $row = PemakaianAlkes::where('kunjungan_id', $this->kunjungan->id)->first();
        $this->assertSame('kasir', $row->sumber);
        $this->assertSame($this->kasir->id, $row->dicatat_oleh);
        $this->assertSame(10, (int) $this->bhp->fresh()->stok, 'Stok belum boleh dipotong sebelum lunas');
    }

    /** @test */
    public function bhp_yang_sama_digabung_dan_qty_melebihi_stok_ditolak(): void
    {
        $c = $this->buka()->call('addBhpItem', $this->bhp->id, 3)->call('addBhpItem', $this->bhp->id, 2);
        $this->assertSame(1, PemakaianAlkes::where('kunjungan_id', $this->kunjungan->id)->count());
        $this->assertSame(5, (int) PemakaianAlkes::where('kunjungan_id', $this->kunjungan->id)->value('jumlah'));

        $c->call('addBhpItem', $this->bhp->id, 6); // 5 + 6 > stok 10
        $this->assertSame(5, (int) PemakaianAlkes::where('kunjungan_id', $this->kunjungan->id)->value('jumlah'));
    }

    /** @test */
    public function hapus_item_bhp_juga_menghapus_baris_pemakaian_dan_tidak_muncul_lagi(): void
    {
        $c = $this->buka()->call('addBhpItem', $this->bhp->id, 2);
        $item = InvoiceItem::where('nama_item', 'Kasa Steril Uji')->firstOrFail();

        $c->call('removeItem', $item->id)->call('fetchTagihan');

        $this->assertSame(0, PemakaianAlkes::where('kunjungan_id', $this->kunjungan->id)->count());
        $this->assertSame(0, InvoiceItem::where('nama_item', 'Kasa Steril Uji')->count());
    }

    /** @test */
    public function ubah_qty_harus_bulat_dan_tidak_melebihi_stok(): void
    {
        $c = $this->buka()->call('addBhpItem', $this->bhp->id, 2);
        $item = InvoiceItem::where('nama_item', 'Kasa Steril Uji')->firstOrFail();

        $c->set("editQty.{$item->id}", '2.5')->call('updateQtyItem', $item->id);
        $this->assertEquals(2, (float) $item->fresh()->qty, 'Qty desimal ditolak');

        $c->set("editQty.{$item->id}", '99')->call('updateQtyItem', $item->id);
        $this->assertEquals(2, (float) $item->fresh()->qty, 'Qty melebihi stok ditolak');

        $c->set("editQty.{$item->id}", '4')->call('updateQtyItem', $item->id);
        $this->assertEquals(4, (float) $item->fresh()->qty);
        $this->assertSame(4, (int) PemakaianAlkes::where('kunjungan_id', $this->kunjungan->id)->value('jumlah'));
    }

    /** @test */
    public function stok_dipotong_saat_lunas_dengan_mutasi_dan_jurnal_hpp_tanpa_dobel(): void
    {
        $c = $this->buka()->call('addBhpItem', $this->bhp->id, 3);
        $this->bayar($c);

        $inv = Invoice::where('kunjungan_id', $this->kunjungan->id)->firstOrFail();
        $this->assertSame('lunas', $inv->status);
        $this->assertSame(7, (int) $this->bhp->fresh()->stok);

        $mutasi = MutasiStok::where('barang_id', $this->bhp->id)->where('tipe', 'keluar_tindakan')->get();
        $this->assertCount(1, $mutasi);
        $this->assertSame(3, (int) $mutasi->first()->jumlah);
        $this->assertSame(10, (int) $mutasi->first()->stok_sebelum);
        $this->assertSame(7, (int) $mutasi->first()->stok_sesudah);

        $hpp = JurnalPending::where('sumber_tipe', 'billing')->where('sumber_id', $inv->id)
            ->where('tipe_transaksi', 'hpp_bhp_kasir')->get();
        $this->assertCount(1, $hpp);
        $this->assertSame('5-1100', $hpp->first()->kode_akun_debit);
        $this->assertSame('1-1300', $hpp->first()->kode_akun_kredit);
        $this->assertEquals(3000, (float) $hpp->first()->nominal); // 3 x harga_pokok 1000

        // Bayar lagi (klik dobel) tidak memotong stok ulang
        $this->bayar($c);
        $this->assertSame(7, (int) $this->bhp->fresh()->stok);
        $this->assertSame(1, MutasiStok::where('barang_id', $this->bhp->id)->where('tipe', 'keluar_tindakan')->count());
    }

    /** @test */
    public function pembayaran_ditolak_utuh_jika_stok_tidak_cukup_saat_lunas(): void
    {
        $c = $this->buka()->call('addBhpItem', $this->bhp->id, 5);
        $this->bhp->update(['stok' => 2]); // stok berkurang oleh transaksi lain sebelum kasir menekan Bayar

        $this->bayar($c);

        $inv = Invoice::where('kunjungan_id', $this->kunjungan->id)->firstOrFail();
        $this->assertSame('belum_bayar', $inv->status, 'Pembayaran harus dibatalkan utuh');
        $this->assertSame(0, $inv->pembayaranSplit()->count());
        $this->assertSame(2, (int) $this->bhp->fresh()->stok, 'Stok tidak boleh negatif/berubah');
        $this->assertSame(0, MutasiStok::where('barang_id', $this->bhp->id)->where('tipe', 'keluar_tindakan')->count());
    }

    /** @test */
    public function tidak_bisa_menambah_bhp_setelah_lunas(): void
    {
        $c = $this->buka()->call('addBhpItem', $this->bhp->id, 1);
        $this->bayar($c);

        $c->call('addBhpItem', $this->bhp->id, 1);

        $this->assertSame(1, (int) PemakaianAlkes::where('kunjungan_id', $this->kunjungan->id)->value('jumlah'));
        $this->assertSame(9, (int) $this->bhp->fresh()->stok);
    }

    /** @test */
    public function pembatalan_billing_mengembalikan_stok_dan_membalik_jurnal_hpp(): void
    {
        $c = $this->buka()->call('addBhpItem', $this->bhp->id, 3);
        $this->bayar($c);
        $inv = Invoice::where('kunjungan_id', $this->kunjungan->id)->firstOrFail();
        $this->assertSame(7, (int) $this->bhp->fresh()->stok);

        app(BhpKasirService::class)->kembalikanStokSaatBatal($inv->fresh('items'), $this->kasir->id);

        $this->assertSame(10, (int) $this->bhp->fresh()->stok);
        $this->assertSame(1, MutasiStok::where('barang_id', $this->bhp->id)->where('tipe', 'penyesuaian_masuk')->count());
        // Jurnal HPP yang masih pending diabaikan (JurnalService::reversal); yang sudah posted akan dibalik.
        $hpp = JurnalPending::where('sumber_tipe', 'billing')->where('sumber_id', $inv->id)
            ->where('tipe_transaksi', 'hpp_bhp_kasir')->first();
        $this->assertNotSame('pending', $hpp->status);
        $this->assertNull(PemakaianAlkes::where('kunjungan_id', $this->kunjungan->id)->value('stok_dipotong_at'));
    }

    private function buatObat(int $stok = 10): Barang
    {
        return Barang::create([
            'kode' => 'OBT' . rand(1000, 9999), 'nama' => 'Paracetamol Uji', 'jenis' => 'obat',
            'satuan' => 'tab', 'stok' => $stok, 'harga_pokok' => 400, 'harga_jual' => 1000, 'is_active' => true,
        ]);
    }

    /** @test */
    public function tab_obat_menampilkan_obat_aktif_bukan_bhp_dan_sebaliknya(): void
    {
        $this->buatObat();
        $obat = collect(app(BhpKasirService::class)->daftar('Uji', false, 40, 'obat'))->pluck('nama');
        $bhp  = collect(app(BhpKasirService::class)->daftar('Uji', false))->pluck('nama');

        $this->assertTrue($obat->contains('Paracetamol Uji'));
        $this->assertFalse($obat->contains('Kasa Steril Uji'));
        $this->assertTrue($bhp->contains('Kasa Steril Uji'));
        $this->assertFalse($bhp->contains('Paracetamol Uji'));
    }

    /** @test */
    public function obat_kasir_masuk_tagihan_stok_dipotong_saat_lunas_sebagai_penjualan_langsung_dengan_hpp(): void
    {
        $obat = $this->buatObat();
        $c = $this->buka()->call('addObatItem', $obat->id, 4);
        $c->call('fetchTagihan');

        $item = InvoiceItem::where('nama_item', 'Paracetamol Uji')->first();
        $this->assertNotNull($item, 'Obat harus tetap ada setelah muat ulang');
        $this->assertEquals(4000, (float) $item->subtotal);
        $this->assertSame(10, (int) $obat->fresh()->stok, 'Stok belum boleh dipotong sebelum lunas');

        $this->bayar($c);

        $inv = Invoice::where('kunjungan_id', $this->kunjungan->id)->firstOrFail();
        $this->assertSame('lunas', $inv->status);
        $this->assertSame(6, (int) $obat->fresh()->stok);
        $this->assertSame(1, MutasiStok::where('barang_id', $obat->id)->where('tipe', 'keluar_ritel')->count());
        $this->assertEquals(1600, (float) JurnalPending::where('sumber_tipe', 'billing')->where('sumber_id', $inv->id)
            ->where('tipe_transaksi', 'hpp_bhp_kasir')->value('nominal')); // 4 x 400
    }

    /** @test */
    public function jenis_barang_harus_sesuai_tab_dan_racikan_tidak_dilayani(): void
    {
        $obat = $this->buatObat();
        $c = $this->buka();

        $c->call('addObatItem', $this->bhp->id, 1);  // BHP lewat jalur obat
        $c->call('addBhpItem', $obat->id, 1);        // obat lewat jalur BHP
        $this->assertSame(0, PemakaianAlkes::where('kunjungan_id', $this->kunjungan->id)->count());

        $c->call('addObatItem', $obat->id, 11);      // melebihi stok 10
        $this->assertSame(0, PemakaianAlkes::where('kunjungan_id', $this->kunjungan->id)->count());
    }

    /** @test */
    public function label_jenis_di_tagihan_menampilkan_obat_atau_bhp_bukan_alkes(): void
    {
        $obat = $this->buatObat();
        $c = $this->buka()->call('addObatItem', $obat->id, 1)->call('addBhpItem', $this->bhp->id, 1);

        $items = Invoice::where('kunjungan_id', $this->kunjungan->id)->firstOrFail()->items;
        $peta  = InvoiceItem::petaLabelAlkes($items);

        $labelObat = $items->firstWhere('nama_item', 'Paracetamol Uji');
        $labelBhp  = $items->firstWhere('nama_item', 'Kasa Steril Uji');
        $this->assertSame('Obat', $peta[$labelObat->ref_id]);
        $this->assertSame('BHP', $peta[$labelBhp->ref_id]);

        // Tampilan tagihan tidak lagi menyebut 'Alkes' untuk kedua item ini.
        $c->call('fetchTagihan')->assertDontSee('>Alkes<', false)->assertSee('Obat')->assertSee('BHP');
    }
}

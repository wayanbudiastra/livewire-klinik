<?php

namespace Tests\Feature;

use App\Livewire\Kasir\TagihanPasien;
use App\Models\Barang;
use App\Models\Dokter;
use App\Models\InvoiceItem;
use App\Models\ItemResep;
use App\Models\Kunjungan;
use App\Models\Pasien;
use App\Models\Poli;
use App\Models\Resep;
use App\Models\SesiKas;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Permintaan user: diskon per-item sebelumnya sengaja dikecualikan utk
 * baris Obat (lihat prd/modul_kasir.md -- "Kasir dapat memodifikasi item
 * non-obat dan menerapkan diskon") -- diputuskan user utk DIAKTIFKAN jg
 * utk Obat, sama persis spt Tindakan/Penunjang/dll.
 *
 * TagihanPasien::updateDiskonItem() sendiri sebenarnya TIDAK PERNAH punya
 * guard yang mengecualikan jenis 'obat' -- pengecualian itu murni di level
 * tampilan (blade), jadi perbaikannya cuma di blade; method PHP tidak
 * perlu diubah.
 *
 * Item jenis 'obat' di invoice dibangun InvoiceService::buildItems() dari
 * Resep yang sudah is_locked=true (bukan InvoiceItem manual) -- lihat
 * InvoiceService::createOrRefresh() yg MENGHAPUS ULANG semua item
 * non-'manual' tiap kali dipanggil, jadi fixture di sini pakai Resep asli
 * supaya tetap konsisten dgn alur sungguhan (bukan nyisip InvoiceItem
 * manual yg akan langsung kehapus begitu fetchTagihan() jalan lagi).
 *
 * Pakai DatabaseTransactions -- bukan RefreshDatabase.
 */
class DiskonItemObatKasirTest extends TestCase
{
    use DatabaseTransactions;

    private function buatKasir(): User
    {
        $user = User::create([
            'nama' => 'Kasir Test ' . uniqid(), 'email' => 'kasir-' . uniqid() . '@example.test',
            'password' => Hash::make('password'), 'is_active' => true,
        ]);
        $user->assignRole('kasir');
        return $user;
    }

    private function bukaSesiKas(User $kasir): SesiKas
    {
        return SesiKas::create([
            'user_id' => $kasir->id, 'tanggal' => now()->toDateString(),
            'dibuka_pada' => now(), 'saldo_awal' => 0, 'status' => 'buka',
        ]);
    }

    /** Kunjungan 'selesai' + resep obat yang sudah dikunci apoteker (is_locked), supaya InvoiceService::buildItems() benar2 menghasilkan 1 baris jenis 'obat'. */
    private function buatKunjunganDenganResepObat(): array
    {
        $dokterUser = User::create([
            'nama' => 'Dr. Test ' . uniqid(), 'email' => 'dokter-' . uniqid() . '@example.test',
            'password' => Hash::make('password'), 'is_active' => true,
        ]);
        $dokterUser->assignRole('dokter');
        $dokter = Dokter::create(['user_id' => $dokterUser->id]);
        $poli   = Poli::create(['nama' => 'Poli Test ' . uniqid(), 'kode' => 'PT' . rand(1000, 9999), 'is_active' => true]);
        $pasien = Pasien::create([
            'nomor_rm' => 'RM-' . uniqid(), 'nama' => 'Pasien Test ' . uniqid(), 'tempat_lahir' => 'Denpasar',
            'tanggal_lahir' => '1990-01-01', 'jenis_kelamin' => 'L', 'alamat' => 'Jl. Test', 'telepon' => '08123',
        ]);
        $kunjungan = Kunjungan::create([
            'nomor_antrean' => 'W-' . rand(100, 999), 'pasien_id' => $pasien->id,
            'dokter_id' => $dokter->id, 'poli_id' => $poli->id,
            'tanggal' => now(), 'status' => 'selesai',
        ]);

        $obat = Barang::create([
            'kode' => 'SNM' . rand(1000, 9999), 'nama' => 'Sanmol Forte Tablet 650mg', 'jenis' => 'obat',
            'satuan' => "Pc(s)", 'stok' => 100, 'harga_pokok' => 1000, 'harga_jual' => 1704, 'is_active' => true,
        ]);

        $resep = Resep::create([
            'kunjungan_id' => $kunjungan->id, 'dokter_id' => $dokter->id,
            'status' => 'diambil', 'is_locked' => true,
        ]);
        ItemResep::create([
            'resep_id' => $resep->id, 'barang_id' => $obat->id, 'jumlah' => 10, 'aturan_pakai' => '3x1',
        ]);

        return compact('kunjungan', 'obat');
    }

    /** @test */
    public function input_diskon_item_sekarang_tampil_juga_utk_baris_obat(): void
    {
        $kasir = $this->buatKasir();
        $this->bukaSesiKas($kasir);
        ['kunjungan' => $kunjungan] = $this->buatKunjunganDenganResepObat();

        $this->actingAs($kasir);

        $component = Livewire::test(TagihanPasien::class)
            ->call('selectKunjungan', $kunjungan->id);

        $itemObat = InvoiceItem::where('jenis', 'obat')->firstOrFail();
        $component->assertSeeHtml('wire:change="updateDiskonItem(' . $itemObat->id . ')"');
    }

    /** @test */
    public function diskon_item_obat_bisa_disimpan_dan_mengurangi_subtotal(): void
    {
        $kasir = $this->buatKasir();
        $this->bukaSesiKas($kasir);
        ['kunjungan' => $kunjungan] = $this->buatKunjunganDenganResepObat();

        $this->actingAs($kasir);

        // Sengaja SATU instance komponen yg sama dari awal sampai akhir --
        // memanggil selectKunjungan() ulang di instance baru akan memicu
        // createOrRefresh() lagi yg MENGHAPUS & membangun ulang baris 'obat'
        // (id baru), bikin referensi $itemObat basi.
        $component = Livewire::test(TagihanPasien::class)->call('selectKunjungan', $kunjungan->id);

        $itemObat = InvoiceItem::where('jenis', 'obat')->firstOrFail();
        $subtotalAwal = (float) $itemObat->subtotal; // 10 x 1704 = 17040

        $component->set("editDiskon.{$itemObat->id}", '2000')
            ->call('updateDiskonItem', $itemObat->id);

        $fresh = $itemObat->fresh();
        $this->assertEquals(2000, (float) $fresh->diskon_item);
        $this->assertEquals($subtotalAwal - 2000, (float) $fresh->subtotal);
    }

    /** @test */
    public function diskon_item_obat_tidak_boleh_melebihi_subtotal_aslinya(): void
    {
        $kasir = $this->buatKasir();
        $this->bukaSesiKas($kasir);
        ['kunjungan' => $kunjungan] = $this->buatKunjunganDenganResepObat();

        $this->actingAs($kasir);

        $component = Livewire::test(TagihanPasien::class)->call('selectKunjungan', $kunjungan->id);
        $itemObat = InvoiceItem::where('jenis', 'obat')->firstOrFail();
        $subtotalAwal = (float) $itemObat->subtotal;

        $component->set("editDiskon.{$itemObat->id}", '999999')
            ->call('updateDiskonItem', $itemObat->id);

        $fresh = $itemObat->fresh();
        $this->assertEquals($subtotalAwal, (float) $fresh->diskon_item); // dipotong maks subtotal asli
        $this->assertEquals(0, (float) $fresh->subtotal);
    }

    // ─────────────────────────────────────────────────────────────────
    //  Diskon mode PERSEN (permintaan user: opsi % selain fix price)
    //
    //  Konvensi penyimpanan yg dipertahankan: diskon_item & subtotal
    //  SELALU berisi nominal Rp final (subtotal = bruto - diskon_item),
    //  karena recalcTotal(), invoice print, SharingFeeService & retur
    //  resep semuanya sudah mengandalkan konvensi itu. Kolom baru
    //  diskon_tipe/diskon_nilai cuma menyimpan input aslinya supaya
    //  bisa ditampilkan ulang & diedit sbg persen.
    // ─────────────────────────────────────────────────────────────────

    /** @test */
    public function diskon_item_mode_persen_dikonversi_ke_nominal_rp(): void
    {
        $kasir = $this->buatKasir();
        $this->bukaSesiKas($kasir);
        ['kunjungan' => $kunjungan] = $this->buatKunjunganDenganResepObat();

        $this->actingAs($kasir);

        $component = Livewire::test(TagihanPasien::class)->call('selectKunjungan', $kunjungan->id);
        $itemObat = InvoiceItem::where('jenis', 'obat')->firstOrFail();
        $bruto = (float) $itemObat->harga_satuan * (float) $itemObat->qty; // 10 x 1704 = 17040

        $component->set("editDiskonTipe.{$itemObat->id}", 'persen')
            ->set("editDiskon.{$itemObat->id}", '10')
            ->call('updateDiskonItem', $itemObat->id);

        $fresh = $itemObat->fresh();
        $this->assertSame('persen', $fresh->diskon_tipe);
        $this->assertEquals(10, (float) $fresh->diskon_nilai);     // input asli disimpan sbg %
        $this->assertEquals($bruto * 0.10, (float) $fresh->diskon_item); // 1704
        $this->assertEquals($bruto * 0.90, (float) $fresh->subtotal);    // 15336
    }

    /** @test */
    public function diskon_item_mode_persen_di_clamp_maksimal_100_persen(): void
    {
        $kasir = $this->buatKasir();
        $this->bukaSesiKas($kasir);
        ['kunjungan' => $kunjungan] = $this->buatKunjunganDenganResepObat();

        $this->actingAs($kasir);

        $component = Livewire::test(TagihanPasien::class)->call('selectKunjungan', $kunjungan->id);
        $itemObat = InvoiceItem::where('jenis', 'obat')->firstOrFail();
        $bruto = (float) $itemObat->harga_satuan * (float) $itemObat->qty;

        $component->set("editDiskonTipe.{$itemObat->id}", 'persen')
            ->set("editDiskon.{$itemObat->id}", '250') // ngawur, harus dipotong ke 100
            ->call('updateDiskonItem', $itemObat->id);

        $fresh = $itemObat->fresh();
        $this->assertEquals(100, (float) $fresh->diskon_nilai);
        $this->assertEquals($bruto, (float) $fresh->diskon_item);
        $this->assertEquals(0, (float) $fresh->subtotal);
    }

    /** @test */
    public function ganti_tipe_diskon_dari_persen_ke_nominal_menghitung_ulang(): void
    {
        $kasir = $this->buatKasir();
        $this->bukaSesiKas($kasir);
        ['kunjungan' => $kunjungan] = $this->buatKunjunganDenganResepObat();

        $this->actingAs($kasir);

        $component = Livewire::test(TagihanPasien::class)->call('selectKunjungan', $kunjungan->id);
        $itemObat = InvoiceItem::where('jenis', 'obat')->firstOrFail();
        $bruto = (float) $itemObat->harga_satuan * (float) $itemObat->qty;

        // Mulai dgn 50%
        $component->set("editDiskonTipe.{$itemObat->id}", 'persen')
            ->set("editDiskon.{$itemObat->id}", '50')
            ->call('updateDiskonItem', $itemObat->id);
        $this->assertEquals($bruto * 0.5, (float) $itemObat->fresh()->diskon_item);

        // Ganti ke nominal Rp 500 -- angka '50' tdk boleh lagi diartikan sbg persen
        $component->set("editDiskonTipe.{$itemObat->id}", 'nominal')
            ->set("editDiskon.{$itemObat->id}", '500')
            ->call('updateDiskonItem', $itemObat->id);

        $fresh = $itemObat->fresh();
        $this->assertSame('nominal', $fresh->diskon_tipe);
        $this->assertEquals(500, (float) $fresh->diskon_item);
        $this->assertEquals($bruto - 500, (float) $fresh->subtotal);
    }

    /** @test */
    public function diskon_global_mode_persen_dikonversi_dari_subtotal_items(): void
    {
        $kasir = $this->buatKasir();
        $this->bukaSesiKas($kasir);
        ['kunjungan' => $kunjungan] = $this->buatKunjunganDenganResepObat();

        $this->actingAs($kasir);

        $component = Livewire::test(TagihanPasien::class)->call('selectKunjungan', $kunjungan->id);
        $invoice = \App\Models\Invoice::where('kunjungan_id', $kunjungan->id)->firstOrFail();
        $subtotalItems = (float) $invoice->items->sum('subtotal');

        $component->set('diskonGlobalTipe', 'persen')
            ->set('diskonGlobalNominal', '15')
            ->call('applyDiskonGlobal');

        $fresh = $invoice->fresh();
        $this->assertSame('persen', $fresh->diskon_global_tipe);
        $this->assertEquals(15, (float) $fresh->diskon_global_nilai);
        $this->assertEquals($subtotalItems * 0.15, (float) $fresh->diskon_global);
        $this->assertEquals($subtotalItems * 0.85, (float) $fresh->total_tagihan);
    }

    /** @test */
    public function diskon_global_mode_nominal_tetap_berfungsi_spt_sebelumnya(): void
    {
        $kasir = $this->buatKasir();
        $this->bukaSesiKas($kasir);
        ['kunjungan' => $kunjungan] = $this->buatKunjunganDenganResepObat();

        $this->actingAs($kasir);

        $component = Livewire::test(TagihanPasien::class)->call('selectKunjungan', $kunjungan->id);
        $invoice = \App\Models\Invoice::where('kunjungan_id', $kunjungan->id)->firstOrFail();
        $subtotalItems = (float) $invoice->items->sum('subtotal');

        $component->set('diskonGlobalTipe', 'nominal')
            ->set('diskonGlobalNominal', '5000')
            ->call('applyDiskonGlobal');

        $fresh = $invoice->fresh();
        $this->assertSame('nominal', $fresh->diskon_global_tipe);
        $this->assertEquals(5000, (float) $fresh->diskon_global);
        $this->assertEquals($subtotalItems - 5000, (float) $fresh->total_tagihan);
    }

    /** @test */
    public function diskon_item_persen_dan_diskon_global_persen_bisa_digabung(): void
    {
        $kasir = $this->buatKasir();
        $this->bukaSesiKas($kasir);
        ['kunjungan' => $kunjungan] = $this->buatKunjunganDenganResepObat();

        $this->actingAs($kasir);

        $component = Livewire::test(TagihanPasien::class)->call('selectKunjungan', $kunjungan->id);
        $itemObat = InvoiceItem::where('jenis', 'obat')->firstOrFail();
        $bruto = (float) $itemObat->harga_satuan * (float) $itemObat->qty; // 17040

        // Diskon item 10% -> subtotal item jadi 15336
        $component->set("editDiskonTipe.{$itemObat->id}", 'persen')
            ->set("editDiskon.{$itemObat->id}", '10')
            ->call('updateDiskonItem', $itemObat->id);

        // Diskon global 10% dihitung dari subtotal items yg SUDAH net diskon item
        $component->set('diskonGlobalTipe', 'persen')
            ->set('diskonGlobalNominal', '10')
            ->call('applyDiskonGlobal');

        $invoice = \App\Models\Invoice::where('kunjungan_id', $kunjungan->id)->firstOrFail();
        $netItem = $bruto * 0.90;

        $this->assertEquals($netItem * 0.10, (float) $invoice->diskon_global);
        $this->assertEquals($netItem * 0.90, (float) $invoice->total_tagihan);
    }
}

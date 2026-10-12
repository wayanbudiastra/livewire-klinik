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
}

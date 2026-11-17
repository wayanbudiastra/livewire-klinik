<?php

namespace Tests\Feature;

use App\Livewire\Kasir\TagihanPasien;
use App\Models\Dokter;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Kunjungan;
use App\Models\MasterTindakan;
use App\Models\Pasien;
use App\Models\Poli;
use App\Models\SesiKas;
use App\Models\Tindakan;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Bug: item yang ditambah kasir dari panel "Tambah Komponen Tagihan"
 * (tindakan/peralatan/lab/radiologi; ref_id kosong) ikut terhapus setiap
 * tagihan dimuat ulang, karena InvoiceService::createOrRefresh() menghapus
 * semua item non-'manual'. Sekarang hanya item turunan klinis (ref_id terisi)
 * yang dibangun ulang. Pakai DatabaseTransactions.
 */
class KomponenPanelTahanMuatUlangTest extends TestCase
{
    use DatabaseTransactions;

    private Kunjungan $kunjungan;

    protected function setUp(): void
    {
        parent::setUp();

        $kasir = User::create(['nama' => 'Kasir ' . uniqid(), 'email' => uniqid() . '@x.test', 'password' => Hash::make('p'), 'is_active' => true]);
        $kasir->assignRole('kasir');
        SesiKas::create(['user_id' => $kasir->id, 'tanggal' => now()->toDateString(), 'dibuka_pada' => now(), 'saldo_awal' => 0, 'status' => 'buka']);

        $du = User::create(['nama' => 'Dr ' . uniqid(), 'email' => uniqid() . '@x.test', 'password' => Hash::make('p'), 'is_active' => true]);
        $dokter = Dokter::create(['user_id' => $du->id]);
        $poli   = Poli::create(['nama' => 'Poli ' . uniqid(), 'kode' => 'P' . rand(1000, 9999), 'is_active' => true]);
        $pasien = Pasien::create(['nomor_rm' => 'RM-' . uniqid(), 'nama' => 'Pasien Uji', 'tempat_lahir' => 'X', 'tanggal_lahir' => '1990-01-01', 'jenis_kelamin' => 'L', 'alamat' => 'x', 'telepon' => '08']);
        $this->kunjungan = Kunjungan::create([
            'nomor_antrean' => 'W-' . rand(100, 999), 'pasien_id' => $pasien->id, 'dokter_id' => $dokter->id,
            'poli_id' => $poli->id, 'tanggal' => now(), 'status' => 'selesai',
        ]);

        $this->actingAs($kasir);
    }

    private function buka()
    {
        return Livewire::test(TagihanPasien::class)->call('selectKunjungan', $this->kunjungan->id);
    }

    /** @test */
    public function item_panel_tetap_ada_setelah_tagihan_dimuat_ulang(): void
    {
        $c = $this->buka();
        foreach (['prosedur' => 'Item Prosedur Uji', 'lab' => 'Item Lab Uji', 'radiologi' => 'Item Rad Uji', 'peralatan' => 'Item Alat Uji'] as $tab => $nama) {
            $c->set('komponenTab', $tab)->call('addKomponenItem', 1, $nama, 50000, 'x', 2);
        }
        $totalSebelum = (float) Invoice::where('kunjungan_id', $this->kunjungan->id)->value('total_tagihan');
        $this->assertEquals(400000, $totalSebelum);

        $c->call('fetchTagihan');
        $this->buka(); // buka ulang dari komponen baru

        $this->assertSame(4, InvoiceItem::whereIn('nama_item', ['Item Prosedur Uji', 'Item Lab Uji', 'Item Rad Uji', 'Item Alat Uji'])->count());
        $this->assertEquals($totalSebelum, (float) Invoice::where('kunjungan_id', $this->kunjungan->id)->value('total_tagihan'));
    }

    /** @test */
    public function item_klinis_tetap_disinkronkan_dan_tidak_menduplikasi_item_panel(): void
    {
        $master = MasterTindakan::create(['kode' => 'T' . rand(1000, 9999), 'nama' => 'Tindakan Klinis Uji', 'tarif' => 100000, 'is_active' => true]);
        $tindakan = Tindakan::create([
            'kunjungan_id' => $this->kunjungan->id, 'master_tindakan_id' => $master->id,
            'jumlah' => 1, 'waktu_tindakan' => now(),
        ]);

        $c = $this->buka();
        $c->set('komponenTab', 'prosedur')->call('addKomponenItem', 1, 'Item Panel Uji', 50000, 'tindakan', 1);
        $c->call('fetchTagihan')->call('fetchTagihan');

        $this->assertSame(1, InvoiceItem::where('nama_item', 'Tindakan Klinis Uji')->count(), 'Item klinis tidak boleh dobel');
        $this->assertSame(1, InvoiceItem::where('nama_item', 'Item Panel Uji')->count());

        // Dokter menghapus tindakan -> item klinis hilang saat dimuat ulang, item panel tetap.
        $tindakan->delete();
        $c->call('fetchTagihan');

        $this->assertSame(0, InvoiceItem::where('nama_item', 'Tindakan Klinis Uji')->count());
        $this->assertSame(1, InvoiceItem::where('nama_item', 'Item Panel Uji')->count());
    }
}

<?php

namespace Tests\Feature;

use App\Livewire\Pemeriksaan\Penunjang;
use App\Models\Dokter;
use App\Models\Invoice;
use App\Models\ItemPenunjang;
use App\Models\Kunjungan;
use App\Models\Pasien;
use App\Models\PermintaanPenunjang;
use App\Models\Poli;
use App\Models\SesiKas;
use App\Models\User;
use App\Services\InvoiceService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Lab dikerjakan pihak ketiga & dibayar di hari H: order lab dari SOAP harus
 * langsung 'selesai' (tertagih) dan masuk bill. Sebelumnya berstatus 'dipesan'
 * sehingga InvoiceService (hanya menagih 'selesai') menghasilkan tagihan Rp0.
 *
 * RefreshDatabase -> jalan di database emr_test (lihat phpunit.xml).
 */
class LabSoapTagihanTest extends TestCase
{
    use RefreshDatabase;

    private User $dokterUser;
    private Kunjungan $kunjungan;
    private SesiKas $sesi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->dokterUser = User::create([
            'nama' => 'Dr ' . uniqid(), 'email' => uniqid() . '@example.test',
            'password' => Hash::make('password'), 'is_active' => true,
        ]);
        $this->dokterUser->assignRole('dokter');
        $dokter = Dokter::create(['user_id' => $this->dokterUser->id]);
        $poli   = Poli::create(['nama' => 'Poli ' . uniqid(), 'kode' => 'P' . rand(1000, 9999), 'is_active' => true]);
        $pasien = Pasien::create([
            'nomor_rm' => 'RM-' . uniqid(), 'nama' => 'Pasien ' . uniqid(), 'tempat_lahir' => 'Denpasar',
            'tanggal_lahir' => '1990-01-01', 'jenis_kelamin' => 'L', 'alamat' => 'Jl. Test', 'telepon' => '08123',
        ]);
        $this->kunjungan = Kunjungan::create([
            'nomor_antrean' => 'W-' . rand(100, 999), 'pasien_id' => $pasien->id,
            'dokter_id' => $dokter->id, 'poli_id' => $poli->id, 'tanggal' => now(), 'status' => 'dalam_pemeriksaan',
        ]);
        $this->sesi = SesiKas::create([
            'user_id' => $this->dokterUser->id, 'tanggal' => today(), 'dibuka_pada' => now(), 'saldo_awal' => 0, 'status' => 'buka',
        ]);
    }

    private function itemLab(): ItemPenunjang
    {
        return ItemPenunjang::create([
            'kode' => 'LAB' . rand(1000, 9999), 'nama' => 'Darah Lengkap', 'kategori' => 'lab', 'tarif' => 85000, 'is_active' => true,
        ]);
    }

    private function orderLab(ItemPenunjang $item): PermintaanPenunjang
    {
        $this->actingAs($this->dokterUser);
        Livewire::test(Penunjang::class, ['kunjunganId' => $this->kunjungan->id])
            ->set('cartLab', [['id' => $item->id, 'kode' => $item->kode, 'nama' => $item->nama, 'tarif' => '85000', 'prioritas' => 'normal', 'catatan' => '']])
            ->call('submitLab');

        return PermintaanPenunjang::firstOrFail();
    }

    public function test_order_lab_soap_langsung_selesai_dan_masuk_bill(): void
    {
        $order = $this->orderLab($this->itemLab());

        $this->assertSame('selesai', $order->status);
        $this->assertNull($order->hasil_diterima_at, 'hasil dari pihak ketiga belum diterima');

        $invoice = app(InvoiceService::class)->createOrRefresh($this->kunjungan->fresh(), $this->sesi);
        $this->assertSame(1, $invoice->items->where('jenis', 'penunjang')->count());
        $this->assertEquals(85000, $invoice->total_tagihan);
    }

    public function test_order_lab_bisa_dibatalkan_selama_bill_belum_lunas_dan_keluar_dari_tagihan(): void
    {
        $order = $this->orderLab($this->itemLab());

        Livewire::test(Penunjang::class, ['kunjunganId' => $this->kunjungan->id])->call('batalkan', $order->id);

        $this->assertSame('dibatalkan', $order->fresh()->status);
        $invoice = app(InvoiceService::class)->createOrRefresh($this->kunjungan->fresh(), $this->sesi);
        $this->assertEquals(0, $invoice->total_tagihan);
    }

    public function test_order_lab_tidak_bisa_dibatalkan_setelah_bill_lunas(): void
    {
        $order = $this->orderLab($this->itemLab());
        $invoice = app(InvoiceService::class)->createOrRefresh($this->kunjungan->fresh(), $this->sesi);
        $invoice->update(['status' => 'lunas']);

        Livewire::test(Penunjang::class, ['kunjunganId' => $this->kunjungan->id])->call('batalkan', $order->id);

        $this->assertSame('selesai', $order->fresh()->status);
    }

    public function test_radiologi_tetap_dipesan_dan_tidak_ikut_tertagih(): void
    {
        $rad = ItemPenunjang::create([
            'kode' => 'RAD' . rand(1000, 9999), 'nama' => 'Rontgen Thorax', 'kategori' => 'radiologi', 'tarif' => 150000, 'is_active' => true,
        ]);
        $this->actingAs($this->dokterUser);
        Livewire::test(Penunjang::class, ['kunjunganId' => $this->kunjungan->id])
            ->set('cartRad', [['id' => $rad->id, 'kode' => $rad->kode, 'nama' => $rad->nama, 'tarif' => '150000', 'lokasi_tubuh' => 'Thorax', 'indikasi_klinis' => 'Batuk']])
            ->call('submitRad');

        $this->assertSame('dipesan', PermintaanPenunjang::firstOrFail()->status);
    }
}

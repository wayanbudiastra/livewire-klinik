<?php

namespace Tests\Feature;

use App\Livewire\Kasir\Susulan\SusulanKasir;
use App\Models\Dokter;
use App\Models\Invoice;
use App\Models\ItemPenunjang;
use App\Models\Kunjungan;
use App\Models\Pasien;
use App\Models\Poli;
use App\Models\SesiKas;
use App\Models\TransaksiRitel;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/** Halaman kasir Order Susulan (RefreshDatabase -> emr_test, lihat phpunit.xml). */
class SusulanKasirTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        $u = User::create([
            'nama' => $role . uniqid(), 'email' => $role . uniqid() . '@example.test',
            'password' => Hash::make('password'), 'is_active' => true,
        ]);
        $u->assignRole($role);
        return $u;
    }

    private function data(): array
    {
        $this->seed(RolePermissionSeeder::class);
        $dokter = Dokter::create(['user_id' => $this->user('dokter')->id]);
        $poli   = Poli::create(['nama' => 'Poli ' . uniqid(), 'kode' => 'P' . rand(1000, 9999), 'is_active' => true]);
        $pasien = Pasien::create([
            'nomor_rm' => 'RM-' . uniqid(), 'nama' => 'Pasien Susulan', 'tempat_lahir' => 'Denpasar',
            'tanggal_lahir' => '1990-01-01', 'jenis_kelamin' => 'L', 'alamat' => 'Jl. Test', 'telepon' => '08123',
        ]);
        $k = Kunjungan::create([
            'nomor_antrean' => 'W-' . rand(100, 999), 'pasien_id' => $pasien->id, 'dokter_id' => $dokter->id,
            'poli_id' => $poli->id, 'tanggal' => now()->subDay(), 'status' => 'selesai',
        ]);
        Invoice::create([
            'kunjungan_id' => $k->id, 'nomor_invoice' => 'INV-' . uniqid(),
            'total_tagihan' => 1000, 'total_bayar' => 1000, 'sisa' => 0, 'status' => 'lunas',
        ]);
        $lab = ItemPenunjang::create(['kode' => 'LAB1', 'nama' => 'Darah Lengkap', 'kategori' => 'lab', 'tarif' => 85000, 'is_active' => true]);

        return compact('dokter', 'pasien', 'k', 'lab');
    }

    public function test_hanya_kasir_yang_boleh_membuka_halaman_susulan(): void
    {
        $d = $this->data();

        $this->actingAs($this->user('dokter'));
        Livewire::test(SusulanKasir::class)->assertForbidden();

        $this->actingAs($this->user('kasir'));
        Livewire::test(SusulanKasir::class)->assertOk();
    }

    public function test_kasir_membuat_dan_membayar_susulan_lab(): void
    {
        $d = $this->data();
        $kasir = $this->user('kasir');
        SesiKas::create(['user_id' => $kasir->id, 'tanggal' => today(), 'dibuka_pada' => now(), 'saldo_awal' => 0, 'status' => 'buka']);
        $this->actingAs($kasir);

        $c = Livewire::test(SusulanKasir::class)
            ->call('pilihPasien', $d['pasien']->id)
            ->set('kunjunganId', $d['k']->id)
            ->set('dokterId', $d['dokter']->id)
            ->set('jenis', 'lab')
            ->call('tambahItem', $d['lab']->id, 'Darah Lengkap', 85000)
            ->call('simpan')
            ->assertHasNoErrors();

        $tr = TransaksiRitel::susulan()->firstOrFail();
        $this->assertSame('menunggu_kasir', $tr->status);
        $this->assertSame($d['dokter']->id, $tr->dokter_id);

        $c->call('bukaBayar', $tr->id)->set('totalBayarInput', '85000')->call('bayar')->assertHasNoErrors();
        $this->assertSame('selesai', $tr->fresh()->status);
    }

    public function test_tanpa_dokter_penyaran_ditolak_dengan_pesan(): void
    {
        $d = $this->data();
        $this->actingAs($this->user('kasir'));

        Livewire::test(SusulanKasir::class)
            ->call('pilihPasien', $d['pasien']->id)
            ->set('kunjunganId', $d['k']->id)
            ->call('tambahItem', $d['lab']->id, 'Darah Lengkap', 85000)
            ->call('simpan')
            ->assertHasErrors('simpan');

        $this->assertSame(0, TransaksiRitel::susulan()->count());
    }

    public function test_kunjungan_dengan_bill_belum_lunas_tidak_ditawarkan(): void
    {
        $d = $this->data();
        Invoice::where('kunjungan_id', $d['k']->id)->update(['status' => 'belum_bayar']);
        $this->actingAs($this->user('kasir'));

        $c = Livewire::test(SusulanKasir::class)->call('pilihPasien', $d['pasien']->id);
        $this->assertCount(0, $c->get('kunjunganLayak'));
    }
}

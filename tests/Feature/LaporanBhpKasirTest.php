<?php

namespace Tests\Feature;

use App\Livewire\Laporan\Kasir\BhpKasirReport;
use App\Models\Barang;
use App\Models\Dokter;
use App\Models\Kunjungan;
use App\Models\Pasien;
use App\Models\PemakaianAlkes;
use App\Models\Poli;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Laporan baca-saja "BHP Kasir" (docs/PERANCANGAN_BHP_KASIR.md): hanya
 * pemakaian_alkes sumber 'kasir', per periode, dengan scope per-kasir
 * (tanpa laporan.kasir.view_all). Pakai DatabaseTransactions.
 */
class LaporanBhpKasirTest extends TestCase
{
    use DatabaseTransactions;

    private Kunjungan $kunjungan;
    private Barang $bhp;

    protected function setUp(): void
    {
        parent::setUp();

        $du = User::create(['nama' => 'Dr ' . uniqid(), 'email' => uniqid() . '@x.test', 'password' => Hash::make('p'), 'is_active' => true]);
        $dokter = Dokter::create(['user_id' => $du->id]);
        $poli   = Poli::create(['nama' => 'Poli ' . uniqid(), 'kode' => 'P' . rand(1000, 9999), 'is_active' => true]);
        $pasien = Pasien::create(['nomor_rm' => 'RM-' . uniqid(), 'nama' => 'Pasien Laporan', 'tempat_lahir' => 'X', 'tanggal_lahir' => '1990-01-01', 'jenis_kelamin' => 'L', 'alamat' => 'x', 'telepon' => '08']);
        $this->kunjungan = Kunjungan::create([
            'nomor_antrean' => 'W-' . rand(100, 999), 'pasien_id' => $pasien->id, 'dokter_id' => $dokter->id,
            'poli_id' => $poli->id, 'tanggal' => now(), 'status' => 'selesai',
        ]);
        $this->bhp = Barang::create([
            'kode' => 'BHP' . rand(1000, 9999), 'nama' => 'Kasa Laporan Uji', 'jenis' => 'bahan_habis_pakai',
            'satuan' => 'pcs', 'stok' => 50, 'harga_pokok' => 1000, 'harga_jual' => 2500, 'is_active' => true,
        ]);
    }

    private function user(string $role, array $extra = []): User
    {
        $u = User::create(['nama' => ucfirst($role) . ' ' . uniqid(), 'email' => uniqid() . '@x.test', 'password' => Hash::make('p'), 'is_active' => true]);
        $u->assignRole($role);
        foreach ($extra as $p) $u->givePermissionTo($p);
        return $u;
    }

    private function baris(string $sumber, ?User $pencatat, int $qty = 2, $dipotong = null): PemakaianAlkes
    {
        return PemakaianAlkes::create([
            'kunjungan_id' => $this->kunjungan->id, 'barang_id' => $this->bhp->id, 'jumlah' => $qty,
            'sumber' => $sumber, 'dicatat_oleh' => $pencatat?->id, 'stok_dipotong_at' => $dipotong,
        ]);
    }

    private function laporan(User $u)
    {
        $this->actingAs($u);
        return Livewire::test(BhpKasirReport::class)->call('generate');
    }

    /** @test */
    public function halaman_dibuka_pemegang_laporan_kasir_view_dan_ditolak_yang_lain(): void
    {
        $this->actingAs($this->user('kasir'))->get(route('laporan.kasir.bhp'))
            ->assertOk()->assertSee('BHP Kasir');

        $this->actingAs($this->user('perawat'))->get(route('laporan.kasir.bhp'))->assertForbidden();
    }

    /** @test */
    public function hanya_baris_sumber_kasir_yang_tampil(): void
    {
        $kasir = $this->user('kasir');
        $this->baris('kasir', $kasir, 3);
        $this->baris('dokter', null, 7);

        $c = $this->laporan($kasir);
        $hasil = $c->get('hasil');

        $this->assertSame(1, $hasil['total_baris']);
        $this->assertSame(3, $hasil['total_qty']);
        $this->assertSame('Kasa Laporan Uji', $hasil['detail'][0]['barang']);
        $c->assertSee('Kasa Laporan Uji')->assertSee('Pasien Laporan')->assertSee('Menunggu lunas');
    }

    /** @test */
    public function filter_periode_mengecualikan_baris_di_luar_periode(): void
    {
        $kasir = $this->user('kasir');
        $this->baris('kasir', $kasir, 1);
        $lama = $this->baris('kasir', $kasir, 5);
        $lama->forceFill(['created_at' => now()->subMonths(3)])->saveQuietly();

        $hasil = $this->laporan($kasir)->get('hasil');

        $this->assertSame(1, $hasil['total_baris']);
        $this->assertSame(1, $hasil['total_qty']);
    }

    /** @test */
    public function status_menunggu_lunas_atau_stok_dipotong(): void
    {
        $kasir = $this->user('kasir');
        $this->baris('kasir', $kasir, 1);
        $this->baris('kasir', $kasir, 1, now());

        $status = collect($this->laporan($kasir)->get('hasil')['detail'])->pluck('status');

        $this->assertTrue($status->contains('Menunggu lunas'));
        $this->assertTrue($status->contains(fn ($s) => str_starts_with($s, 'Stok dipotong')));
    }

    /** @test */
    public function kasir_tanpa_view_all_hanya_melihat_miliknya_dan_view_all_melihat_semua(): void
    {
        $kasirA = $this->user('kasir');
        $kasirB = $this->user('kasir');
        $this->baris('kasir', $kasirA, 1);
        $this->baris('kasir', $kasirB, 4);

        $this->assertSame(1, $this->laporan($kasirA)->get('hasil')['total_baris']);

        $semua = $this->user('kasir', ['laporan.kasir.view_all']);
        $this->assertSame(2, $this->laporan($semua)->get('hasil')['total_baris']);
    }

    /** @test */
    public function laporan_baca_saja_tidak_mengubah_data(): void
    {
        $kasir = $this->user('kasir');
        $this->baris('kasir', $kasir, 2);
        $stok = $this->bhp->fresh()->stok;
        $jumlahBaris = PemakaianAlkes::count();

        $this->laporan($kasir);

        $this->assertSame($jumlahBaris, PemakaianAlkes::count());
        $this->assertSame($stok, $this->bhp->fresh()->stok);
    }
}

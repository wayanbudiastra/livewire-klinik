<?php

namespace Tests\Feature;

use App\Livewire\Pemeriksaan\WaitingArea;
use App\Models\Dokter;
use App\Models\Kunjungan;
use App\Models\Pasien;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Tab Waiting Area (permintaan user): filter tanggal diganti dari 1 tanggal
 * jadi rentang tanggal (tanggalMulai s/d tanggalAkhir), dibatasi maksimal
 * 1 bulan (31 hari) supaya query tidak jadi terlalu berat.
 *
 * Pakai DatabaseTransactions -- lihat catatan yang sama di
 * SensitiveActionAuthorizationTest.php soal kenapa bukan RefreshDatabase.
 */
class WaitingAreaRentangTanggalTest extends TestCase
{
    use DatabaseTransactions;

    private function login(): void
    {
        $user = User::role('super_admin')->first() ?? User::role('admin')->first();
        $this->actingAs($user);
    }

    private function buatKunjunganPadaTanggal(\DateTimeInterface|string $tanggal): Kunjungan
    {
        $dokterUser = User::create([
            'nama' => 'Dr. Test ' . uniqid(), 'email' => 'dokter-' . uniqid() . '@example.test',
            'password' => Hash::make('password'), 'is_active' => true,
        ]);
        $dokterUser->assignRole('dokter');
        $dokter = Dokter::create(['user_id' => $dokterUser->id]);

        $pasien = Pasien::create([
            'nomor_rm' => 'RM-' . uniqid(), 'nama' => 'Pasien Test ' . uniqid(), 'tempat_lahir' => 'Denpasar',
            'tanggal_lahir' => '1990-01-01', 'jenis_kelamin' => 'L', 'alamat' => 'Jl. Test', 'telepon' => '08123',
        ]);

        return Kunjungan::create([
            'nomor_antrean' => 'A-' . uniqid(), 'pasien_id' => $pasien->id, 'dokter_id' => $dokter->id,
            'status' => 'menunggu', 'tanggal' => $tanggal,
        ]);
    }

    /** @test */
    public function default_mount_tanggalmulai_dan_akhir_sama_dengan_hari_ini(): void
    {
        $this->login();

        Livewire::test(WaitingArea::class)
            ->assertSet('tanggalMulai', now()->toDateString())
            ->assertSet('tanggalAkhir', now()->toDateString());
    }

    /** @test */
    public function kunjungan_dalam_rentang_tanggal_ikut_tampil(): void
    {
        $this->login();
        $k1 = $this->buatKunjunganPadaTanggal(now()->subDays(3));
        $k2 = $this->buatKunjunganPadaTanggal(now()->subDays(1));
        $k3 = $this->buatKunjunganPadaTanggal(now()->subDays(10)); // di luar rentang

        $hasil = Livewire::test(WaitingArea::class)
            ->set('filterStatus', '')
            ->set('tanggalMulai', now()->subDays(5)->toDateString())
            ->set('tanggalAkhir', now()->toDateString())
            ->get('kunjungan');

        $ids = $hasil->pluck('id');
        $this->assertTrue($ids->contains($k1->id));
        $this->assertTrue($ids->contains($k2->id));
        $this->assertFalse($ids->contains($k3->id));
    }

    /** @test */
    public function rentang_lebih_dari_1_bulan_otomatis_dipangkas(): void
    {
        $this->login();

        Livewire::test(WaitingArea::class)
            ->set('tanggalMulai', '2026-01-01')
            ->set('tanggalAkhir', '2026-06-01') // jauh lebih dari 31 hari
            ->assertSet('tanggalAkhir', '2026-01-31');
    }

    /** @test */
    public function tanggal_akhir_sebelum_mulai_otomatis_disamakan(): void
    {
        $this->login();

        Livewire::test(WaitingArea::class)
            ->set('tanggalMulai', '2026-03-15')
            ->set('tanggalAkhir', '2026-03-01') // sebelum mulai
            ->assertSet('tanggalAkhir', '2026-03-15');
    }

    /** @test */
    public function rentang_pas_31_hari_tidak_dipangkas(): void
    {
        $this->login();

        Livewire::test(WaitingArea::class)
            ->set('tanggalMulai', '2026-01-01')
            ->set('tanggalAkhir', '2026-01-31') // pas 31 hari (batas maksimal)
            ->assertSet('tanggalAkhir', '2026-01-31');
    }
}

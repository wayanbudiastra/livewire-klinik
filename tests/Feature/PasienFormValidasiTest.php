<?php

namespace Tests\Feature;

use App\Livewire\Pasien\PasienForm;
use App\Models\Pasien;
use App\Models\User;
use App\Services\PasienService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regresi untuk pelonggaran validasi form Pasien (permintaan user):
 * - Alamat: wajib diisi, tapi TIDAK ada lagi minimal 10 karakter.
 * - No. HP: wajib diisi, tapi TIDAK dipaksa format Indonesia (08xx) saja --
 *   nomor luar negeri (pasien WNA) juga harus diterima.
 */
class PasienFormValidasiTest extends TestCase
{
    use DatabaseTransactions;

    private function loginSebagaiAdmin(): User
    {
        $user = User::role('admin')->first() ?? User::role('super_admin')->first();
        $this->actingAs($user);
        return $user;
    }

    /** @test */
    public function alamat_pendek_tidak_lagi_ditolak_selama_tidak_kosong(): void
    {
        $this->loginSebagaiAdmin();

        Livewire::test(PasienForm::class)
            ->set('alamat', 'CANGGU') // 6 karakter, dulu ditolak (min:10)
            ->call('save')
            ->assertHasNoErrors(['alamat']);
    }

    /** @test */
    public function alamat_kosong_tetap_ditolak(): void
    {
        $this->loginSebagaiAdmin();

        Livewire::test(PasienForm::class)
            ->set('alamat', '')
            ->call('save')
            ->assertHasErrors(['alamat' => 'required']);
    }

    /** @test */
    public function nomor_hp_internasional_pasien_wna_diterima(): void
    {
        $this->loginSebagaiAdmin();

        Livewire::test(PasienForm::class)
            ->set('tipe_pasien', 'WNA')
            ->set('telepon', '+31612345678') // nomor Belanda, dulu ditolak
            ->call('save')
            ->assertHasNoErrors(['telepon']);
    }

    /** @test */
    public function nomor_hp_format_indonesia_masih_diterima(): void
    {
        $this->loginSebagaiAdmin();

        Livewire::test(PasienForm::class)
            ->set('telepon', '081234567890')
            ->call('save')
            ->assertHasNoErrors(['telepon']);
    }

    /** @test */
    public function nomor_hp_terlalu_pendek_tetap_ditolak(): void
    {
        $this->loginSebagaiAdmin();

        Livewire::test(PasienForm::class)
            ->set('telepon', '00123') // dari screenshot user -- harus tetap invalid
            ->call('save')
            ->assertHasErrors(['telepon']);
    }

    /** @test */
    public function nomor_hp_kosong_tetap_ditolak(): void
    {
        $this->loginSebagaiAdmin();

        Livewire::test(PasienForm::class)
            ->set('telepon', '')
            ->call('save')
            ->assertHasErrors(['telepon' => 'required']);
    }

    // ── Edit tipe pasien WNI <-> WNA ──────────────────────────────

    private function buatPasien(array $override = []): Pasien
    {
        return app(PasienService::class)->create(
            array_merge([
                'nama' => 'Pasien Tipe Test', 'tempat_lahir' => 'Denpasar',
                'tanggal_lahir' => '1990-01-01', 'jenis_kelamin' => 'L',
                'tipe_pasien' => 'WNI', 'nik' => (string) random_int(3100000000000000, 3199999999999999),
                'alamat' => 'Jl. Test', 'telepon' => '081234567890',
            ], $override),
            [
                ['nama' => 'Keluarga', 'nomor_hp' => '082234567890', 'hubungan' => 'lainnya'],
            ]
        );
    }

    /** @test */
    public function edit_pasien_wni_menjadi_wna_berhasil_dan_nik_dikosongkan(): void
    {
        $this->loginSebagaiAdmin();
        $pasien = $this->buatPasien();
        $paspor = 'X' . random_int(1000000, 9999999);

        Livewire::test(PasienForm::class, ['pasienId' => $pasien->id])
            ->assertSet('tipe_pasien', 'WNI')
            ->set('tipe_pasien', 'WNA')
            ->assertSet('nik', '')
            ->set('no_paspor', $paspor)
            ->set('negara_asal', 'Belanda')
            ->call('save')
            ->assertHasNoErrors();

        $pasien->refresh();
        $this->assertSame('WNA', $pasien->tipe_pasien);
        $this->assertNull($pasien->nik);
        $this->assertNull($pasien->no_bpjs);
        $this->assertSame($paspor, $pasien->no_paspor);
        $this->assertSame('Belanda', $pasien->negara_asal);
    }

    /** @test */
    public function edit_pasien_wna_menjadi_wni_berhasil_dan_paspor_dikosongkan(): void
    {
        $this->loginSebagaiAdmin();
        $pasien = $this->buatPasien([
            'tipe_pasien' => 'WNA', 'nik' => null,
            'no_paspor' => 'Y' . random_int(1000000, 9999999), 'negara_asal' => 'Jepang',
        ]);
        $nik = (string) random_int(3100000000000000, 3199999999999999);

        Livewire::test(PasienForm::class, ['pasienId' => $pasien->id])
            ->assertSet('tipe_pasien', 'WNA')
            ->set('tipe_pasien', 'WNI')
            ->set('nik', $nik)
            ->call('save')
            ->assertHasNoErrors();

        $pasien->refresh();
        $this->assertSame('WNI', $pasien->tipe_pasien);
        $this->assertSame($nik, $pasien->nik);
        $this->assertNull($pasien->no_paspor);
        $this->assertNull($pasien->negara_asal);
    }

    /** @test */
    public function batal_ganti_tipe_saat_edit_memulihkan_identitas_asal(): void
    {
        $this->loginSebagaiAdmin();
        $pasien = $this->buatPasien();

        Livewire::test(PasienForm::class, ['pasienId' => $pasien->id])
            ->set('tipe_pasien', 'WNA')
            ->assertSet('nik', '')
            ->set('tipe_pasien', 'WNI')
            ->assertSet('nik', $pasien->nik);
    }

    // ── Riwayat Alergi Mandatory ──────────────────────────────────────

    /** @test */
    public function riwayat_alergi_wajib_diisi(): void
    {
        $this->loginSebagaiAdmin();

        Livewire::test(PasienForm::class)
            ->set('alergi', '')
            ->call('save')
            ->assertHasErrors(['alergi' => 'required']);
    }

    /** @test */
    public function riwayat_alergi_boleh_tidak_ada_alergi(): void
    {
        $this->loginSebagaiAdmin();

        Livewire::test(PasienForm::class)
            ->set('alergi', 'Tidak ada alergi')
            ->call('save')
            ->assertHasNoErrors(['alergi']);
    }

    /** @test */
    public function riwayat_alergi_boleh_spesifik(): void
    {
        $this->loginSebagaiAdmin();

        Livewire::test(PasienForm::class)
            ->set('alergi', 'Penisilin, Seafood')
            ->call('save')
            ->assertHasNoErrors(['alergi']);
    }
}

<?php

namespace Tests\Feature;

use App\Livewire\Pasien\PasienForm;
use App\Models\User;
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
}

<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Regresi menu khusus "SatuSehat" (dipisah dari submenu Pengaturan) --
 * berisi 2 proses: Setup IHS (kredensial & tes koneksi, sudah ada
 * sebelumnya sbg "Konfigurasi SatuSehat") dan Pengiriman Data (placeholder
 * coming-soon, detail fungsional menyusul di PRD terpisah).
 *
 * Route lama /pengaturan/satusehat (name: pengaturan.satusehat) dibiarkan
 * sbg redirect ke satusehat.setup supaya bookmark/link lama tetap jalan.
 *
 * Pakai DatabaseTransactions -- bukan RefreshDatabase.
 */
class SatuSehatMenuTest extends TestCase
{
    use DatabaseTransactions;

    private function buatUserDenganRole(string $role): User
    {
        $user = User::create([
            'nama' => ucfirst($role) . ' Test ' . uniqid(),
            'email' => strtolower($role) . '-' . uniqid() . '@example.test',
            'password' => Hash::make('password'), 'is_active' => true,
        ]);
        $user->assignRole($role);
        return $user;
    }

    /** @test */
    public function admin_bisa_akses_setup_ihs_dan_pengiriman_data(): void
    {
        $admin = $this->buatUserDenganRole('admin');
        $this->actingAs($admin);

        $this->get(route('satusehat.setup'))->assertOk();
        $this->get(route('satusehat.pengiriman'))->assertOk()
            ->assertSee('Pengiriman Data SatuSehat');
    }

    /** @test */
    public function route_lama_pengaturan_satusehat_redirect_ke_satusehat_setup(): void
    {
        $admin = $this->buatUserDenganRole('admin');
        $this->actingAs($admin);

        $this->get(route('pengaturan.satusehat'))
            ->assertRedirect(route('satusehat.setup'));
    }

    /** @test */
    public function role_tanpa_pengaturan_satusehat_ditolak_403(): void
    {
        $dokter = $this->buatUserDenganRole('dokter');
        $this->actingAs($dokter);

        $this->get(route('satusehat.setup'))->assertForbidden();
        $this->get(route('satusehat.pengiriman'))->assertForbidden();
        $this->get(route('pengaturan.satusehat'))->assertForbidden();
    }
}

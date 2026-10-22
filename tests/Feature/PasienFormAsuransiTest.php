<?php

namespace Tests\Feature;

use App\Livewire\Pasien\PasienForm;
use App\Models\Asuransi;
use App\Models\Pasien;
use App\Models\PasienAsuransi;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Test fitur pemisahan Nama Asuransi dan Nomor Polis di form pasien
 */
class PasienFormAsuransiTest extends TestCase
{
    use DatabaseTransactions;

    private Asuransi $asuransi;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test asuransi
        $this->asuransi = Asuransi::factory()->create([
            'is_active' => true,
            'nama'      => 'Asuransi Test Premium',
        ]);
    }

    private function loginSebagaiAdmin(): User
    {
        $user = User::role('admin')->first() ?? User::role('super_admin')->first();
        $this->actingAs($user);
        return $user;
    }

    /** @test */
    public function dapat_menambah_pasien_dengan_asuransi(): void
    {
        $this->loginSebagaiAdmin();

        Livewire::test(PasienForm::class)
            ->set('nama', 'Budi Santoso')
            ->set('tempat_lahir', 'Jakarta')
            ->set('tanggal_lahir', '1990-01-15')
            ->set('jenis_kelamin', 'L')
            ->set('tipe_pasien', 'WNI')
            ->set('nik', '3201234567890123')
            ->set('no_bpjs', '1234567890123')
            ->set('alamat', 'Jl. Test No. 123')
            ->set('telepon', '081234567890')
            ->set('asuransi_id', $this->asuransi->id)
            ->set('nomor_polis', 'POL-2024-001')
            ->set('sumberInformasiId', 1)
            ->set('kontak', [
                [
                    'nama'       => 'Istri',
                    'nomor_hp'   => '082345678901',
                    'hubungan'   => 'istri',
                    'alamat'     => 'Alamat sama',
                    'is_primary' => true,
                ],
            ])
            ->call('save')
            ->assertHasNoErrors();

        // Verify pasien was created
        $pasien = Pasien::where('nik', '3201234567890123')->first();
        $this->assertNotNull($pasien);

        // Verify asuransi was linked
        $asuransiLink = PasienAsuransi::where('pasien_id', $pasien->id)
            ->where('asuransi_id', $this->asuransi->id)
            ->where('nomor_polis', 'POL-2024-001')
            ->where('is_primary', true)
            ->first();
        
        $this->assertNotNull($asuransiLink);
    }
}

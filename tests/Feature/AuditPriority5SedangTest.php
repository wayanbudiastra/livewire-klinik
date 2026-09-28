<?php

namespace Tests\Feature;

use App\Livewire\Pasien\AsuransiPasienManager;
use App\Models\Asuransi;
use App\Models\Pasien;
use App\Models\PasienAsuransi;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regresi Audit Priority 5 (Asuransi/BPJS coverage calculation), temuan Sedang:
 *
 * AsuransiPasienManager::tambah()/setPrimary()/hapus() sebelumnya tidak
 * punya authorize() sama sekali -- halaman induk (detail pasien) cuma
 * digate pasien.view/pasien.edit, bukan asuransi.pasien.manage yang
 * sebenarnya sudah ada di seeder (dipegang kasir & front_office). Siapa
 * pun yang bisa buka halaman pasien -- termasuk dokter/perawat -- bisa
 * menambah/menghapus data asuransi pasien. Ditambahkan
 * authorize('asuransi.pasien.manage') di ketiga method.
 *
 * Pakai DatabaseTransactions -- bukan RefreshDatabase.
 */
class AuditPriority5SedangTest extends TestCase
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

    private function buatPasien(): Pasien
    {
        return Pasien::create([
            'nomor_rm' => 'RM-' . uniqid(), 'nama' => 'Pasien Test ' . uniqid(), 'tempat_lahir' => 'Denpasar',
            'tanggal_lahir' => '1990-01-01', 'jenis_kelamin' => 'L', 'alamat' => 'Jl. Test', 'telepon' => '08123',
        ]);
    }

    private function buatAsuransi(): Asuransi
    {
        return Asuransi::create([
            'kode' => 'AS-' . uniqid(), 'nama' => 'Asuransi Test ' . uniqid(), 'tipe' => 'swasta', 'is_active' => true,
        ]);
    }

    /** @test */
    public function dokter_ditolak_tambah_setprimary_dan_hapus_asuransi_pasien(): void
    {
        $pasien   = $this->buatPasien();
        $asuransi = $this->buatAsuransi();
        $existing = PasienAsuransi::create([
            'pasien_id' => $pasien->id, 'asuransi_id' => $asuransi->id, 'nomor_polis' => 'POL-EXISTING',
        ]);

        $dokter = $this->buatUserDenganRole('dokter');
        $this->actingAs($dokter);

        Livewire::test(AsuransiPasienManager::class, ['pasien' => $pasien])
            ->set('asuransiId', $asuransi->id)
            ->set('nomorPolis', 'POL-BARU')
            ->call('tambah')
            ->assertForbidden();

        Livewire::test(AsuransiPasienManager::class, ['pasien' => $pasien])
            ->call('setPrimary', $existing->id)
            ->assertForbidden();

        Livewire::test(AsuransiPasienManager::class, ['pasien' => $pasien])
            ->call('hapus', $existing->id)
            ->assertForbidden();

        $this->assertSame(1, PasienAsuransi::where('pasien_id', $pasien->id)->count());
        $this->assertTrue($existing->fresh()->is_active);
    }

    /** @test */
    public function kasir_tetap_bisa_tambah_setprimary_dan_hapus_asuransi_pasien(): void
    {
        $pasien   = $this->buatPasien();
        $asuransi = $this->buatAsuransi();

        $kasir = $this->buatUserDenganRole('kasir');
        $this->actingAs($kasir);

        Livewire::test(AsuransiPasienManager::class, ['pasien' => $pasien])
            ->set('asuransiId', $asuransi->id)
            ->set('nomorPolis', 'POL-KASIR')
            ->call('tambah')
            ->assertOk();

        $pa = PasienAsuransi::where('pasien_id', $pasien->id)->firstOrFail();

        Livewire::test(AsuransiPasienManager::class, ['pasien' => $pasien])
            ->call('setPrimary', $pa->id)
            ->assertOk();
        $this->assertTrue($pa->fresh()->is_primary);

        Livewire::test(AsuransiPasienManager::class, ['pasien' => $pasien])
            ->call('hapus', $pa->id)
            ->assertOk();
        $this->assertFalse($pa->fresh()->is_active);
    }
}

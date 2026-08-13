<?php

namespace Tests\Feature;

use App\Livewire\Pemeriksaan\CetakSurat;
use App\Models\Dokter;
use App\Models\Kunjungan;
use App\Models\Pasien;
use App\Models\SoapNote;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regresi untuk bug "Undefined variable $kunjungan" di cetak-surat.blade.php.
 *
 * Root cause: CetakSurat::kunjungan() adalah #[Computed] method, bukan public
 * property, sehingga di Blade wajib diakses sebagai $this->kunjungan --
 * bukan $kunjungan bare (yang cuma otomatis ter-extract untuk public property).
 * Error-nya muncul begitu modal dibuka, sebelum logic SOAP apa pun jalan --
 * makanya user melihatnya identik baik SOAP sudah final maupun belum.
 *
 * Pakai DatabaseTransactions -- lihat catatan yang sama di
 * SensitiveActionAuthorizationTest.php soal kenapa bukan RefreshDatabase.
 */
class CetakSuratModalTest extends TestCase
{
    use DatabaseTransactions;

    private function buatKunjungan(bool $soapFinal): Kunjungan
    {
        $dokterUser = User::create([
            'nama'      => 'Dr. Test ' . uniqid(),
            'email'     => 'dokter-' . uniqid() . '@example.test',
            'password'  => Hash::make('password'),
            'is_active' => true,
        ]);
        $dokterUser->assignRole('dokter');

        $dokter = Dokter::create(['user_id' => $dokterUser->id]);

        $pasien = Pasien::create([
            'nomor_rm'      => 'RM-' . uniqid(),
            'nama'          => 'Pasien Test',
            'tempat_lahir'  => 'Denpasar',
            'tanggal_lahir' => '1990-01-01',
            'jenis_kelamin' => 'L',
            'alamat'        => 'Jl. Test No. 1',
            'telepon'       => '081234567890',
        ]);

        $kunjungan = Kunjungan::create([
            'nomor_antrean' => 'A-' . uniqid(),
            'pasien_id'     => $pasien->id,
            'dokter_id'     => $dokter->id,
            'status'        => 'dalam_pemeriksaan',
        ]);

        SoapNote::create([
            'kunjungan_id' => $kunjungan->id,
            'is_final'     => $soapFinal,
        ]);

        return $kunjungan;
    }

    private function loginSebagaiDokter(Kunjungan $kunjungan): void
    {
        $this->actingAs($kunjungan->dokter->user);
    }

    /** @test */
    public function modal_terbuka_tanpa_error_saat_soap_belum_final(): void
    {
        $kunjungan = $this->buatKunjungan(soapFinal: false);
        $this->loginSebagaiDokter($kunjungan);

        Livewire::test(CetakSurat::class, ['kunjunganId' => $kunjungan->id])
            ->call('buka', 'keterangan_sehat')
            ->assertOk()
            ->assertSee($kunjungan->pasien->nama)
            ->assertSee('SOAP Note belum final');
    }

    /** @test */
    public function modal_terbuka_tanpa_error_saat_soap_sudah_final(): void
    {
        $kunjungan = $this->buatKunjungan(soapFinal: true);
        $this->loginSebagaiDokter($kunjungan);

        Livewire::test(CetakSurat::class, ['kunjunganId' => $kunjungan->id])
            ->call('buka', 'keterangan_sehat')
            ->assertOk()
            ->assertSee($kunjungan->pasien->nama)
            ->assertDontSee('SOAP Note belum final');
    }
}

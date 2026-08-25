<?php

namespace Tests\Feature;

use App\Livewire\Pemeriksaan\DetailPemeriksaan;
use App\Models\AsesmenPerawat;
use App\Models\Dokter;
use App\Models\Kunjungan;
use App\Models\Pasien;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Field "Anamnesis / Keluhan Utama" di tab Asesmen & Tanda Vital (permintaan
 * user): pre-fill dari kunjungan.keluhan (yang dicatat petugas saat
 * pendaftaran di PendaftaranTab.php) supaya perawat tidak perlu ketik ulang
 * -- tapi tetap bisa diedit, dan tidak menimpa data asesmen yang sudah
 * disimpan perawat sebelumnya.
 *
 * Pakai DatabaseTransactions -- lihat catatan yang sama di
 * SensitiveActionAuthorizationTest.php soal kenapa bukan RefreshDatabase.
 */
class AnamnesisDariKeluhanPendaftaranTest extends TestCase
{
    use DatabaseTransactions;

    private function buatKunjungan(?string $keluhan): Kunjungan
    {
        $dokterUser = User::create([
            'nama' => 'Dr. Test ' . uniqid(), 'email' => 'dokter-' . uniqid() . '@example.test',
            'password' => Hash::make('password'), 'is_active' => true,
        ]);
        $dokterUser->assignRole('dokter');
        $dokter = Dokter::create(['user_id' => $dokterUser->id]);

        $pasien = Pasien::create([
            'nomor_rm' => 'RM-' . uniqid(), 'nama' => 'Pasien Test', 'tempat_lahir' => 'Denpasar',
            'tanggal_lahir' => '1990-01-01', 'jenis_kelamin' => 'L', 'alamat' => 'Jl. Test', 'telepon' => '08123',
        ]);

        $kunjungan = Kunjungan::create([
            'nomor_antrean' => 'A-' . uniqid(), 'pasien_id' => $pasien->id, 'dokter_id' => $dokter->id,
            'status' => 'menunggu', 'tanggal' => now(), 'keluhan' => $keluhan,
        ]);

        $this->actingAs($dokterUser);

        return $kunjungan;
    }

    /** @test */
    public function anamnesis_terisi_otomatis_dari_keluhan_pendaftaran_saat_belum_ada_asesmen(): void
    {
        $kunjungan = $this->buatKunjungan('Demam 3 hari, batuk pilek');

        Livewire::test(DetailPemeriksaan::class, ['kunjunganId' => $kunjungan->id])
            ->assertSet('anamnesisAwal', 'Demam 3 hari, batuk pilek');
    }

    /** @test */
    public function anamnesis_yang_sudah_disimpan_perawat_tidak_ditimpa_keluhan_pendaftaran(): void
    {
        $kunjungan = $this->buatKunjungan('Demam 3 hari');

        AsesmenPerawat::create([
            'kunjungan_id'    => $kunjungan->id,
            'anamnesis_awal'  => 'Hasil anamnesis lengkap oleh perawat',
        ]);

        Livewire::test(DetailPemeriksaan::class, ['kunjunganId' => $kunjungan->id])
            ->assertSet('anamnesisAwal', 'Hasil anamnesis lengkap oleh perawat');
    }

    /** @test */
    public function anamnesis_terisi_dari_keluhan_walau_sudah_ada_asesmen_vital_tapi_anamnesis_kosong(): void
    {
        $kunjungan = $this->buatKunjungan('Nyeri dada');

        AsesmenPerawat::create([
            'kunjungan_id'   => $kunjungan->id,
            'tekanan_darah'  => '120/80',
            'anamnesis_awal' => null, // vital sudah diisi, tapi anamnesis belum
        ]);

        Livewire::test(DetailPemeriksaan::class, ['kunjunganId' => $kunjungan->id])
            ->assertSet('anamnesisAwal', 'Nyeri dada')
            ->assertSet('tekananDarah', '120/80');
    }

    /** @test */
    public function anamnesis_tetap_kosong_kalau_kunjungan_tidak_punya_keluhan(): void
    {
        $kunjungan = $this->buatKunjungan(null);

        Livewire::test(DetailPemeriksaan::class, ['kunjunganId' => $kunjungan->id])
            ->assertSet('anamnesisAwal', '');
    }
}

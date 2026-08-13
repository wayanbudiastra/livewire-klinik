<?php

namespace Tests\Feature;

use App\Livewire\Pemeriksaan\CetakSurat;
use App\Models\Dokter;
use App\Models\Kunjungan;
use App\Models\Pasien;
use App\Models\SoapNote;
use App\Models\SuratKeterangan;
use App\Models\User;
use App\Services\Pemeriksaan\SuratKeteranganService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Aturan baru (permintaan user): kunjungan yang statusnya sudah "Selesai"
 * dianggap final/terkunci -- tiap tipe surat cuma boleh diterbitkan sekali.
 * Selama masih "dalam_pemeriksaan", boleh diterbitkan ulang (mis. ralat
 * sebelum kunjungan ditutup).
 *
 * Pakai DatabaseTransactions -- lihat catatan yang sama di
 * SensitiveActionAuthorizationTest.php soal kenapa bukan RefreshDatabase.
 */
class CetakSuratSekaliPerTipeTest extends TestCase
{
    use DatabaseTransactions;

    private function buatKunjungan(string $status): Kunjungan
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
            'status'        => $status,
        ]);

        SoapNote::create([
            'kunjungan_id' => $kunjungan->id,
            'is_final'     => true,
        ]);

        return $kunjungan;
    }

    /** @test */
    public function tidak_bisa_cetak_ulang_tipe_yang_sama_setelah_kunjungan_selesai(): void
    {
        $kunjungan = $this->buatKunjungan('selesai');
        $this->actingAs($kunjungan->dokter->user);

        // Surat pertama sudah pernah diterbitkan sebelumnya (mis. saat masih dalam_pemeriksaan).
        SuratKeterangan::create([
            'nomor_surat'  => SuratKeterangan::generateNomor('keterangan_sehat'),
            'kunjungan_id' => $kunjungan->id,
            'tipe'         => 'keterangan_sehat',
            'dokter_id'    => $kunjungan->dokter_id,
            'data'         => [],
            'dicetak_oleh' => $kunjungan->dokter->user_id,
            'dicetak_pada' => now(),
        ]);

        $jumlahSebelum = SuratKeterangan::count();

        Livewire::test(CetakSurat::class, ['kunjunganId' => $kunjungan->id])
            ->call('buka', 'keterangan_sehat')
            ->set('dokterId', $kunjungan->dokter_id)
            ->call('cetak')
            ->assertOk();

        // Tidak ada surat baru yang tercipta -- ditolak di service layer.
        $this->assertSame($jumlahSebelum, SuratKeterangan::count());
    }

    /** @test */
    public function dropdown_menampilkan_tipe_yang_sudah_terbit_sebagai_terkunci_saat_selesai(): void
    {
        $kunjungan = $this->buatKunjungan('selesai');
        $this->actingAs($kunjungan->dokter->user);

        SuratKeterangan::create([
            'nomor_surat'  => SuratKeterangan::generateNomor('resume_medis'),
            'kunjungan_id' => $kunjungan->id,
            'tipe'         => 'resume_medis',
            'dokter_id'    => $kunjungan->dokter_id,
            'data'         => [],
            'dicetak_oleh' => $kunjungan->dokter->user_id,
            'dicetak_pada' => now(),
        ]);

        Livewire::test(CetakSurat::class, ['kunjunganId' => $kunjungan->id])
            ->assertSee('terbit');
    }

    /** @test */
    public function masih_boleh_cetak_ulang_tipe_yang_sama_selama_masih_dalam_pemeriksaan(): void
    {
        $kunjungan = $this->buatKunjungan('dalam_pemeriksaan');
        $this->actingAs($kunjungan->dokter->user);

        app(SuratKeteranganService::class)->simpanSehat($kunjungan, [
            'dokter_id' => $kunjungan->dokter_id,
            'keperluan' => 'test',
        ], $kunjungan->dokter->user_id);

        $jumlahSebelum = SuratKeterangan::count();

        Livewire::test(CetakSurat::class, ['kunjunganId' => $kunjungan->id])
            ->call('buka', 'keterangan_sehat')
            ->set('dokterId', $kunjungan->dokter_id)
            ->call('cetak');

        // Surat kedua berhasil dibuat -- belum "selesai", jadi tidak dikunci.
        $this->assertSame($jumlahSebelum + 1, SuratKeterangan::count());
    }
}

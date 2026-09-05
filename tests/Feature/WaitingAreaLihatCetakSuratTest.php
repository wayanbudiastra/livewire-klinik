<?php

namespace Tests\Feature;

use App\Livewire\Pemeriksaan\WaitingArea;
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
 * Modal "Lihat" (riwayat pemeriksaan read-only) di tab Waiting Area,
 * untuk kunjungan berstatus "selesai", sebelumnya tidak menampilkan
 * tombol Cetak Surat sama sekali -- padahal fitur cetak surat sudah
 * mendukung status selesai (lihat detail-pemeriksaan.blade.php). User
 * yang mengakses lewat "Lihat" jadi tidak bisa menerbitkan/mengunduh
 * ulang surat untuk kunjungan yang sudah selesai.
 *
 * Pakai DatabaseTransactions -- lihat catatan yang sama di
 * SensitiveActionAuthorizationTest.php soal kenapa bukan RefreshDatabase.
 */
class WaitingAreaLihatCetakSuratTest extends TestCase
{
    use DatabaseTransactions;

    private function buatKunjunganSelesai(): Kunjungan
    {
        $adminUser = User::create([
            'nama'      => 'Admin Test ' . uniqid(),
            'email'     => 'admin-' . uniqid() . '@example.test',
            'password'  => Hash::make('password'),
            'is_active' => true,
        ]);
        $adminUser->assignRole('super_admin');

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
            'status'        => 'selesai',
            'tanggal'       => now(),
        ]);

        SoapNote::create(['kunjungan_id' => $kunjungan->id, 'is_final' => true]);

        $this->actingAs($adminUser);

        return $kunjungan;
    }

    /** @test */
    public function tombol_cetak_surat_tampil_di_modal_lihat_untuk_kunjungan_selesai(): void
    {
        $kunjungan = $this->buatKunjunganSelesai();

        Livewire::test(WaitingArea::class)
            ->set('tanggalMulai', $kunjungan->tanggal->toDateString())
            ->set('tanggalAkhir', $kunjungan->tanggal->toDateString())
            ->set('filterStatus', 'selesai')
            ->call('openView', $kunjungan->id)
            ->assertOk()
            ->assertSee('Cetak Surat')
            ->assertSee('Tutup');
    }
}

<?php

namespace Tests\Feature;

use App\Livewire\Kasir\TagihanPasien;
use App\Livewire\Pemeriksaan\DetailPemeriksaan;
use App\Models\AsesmenPerawat;
use App\Models\Dokter;
use App\Models\Invoice;
use App\Models\Kunjungan;
use App\Models\MasterTindakan;
use App\Models\Pasien;
use App\Models\Perawat;
use App\Models\Poli;
use App\Models\SesiKas;
use App\Models\Tindakan;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Sejak 2026-11-09: TTV finalization oleh perawat mengganti SOAP final
 * sebagai gate pembayaran. User meminta perubahan ini (lihat git log
 * commit 574a7de) untuk menghindari kasir terhenti saat antrian panjang,
 * dengan trade-off: pasien bisa lunas sebelum dokter menulis SOAP Note.
 */
class TtvFinalisasiTest extends TestCase
{
    use DatabaseTransactions;

    private function buatPerawat(): User
    {
        $user = User::create([
            'nama' => 'Perawat Test ' . uniqid(), 'email' => 'perawat-' . uniqid() . '@example.test',
            'password' => Hash::make('password'), 'is_active' => true,
        ]);
        $user->assignRole('perawat');
        Perawat::create(['user_id' => $user->id]);
        return $user;
    }

    private function buatKasir(): User
    {
        $user = User::create([
            'nama' => 'Kasir Test ' . uniqid(), 'email' => 'kasir-' . uniqid() . '@example.test',
            'password' => Hash::make('password'), 'is_active' => true,
        ]);
        $user->assignRole('kasir');
        return $user;
    }

    private function buatKunjunganDalamPemeriksaan(): Kunjungan
    {
        $dokterUser = User::create([
            'nama' => 'Dr. Test ' . uniqid(), 'email' => 'dokter-' . uniqid() . '@example.test',
            'password' => Hash::make('password'), 'is_active' => true,
        ]);
        $dokterUser->assignRole('dokter');
        $dokter = Dokter::create(['user_id' => $dokterUser->id]);
        $poli   = Poli::create(['nama' => 'Poli Test ' . uniqid(), 'kode' => 'PT' . rand(1000, 9999), 'is_active' => true]);
        $pasien = Pasien::create([
            'nomor_rm' => 'RM-' . uniqid(), 'nama' => 'Pasien Test ' . uniqid(), 'tempat_lahir' => 'Denpasar',
            'tanggal_lahir' => '1990-01-01', 'jenis_kelamin' => 'L', 'alamat' => 'Jl. Test', 'telepon' => '08123',
        ]);

        return Kunjungan::create([
            'nomor_antrean' => 'W-' . rand(100, 999), 'pasien_id' => $pasien->id,
            'dokter_id' => $dokter->id, 'poli_id' => $poli->id,
            'tanggal' => now(), 'status' => 'dalam_pemeriksaan',
        ]);
    }

    private function isiTandaVital(Kunjungan $kunjungan): AsesmenPerawat
    {
        return AsesmenPerawat::create([
            'kunjungan_id'  => $kunjungan->id,
            'tekanan_darah' => '120/80',
            'nadi'          => 80,
            'suhu'          => 36.5,
        ]);
    }

    private function tambahTindakan(Kunjungan $kunjungan, float $tarif = 150000): Tindakan
    {
        $master = MasterTindakan::create([
            'kode' => 'TND-' . uniqid(), 'nama' => 'Tindakan Test ' . uniqid(),
            'tarif' => $tarif, 'is_active' => true,
        ]);

        return Tindakan::create([
            'kunjungan_id'       => $kunjungan->id,
            'master_tindakan_id' => $master->id,
            'jumlah'             => 1,
        ]);
    }

    private function bukaSesiKas(User $kasir): SesiKas
    {
        return SesiKas::create([
            'user_id' => $kasir->id, 'tanggal' => now()->toDateString(),
            'dibuka_pada' => now(), 'saldo_awal' => 0, 'status' => 'buka',
        ]);
    }

    // ── Finalisasi TTV ──────────────────────────────────────────

    /** @test */
    public function perawat_bisa_finalisasi_ttv(): void
    {
        $perawat   = $this->buatPerawat();
        $kunjungan = $this->buatKunjunganDalamPemeriksaan();
        $this->isiTandaVital($kunjungan);

        $this->actingAs($perawat);

        Livewire::test(DetailPemeriksaan::class, ['kunjunganId' => $kunjungan->id])
            ->call('finalisasiTtv')
            ->assertHasNoErrors()
            ->assertDispatched('notify', type: 'success');

        $asesmen = $kunjungan->fresh()->asesmenPerawat;
        $this->assertTrue($asesmen->is_final);
        $this->assertNotNull($asesmen->finalized_at);
        $this->assertSame($perawat->id, $asesmen->finalized_by);
    }

    /** @test */
    public function finalisasi_ttv_tanpa_minimal_satu_ttv_ditolak(): void
    {
        $perawat   = $this->buatPerawat();
        $kunjungan = $this->buatKunjunganDalamPemeriksaan();
        // sengaja tidak isi tanda vital

        $this->actingAs($perawat);

        Livewire::test(DetailPemeriksaan::class, ['kunjunganId' => $kunjungan->id])
            ->call('finalisasiTtv')
            ->assertDispatched('notify', fn ($name, $params) =>
                ($params['type'] ?? null) === 'error'
                && str_contains($params['message'] ?? '', 'minimal satu tanda vital'));

        $this->assertFalse(
            (bool) ($kunjungan->fresh()->asesmenPerawat?->is_final),
            'TTV tidak boleh final jika tidak ada input vital.'
        );
    }

    /** @test */
    public function finalisasi_ttv_sudah_final_idempoten(): void
    {
        $perawat   = $this->buatPerawat();
        $kunjungan = $this->buatKunjunganDalamPemeriksaan();
        $this->isiTandaVital($kunjungan);

        $this->actingAs($perawat);

        Livewire::test(DetailPemeriksaan::class, ['kunjunganId' => $kunjungan->id])
            ->call('finalisasiTtv')
            ->call('finalisasiTtv') // panggil 2x
            ->assertHasNoErrors();

        $this->assertTrue($kunjungan->fresh()->asesmenPerawat->is_final);
    }

    /** @test */
    public function buka_finalisasi_ttv_memungkinkan_edit_ulang(): void
    {
        $perawat   = $this->buatPerawat();
        $kunjungan = $this->buatKunjunganDalamPemeriksaan();
        $this->isiTandaVital($kunjungan);

        $this->actingAs($perawat);

        Livewire::test(DetailPemeriksaan::class, ['kunjunganId' => $kunjungan->id])
            ->call('finalisasiTtv')
            ->call('bukaFinalisasiTtv')
            ->assertHasNoErrors();

        $this->assertFalse($kunjungan->fresh()->asesmenPerawat->is_final);
    }

    /** @test */
    public function buka_finalisasi_ditolak_jika_tagihan_sudah_lunas(): void
    {
        $perawat   = $this->buatPerawat();
        $kasir     = $this->buatKasir();
        $kunjungan = $this->buatKunjunganDalamPemeriksaan();
        $this->isiTandaVital($kunjungan);
        $this->tambahTindakan($kunjungan, 150000);
        $this->bukaSesiKas($kasir);

        $this->actingAs($perawat);

        // Finalisasi TTV
        Livewire::test(DetailPemeriksaan::class, ['kunjunganId' => $kunjungan->id])
            ->call('finalisasiTtv')
            ->assertHasNoErrors();

        // Kasir proses pembayaran dengan status 'selesai' (backward compat)
        $kunjungan->update(['status' => 'selesai']);

        $this->actingAs($kasir);
        Livewire::test(TagihanPasien::class)
            ->call('selectKunjungan', $kunjungan->id)
            ->set('metodePembayaran', 'tunai')
            ->set('jumlahTunai', '150000')
            ->call('prosesPembayaran')
            ->assertHasNoErrors();

        // Sekarang coba buka finalisasi -- harus ditolak
        $this->actingAs($perawat);
        Livewire::test(DetailPemeriksaan::class, ['kunjunganId' => $kunjungan->id])
            ->call('bukaFinalisasiTtv')
            ->assertDispatched('notify', fn ($name, $params) =>
                ($params['type'] ?? null) === 'error'
                && str_contains($params['message'] ?? '', 'sudah lunas'));
    }

    // ── Pembayaran dengan TTV Final ─────────────────────────────

    /** @test */
    public function pembayaran_berhasil_setelah_ttv_difinalisasi(): void
    {
        $perawat   = $this->buatPerawat();
        $kasir     = $this->buatKasir();
        $kunjungan = $this->buatKunjunganDalamPemeriksaan();
        $this->isiTandaVital($kunjungan);
        $this->tambahTindakan($kunjungan, 150000);
        $this->bukaSesiKas($kasir);

        // Perawat finalisasi TTV
        $this->actingAs($perawat);
        Livewire::test(DetailPemeriksaan::class, ['kunjunganId' => $kunjungan->id])
            ->call('finalisasiTtv')
            ->assertHasNoErrors();

        // Kasir bisa langsung bayar (tanpa perlu kunjungan 'selesai')
        $this->actingAs($kasir);
        Livewire::test(TagihanPasien::class)
            ->call('selectKunjungan', $kunjungan->id)
            ->set('metodePembayaran', 'tunai')
            ->set('jumlahTunai', '150000')
            ->call('prosesPembayaran')
            ->assertHasNoErrors();

        $invoice = Invoice::where('kunjungan_id', $kunjungan->id)->first();
        $this->assertSame('lunas', $invoice->fresh()->status,
            'Pembayaran harus berhasil jika TTV sudah difinalisasi, tanpa perlu status selesai.');
    }

    /** @test */
    public function pembayaran_ditolak_jika_ttv_belum_finalisasi(): void
    {
        $kasir     = $this->buatKasir();
        $kunjungan = $this->buatKunjunganDalamPemeriksaan();
        $this->isiTandaVital($kunjungan);
        $this->tambahTindakan($kunjungan, 150000);
        $this->bukaSesiKas($kasir);

        $this->actingAs($kasir);
        Livewire::test(TagihanPasien::class)
            ->call('selectKunjungan', $kunjungan->id)
            ->set('metodePembayaran', 'tunai')
            ->set('jumlahTunai', '150000')
            ->call('prosesPembayaran');

        $this->assertStringContainsString('Tanda vital belum difinalisasi', (string) session('error'));
        $invoice = Invoice::where('kunjungan_id', $kunjungan->id)->first();
        $this->assertSame('belum_bayar', $invoice->fresh()->status,
            'Pembayaran harus ditolak jika TTV belum difinalisasi.');
    }

    /** @test */
    public function backward_compat_kunjungan_selesai_masih_lolos_pembayaran(): void
    {
        $kasir     = $this->buatKasir();
        $kunjungan = $this->buatKunjunganDalamPemeriksaan();
        // Sengaja tidak finalisasi TTV, tapi set status selesai langsung
        $this->tambahTindakan($kunjungan, 100000);
        $kunjungan->update(['status' => 'selesai']);
        $this->bukaSesiKas($kasir);

        $this->actingAs($kasir);
        Livewire::test(TagihanPasien::class)
            ->call('selectKunjungan', $kunjungan->id)
            ->set('metodePembayaran', 'tunai')
            ->set('jumlahTunai', '100000')
            ->call('prosesPembayaran')
            ->assertHasNoErrors();

        $invoice = Invoice::where('kunjungan_id', $kunjungan->id)->first();
        $this->assertSame('lunas', $invoice->fresh()->status,
            'Kunjungan lama (status selesai tanpa TTV final) harus tetap bisa dibayar.');
    }
}

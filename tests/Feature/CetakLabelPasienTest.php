<?php

namespace Tests\Feature;

use App\Livewire\Kunjungan\ListPendaftaran;
use App\Livewire\Kunjungan\PendaftaranTab;
use App\Models\Dokter;
use App\Models\Invoice;
use App\Models\Kunjungan;
use App\Models\Pasien;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Cetak Label Pasien (permintaan user): dicetak setelah proses registrasi,
 * dan tetap bisa dicetak ulang dari list registrasi selama kunjungan masih
 * aktif (belum selesai/dibatalkan) DAN billing belum ditutup (belum lunas)
 * -- lihat Kunjungan::getBisaCetakLabelAttribute() & routes/web.php
 * (kunjungan.label.cetak).
 *
 * Pakai DatabaseTransactions -- bukan RefreshDatabase (lihat catatan yang
 * sama di SensitiveActionAuthorizationTest.php).
 */
class CetakLabelPasienTest extends TestCase
{
    use DatabaseTransactions;

    private function buatUserFrontOffice(): User
    {
        $user = User::create([
            'nama' => 'FO Test ' . uniqid(), 'email' => 'fo-' . uniqid() . '@example.test',
            'password' => Hash::make('password'), 'is_active' => true,
        ]);
        $user->assignRole('front_office');

        return $user;
    }

    private function buatPasien(): Pasien
    {
        return Pasien::create([
            'nomor_rm' => 'RM-' . uniqid(), 'nama' => 'Pasien Test', 'tempat_lahir' => 'Denpasar',
            'tanggal_lahir' => '1990-01-01', 'jenis_kelamin' => 'L', 'alamat' => 'Jl. Test', 'telepon' => '08123',
        ]);
    }

    private function buatKunjungan(Pasien $pasien, string $status = 'menunggu'): Kunjungan
    {
        $dokterUser = User::create([
            'nama' => 'Dr. Test ' . uniqid(), 'email' => 'dokter-' . uniqid() . '@example.test',
            'password' => Hash::make('password'), 'is_active' => true,
        ]);
        $dokterUser->assignRole('dokter');
        $dokter = Dokter::create(['user_id' => $dokterUser->id]);

        return Kunjungan::create([
            'nomor_antrean' => 'W-' . uniqid(), 'pasien_id' => $pasien->id, 'dokter_id' => $dokter->id,
            'status' => $status, 'tanggal' => now(),
        ]);
    }

    // ── Accessor bisa_cetak_label ────────────────────────────────

    /** @test */
    public function kunjungan_menunggu_tanpa_invoice_bisa_cetak_label(): void
    {
        $kunjungan = $this->buatKunjungan($this->buatPasien(), 'menunggu');
        $this->assertTrue($kunjungan->bisa_cetak_label);
    }

    /** @test */
    public function kunjungan_dalam_pemeriksaan_dengan_invoice_belum_lunas_bisa_cetak_label(): void
    {
        $kunjungan = $this->buatKunjungan($this->buatPasien(), 'dalam_pemeriksaan');
        Invoice::create([
            'kunjungan_id' => $kunjungan->id, 'nomor_invoice' => 'INV-' . uniqid(),
            'total_tagihan' => 100000, 'sisa' => 100000, 'status' => 'belum_bayar',
        ]);

        $this->assertTrue($kunjungan->fresh()->bisa_cetak_label);
    }

    /** @test */
    public function kunjungan_selesai_tidak_bisa_cetak_label(): void
    {
        $kunjungan = $this->buatKunjungan($this->buatPasien(), 'selesai');
        $this->assertFalse($kunjungan->bisa_cetak_label);
    }

    /** @test */
    public function kunjungan_dibatalkan_tidak_bisa_cetak_label(): void
    {
        $kunjungan = $this->buatKunjungan($this->buatPasien(), 'dibatalkan');
        $this->assertFalse($kunjungan->bisa_cetak_label);
    }

    /** @test */
    public function kunjungan_dengan_invoice_lunas_tidak_bisa_cetak_label(): void
    {
        $kunjungan = $this->buatKunjungan($this->buatPasien(), 'dalam_pemeriksaan');
        Invoice::create([
            'kunjungan_id' => $kunjungan->id, 'nomor_invoice' => 'INV-' . uniqid(),
            'total_tagihan' => 100000, 'sisa' => 0, 'total_bayar' => 100000, 'status' => 'lunas',
        ]);

        $this->assertFalse($kunjungan->fresh()->bisa_cetak_label);
    }

    // ── Route cetak label ─────────────────────────────────────────

    /** @test */
    public function route_cetak_label_mengembalikan_pdf_untuk_kunjungan_yang_eligible(): void
    {
        $user      = $this->buatUserFrontOffice();
        $kunjungan = $this->buatKunjungan($this->buatPasien(), 'menunggu');

        $response = $this->actingAs($user)->get(route('kunjungan.label.cetak', $kunjungan->id));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    /** @test */
    public function route_cetak_label_ditolak_403_untuk_kunjungan_yang_sudah_selesai(): void
    {
        $user      = $this->buatUserFrontOffice();
        $kunjungan = $this->buatKunjungan($this->buatPasien(), 'selesai');

        $response = $this->actingAs($user)->get(route('kunjungan.label.cetak', $kunjungan->id));

        $response->assertForbidden();
    }

    /** @test */
    public function route_cetak_label_ditolak_403_untuk_kunjungan_yang_billingnya_sudah_lunas(): void
    {
        $user      = $this->buatUserFrontOffice();
        $kunjungan = $this->buatKunjungan($this->buatPasien(), 'dalam_pemeriksaan');
        Invoice::create([
            'kunjungan_id' => $kunjungan->id, 'nomor_invoice' => 'INV-' . uniqid(),
            'total_tagihan' => 50000, 'sisa' => 0, 'total_bayar' => 50000, 'status' => 'lunas',
        ]);

        $response = $this->actingAs($user)->get(route('kunjungan.label.cetak', $kunjungan->id));

        $response->assertForbidden();
    }

    /** @test */
    public function route_cetak_label_butuh_login(): void
    {
        $kunjungan = $this->buatKunjungan($this->buatPasien(), 'menunggu');

        $response = $this->get(route('kunjungan.label.cetak', $kunjungan->id));

        $response->assertRedirect(route('login'));
    }

    // ── Tombol di List Pendaftaran ──────────────────────────────────

    /** @test */
    public function tombol_cetak_label_tampil_di_list_untuk_kunjungan_yang_eligible(): void
    {
        $user      = $this->buatUserFrontOffice();
        $kunjungan = $this->buatKunjungan($this->buatPasien(), 'menunggu');

        $this->actingAs($user);

        Livewire::test(ListPendaftaran::class, ['tanggal' => $kunjungan->tanggal->toDateString()])
            ->set('tanggal', now()->toDateString())
            ->assertSeeHtml(route('kunjungan.label.cetak', $kunjungan->id));
    }

    /** @test */
    public function tombol_cetak_label_tidak_tampil_untuk_kunjungan_yang_sudah_selesai(): void
    {
        $user      = $this->buatUserFrontOffice();
        $kunjungan = $this->buatKunjungan($this->buatPasien(), 'selesai');

        $this->actingAs($user);

        Livewire::test(ListPendaftaran::class)
            ->set('tanggal', now()->toDateString())
            ->assertDontSeeHtml(route('kunjungan.label.cetak', $kunjungan->id));
    }

    // ── Panel hasil pendaftaran ──────────────────────────────────

    /** @test */
    public function link_cetak_label_tampil_di_panel_hasil_setelah_registrasi(): void
    {
        $user      = $this->buatUserFrontOffice();
        $kunjungan = $this->buatKunjungan($this->buatPasien(), 'menunggu');

        $this->actingAs($user);

        Livewire::test(PendaftaranTab::class)
            ->set('showHasil', true)
            ->set('nomorAntrean', $kunjungan->nomor_antrean)
            ->set('namaPasienHasil', $kunjungan->pasien->nama)
            ->set('kunjunganIdHasil', $kunjungan->id)
            ->assertSeeHtml(route('kunjungan.label.cetak', $kunjungan->id));
    }

    /** @test */
    public function link_cetak_label_tidak_tampil_kalau_kunjungan_id_hasil_kosong(): void
    {
        $user = $this->buatUserFrontOffice();
        $this->actingAs($user);

        Livewire::test(PendaftaranTab::class)
            ->set('showHasil', true)
            ->set('nomorAntrean', 'W-999')
            ->set('namaPasienHasil', 'Pasien Tanpa ID')
            ->assertDontSee('Cetak Label');
    }
}

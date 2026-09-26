<?php

namespace Tests\Feature;

use App\Livewire\Pengaturan\User\UserForm;
use App\Models\Appointment;
use App\Models\Dokter;
use App\Models\DokterPoli;
use App\Models\JadwalPraktek;
use App\Models\Pasien;
use App\Models\Perawat;
use App\Models\Poli;
use App\Models\User;
use App\Services\KunjunganService;
use App\Services\PasienService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regresi untuk temuan Kritikal & Tinggi dari audit masterdata pasien/dokter/
 * pendaftaran:
 *
 * 1. [Kritikal] UserForm role "dokter" tidak pernah membuat row dokter --
 *    padahal role "perawat" sudah otomatis dapat row perawat. Diperbaiki di
 *    app/Livewire/Pengaturan/User/UserForm.php.
 * 2. [Tinggi] KunjunganService::cekSisaKuota() pakai max() bukan sum() utk
 *    menghitung total slot terpakai (appointment checked-in + masih booked),
 *    bikin kuota bisa overbooking begitu sebagian appointment sudah check-in.
 *    Diperbaiki di app/Services/KunjunganService.php.
 * 3. [Tinggi] PasienService::generateNomorRM() dipanggil sebagai transaksi
 *    berdiri sendiri yang sudah commit (lockForUpdate()-nya lepas) SEBELUM
 *    insert pasien sungguhan terjadi di transaksi terpisah -- race condition
 *    nomor RM duplikat saat pendaftaran bersamaan. Diperbaiki dengan
 *    membungkus generateNomorRM() + insert dalam satu transaksi yang sama di
 *    app/Services/PasienService.php.
 *
 * Pakai DatabaseTransactions -- bukan RefreshDatabase (lihat catatan yang
 * sama di SensitiveActionAuthorizationTest.php).
 */
class MasterdataAuditFixesTest extends TestCase
{
    use DatabaseTransactions;

    // ── #1: Provisioning row dokter dari UserForm ────────────────

    /** @test */
    public function membuat_user_baru_dengan_role_dokter_otomatis_membuat_row_dokter(): void
    {
        $admin = User::where('email', 'admin@emr.app')->firstOrFail();
        $this->actingAs($admin);

        Livewire::test(UserForm::class)
            ->call('openCreate')
            ->set('nama', 'Dr. Baru Test')
            ->set('email', 'dokterbaru-' . uniqid() . '@example.test')
            ->set('password', 'password123')
            ->set('password_confirmation', 'password123')
            ->set('role', 'dokter')
            ->call('save')
            ->assertHasNoErrors();

        $user = User::where('nama', 'Dr. Baru Test')->firstOrFail();
        $this->assertTrue($user->hasRole('dokter'));

        $dokter = Dokter::where('user_id', $user->id)->first();
        $this->assertNotNull($dokter, 'Row dokter harus otomatis dibuat saat role dokter dipilih.');
    }

    /** @test */
    public function edit_user_ganti_role_jadi_dokter_juga_membuat_row_dokter(): void
    {
        $admin = User::where('email', 'admin@emr.app')->firstOrFail();
        $this->actingAs($admin);

        $user = User::create([
            'nama' => 'User Kasir Naik Jadi Dokter', 'email' => 'kasir-' . uniqid() . '@example.test',
            'password' => bcrypt('password'), 'is_active' => true,
        ]);
        $user->assignRole('kasir');

        $this->assertNull(Dokter::where('user_id', $user->id)->first());

        Livewire::test(UserForm::class)
            ->call('openEdit', $user->id)
            ->set('role', 'dokter')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue($user->fresh()->hasRole('dokter'));
        $this->assertNotNull(Dokter::where('user_id', $user->id)->first());
    }

    /** @test */
    public function role_selain_dokter_perawat_tidak_membuat_row_dokter_maupun_perawat(): void
    {
        $admin = User::where('email', 'admin@emr.app')->firstOrFail();
        $this->actingAs($admin);

        Livewire::test(UserForm::class)
            ->call('openCreate')
            ->set('nama', 'Kasir Baru Test')
            ->set('email', 'kasirbaru-' . uniqid() . '@example.test')
            ->set('password', 'password123')
            ->set('password_confirmation', 'password123')
            ->set('role', 'kasir')
            ->call('save')
            ->assertHasNoErrors();

        $user = User::where('nama', 'Kasir Baru Test')->firstOrFail();
        $this->assertNull(Dokter::where('user_id', $user->id)->first());
        $this->assertNull(Perawat::where('user_id', $user->id)->first());
    }

    // ── #2: cekSisaKuota() sum, bukan max ─────────────────────────

    private function buatJadwalDenganKuota(int $kuota): array
    {
        $dokterUser = User::create([
            'nama' => 'Dr. Kuota Test ' . uniqid(), 'email' => 'kuota-' . uniqid() . '@example.test',
            'password' => bcrypt('password'), 'is_active' => true,
        ]);
        $dokterUser->assignRole('dokter');
        $dokter = Dokter::create(['user_id' => $dokterUser->id]);
        $poli   = Poli::create(['nama' => 'Poli Test ' . uniqid(), 'kode' => 'PT' . rand(1000, 9999), 'is_active' => true]);
        $dokterPoli = DokterPoli::create(['dokter_id' => $dokter->id, 'poli_id' => $poli->id, 'is_aktif' => true]);
        $jadwal = JadwalPraktek::create([
            'dokter_poli_id' => $dokterPoli->id, 'hari' => 'senin',
            'jam_mulai' => '08:00', 'jam_selesai' => '12:00',
            'kuota_pasien' => $kuota, 'is_aktif' => true,
        ]);

        return compact('dokter', 'poli', 'jadwal');
    }

    private function buatPasienUntukKuota(): Pasien
    {
        return Pasien::create([
            'nomor_rm' => 'RM-' . uniqid(), 'nama' => 'Pasien Kuota ' . uniqid(), 'tempat_lahir' => 'Denpasar',
            'tanggal_lahir' => '1990-01-01', 'jenis_kelamin' => 'L', 'alamat' => 'Jl. Test', 'telepon' => '08123',
        ]);
    }

    /** @test */
    public function sisa_kuota_dihitung_dari_jumlah_checked_in_dan_masih_booked_bukan_nilai_terbesarnya(): void
    {
        $ctx    = $this->buatJadwalDenganKuota(5);
        $tgl    = now()->toDateString();

        // 2 appointment sudah check-in (punya Kunjungan terkait jadwal ini)
        for ($i = 0; $i < 2; $i++) {
            $pasien = $this->buatPasienUntukKuota();
            $apt = Appointment::create([
                'kode_booking' => 'BK-' . uniqid(), 'pasien_id' => $pasien->id,
                'dokter_id' => $ctx['dokter']->id, 'poli_id' => $ctx['poli']->id,
                'jadwal_praktek_id' => $ctx['jadwal']->id, 'tanggal_appointment' => $tgl,
                'status' => 'checked_in',
            ]);
            \App\Models\Kunjungan::create([
                'appointment_id' => $apt->id, 'nomor_antrean' => 'A-' . str_pad($i + 1, 3, '0', STR_PAD_LEFT),
                'pasien_id' => $pasien->id, 'dokter_id' => $ctx['dokter']->id, 'poli_id' => $ctx['poli']->id,
                'tanggal' => now(), 'status' => 'menunggu',
            ]);
        }

        // 2 appointment lain masih 'booked' (belum check-in)
        for ($i = 0; $i < 2; $i++) {
            $pasien = $this->buatPasienUntukKuota();
            Appointment::create([
                'kode_booking' => 'BK-' . uniqid(), 'pasien_id' => $pasien->id,
                'dokter_id' => $ctx['dokter']->id, 'poli_id' => $ctx['poli']->id,
                'jadwal_praktek_id' => $ctx['jadwal']->id, 'tanggal_appointment' => $tgl,
                'status' => 'booked',
            ]);
        }

        // Total terpakai sesungguhnya = 2 (checked-in) + 2 (booked) = 4 dari kuota 5.
        // Sebelum diperbaiki (pakai max(2,2)=2), sisa akan salah dilaporkan 3.
        $sisa = app(KunjunganService::class)->cekSisaKuota($ctx['jadwal']->id, $tgl);
        $this->assertSame(1, $sisa, 'Sisa kuota harus 5 - (2 checked-in + 2 booked) = 1, bukan 5 - max(2,2) = 3.');
    }

    /** @test */
    public function kuota_penuh_menolak_appointment_baru_walau_sebagian_sudah_check_in(): void
    {
        $ctx = $this->buatJadwalDenganKuota(3);
        $tgl = now()->toDateString();

        // 1 checked-in + 2 masih booked = 3 (pas kuota)
        $pasienCheckedIn = $this->buatPasienUntukKuota();
        $aptCheckedIn = Appointment::create([
            'kode_booking' => 'BK-' . uniqid(), 'pasien_id' => $pasienCheckedIn->id,
            'dokter_id' => $ctx['dokter']->id, 'poli_id' => $ctx['poli']->id,
            'jadwal_praktek_id' => $ctx['jadwal']->id, 'tanggal_appointment' => $tgl,
            'status' => 'checked_in',
        ]);
        \App\Models\Kunjungan::create([
            'appointment_id' => $aptCheckedIn->id, 'nomor_antrean' => 'A-001',
            'pasien_id' => $pasienCheckedIn->id, 'dokter_id' => $ctx['dokter']->id, 'poli_id' => $ctx['poli']->id,
            'tanggal' => now(), 'status' => 'menunggu',
        ]);

        for ($i = 0; $i < 2; $i++) {
            $pasien = $this->buatPasienUntukKuota();
            Appointment::create([
                'kode_booking' => 'BK-' . uniqid(), 'pasien_id' => $pasien->id,
                'dokter_id' => $ctx['dokter']->id, 'poli_id' => $ctx['poli']->id,
                'jadwal_praktek_id' => $ctx['jadwal']->id, 'tanggal_appointment' => $tgl,
                'status' => 'booked',
            ]);
        }

        $pasienBaru = $this->buatPasienUntukKuota();

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(KunjunganService::class)->buatAppointment([
            'pasien_id' => $pasienBaru->id, 'dokter_id' => $ctx['dokter']->id, 'poli_id' => $ctx['poli']->id,
            'jadwal_praktek_id' => $ctx['jadwal']->id, 'tanggal_appointment' => $tgl,
        ]);
    }

    // ── #3: PasienService::create() -- transaksi nomor RM ─────────

    /** @test */
    public function pendaftaran_pasien_berurutan_tetap_dapat_nomor_rm_berbeda_setelah_transaksi_digabung(): void
    {
        $service = app(PasienService::class);

        $data = fn () => [
            'nama' => 'Pasien RM Test ' . uniqid(), 'tempat_lahir' => 'Denpasar',
            'tanggal_lahir' => '1990-01-01', 'jenis_kelamin' => 'L', 'tipe_pasien' => 'WNI',
            'alamat' => 'Jl. Test', 'telepon' => '08123',
        ];

        $p1 = $service->create($data());
        $p2 = $service->create($data());
        $p3 = $service->create($data());

        $nomors = [$p1->nomor_rm, $p2->nomor_rm, $p3->nomor_rm];
        $this->assertSame(3, count(array_unique($nomors)), 'Ketiga pasien harus dapat nomor RM yang berbeda-beda.');
        foreach ($nomors as $n) {
            $this->assertMatchesRegularExpression('/^RM-\d{6}$/', $n);
        }
    }

    /** @test */
    public function nomor_rm_dan_kontak_darurat_tetap_tersimpan_benar_setelah_transaksi_digabung(): void
    {
        $service = app(PasienService::class);

        $pasien = $service->create([
            'nama' => 'Pasien Kontak Test', 'tempat_lahir' => 'Denpasar',
            'tanggal_lahir' => '1990-01-01', 'jenis_kelamin' => 'P', 'tipe_pasien' => 'WNI',
            'alamat' => 'Jl. Test', 'telepon' => '08123',
        ], [
            ['nama' => 'Kontak Darurat', 'nomor_hp' => '081234567890', 'hubungan' => 'suami'],
        ]);

        $this->assertNotEmpty($pasien->nomor_rm);
        $this->assertDatabaseHas('pasien', ['id' => $pasien->id, 'nomor_rm' => $pasien->nomor_rm]);
        $this->assertCount(1, $pasien->kontakDarurat);
        $this->assertTrue($pasien->kontakDarurat->first()->is_primary, 'Satu-satunya kontak harus otomatis jadi primary.');
    }
}

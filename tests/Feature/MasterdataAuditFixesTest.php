<?php

namespace Tests\Feature;

use App\Livewire\Kunjungan\ListPendaftaran;
use App\Livewire\Pemeriksaan\DetailPemeriksaan;
use App\Livewire\Pasien\PasienForm;
use App\Livewire\Pasien\PasienTable;
use App\Livewire\Pengaturan\User\UserForm;
use App\Models\Appointment;
use App\Models\ConfigSatuSehat;
use App\Models\Dokter;
use App\Models\DokterPoli;
use App\Models\Invoice;
use App\Models\JadwalPraktek;
use App\Models\Kunjungan;
use App\Models\Pasien;
use App\Models\Perawat;
use App\Models\Poli;
use App\Models\User;
use App\Services\KunjunganService;
use App\Services\PasienService;
use App\Services\SatuSehat\SatuSehatIhsService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
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
 * ...dan temuan Sedang & Rendah:
 *
 * 4. [Sedang] generateNomorAntrean() pakai whereDate() yang tidak sargable
 *    (bikin lockForUpdate() kurang efektif) -- diganti whereBetween() +
 *    loop pengaman lompat nomor yang sudah terpakai kunjungan aktif.
 * 5. [Sedang] Validasi no_bpjs di PasienForm (form yang sungguhan dipakai)
 *    belum ada regex digit-only, beda dgn StorePasienRequest yang sudah
 *    dihapus di temuan #9.
 * 6. [Sedang] cancelKunjungan() tidak pernah cek billing walau modul
 *    billing sudah ada (placeholder lama lupa diaktifkan) -- sekarang
 *    menolak pembatalan kalau masih ada tagihan aktif, dan errornya
 *    benar-benar ditangkap & ditampilkan di pemanggilnya (sebelumnya
 *    ValidationException dari cancelKunjungan() tidak ditangkap sama
 *    sekali di ListPendaftaran::cancel() / DetailPemeriksaan::
 *    batalkanRegistrasi()).
 * 7. [Rendah] PasienTable::fetchIhsSemua() sekarang dibatasi per-batch
 *    (IHS_BATCH_SIZE) supaya tidak berpotensi timeout kalau pasien
 *    belum ber-IHS jumlahnya banyak.
 * 8. [Rendah] Pasien pakai SoftDeletes tapi unique index nik/no_bpjs/
 *    no_paspor/nomor_rm di DB tidak mengecualikan baris terhapus --
 *    didokumentasikan jelas di model (bukan diperbaiki di level skema,
 *    lihat alasannya di komentar Pasien::class) karena belum ada fitur
 *    hapus pasien sama sekali saat ini.
 * 9. [Rendah] StorePasienRequest/UpdatePasienRequest/
 *    UpdateDokterProfilRequest (+ StoreJadwalPraktekRequest/
 *    StoreSharingFeeRequest yang ternyata juga tidak terpakai) dihapus --
 *    validasi asli ada di komponen Livewire masing-masing.
 * 10. [Rendah] JadwalPraktek::hasOverlap() sekarang benar utk jadwal yang
 *     melewati tengah malam (mis. 22:00-02:00).
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

    // ── #4: generateNomorAntrean() sargable + pengaman lompat nomor ──

    private function buatDokterPoli(): array
    {
        $dokterUser = User::create([
            'nama' => 'Dr. Antrean Test ' . uniqid(), 'email' => 'antrean-' . uniqid() . '@example.test',
            'password' => Hash::make('password'), 'is_active' => true,
        ]);
        $dokterUser->assignRole('dokter');
        $dokter = Dokter::create(['user_id' => $dokterUser->id]);
        $poli   = Poli::create(['nama' => 'Poli Antrean ' . uniqid(), 'kode' => 'PA' . rand(1000, 9999), 'is_active' => true]);

        return compact('dokter', 'poli');
    }

    private function buatKunjunganAntrean(Dokter $dokter, Poli $poli, string $nomorAntrean, string $status = 'menunggu'): Kunjungan
    {
        $pasien = Pasien::create([
            'nomor_rm' => 'RM-' . uniqid(), 'nama' => 'Pasien Antrean ' . uniqid(), 'tempat_lahir' => 'Denpasar',
            'tanggal_lahir' => '1990-01-01', 'jenis_kelamin' => 'L', 'alamat' => 'Jl. Test', 'telepon' => '08123',
        ]);

        return Kunjungan::create([
            'nomor_antrean' => $nomorAntrean, 'pasien_id' => $pasien->id,
            'dokter_id' => $dokter->id, 'poli_id' => $poli->id,
            'tanggal' => now(), 'status' => $status,
        ]);
    }

    /** @test */
    public function nomor_antrean_tetap_berurutan_normal_setelah_query_diganti_sargable(): void
    {
        ['dokter' => $dokter, 'poli' => $poli] = $this->buatDokterPoli();
        $service = app(KunjunganService::class);

        $n1 = $service->generateNomorAntrean($poli->id, now()->toDateString());
        $this->buatKunjunganAntrean($dokter, $poli, $n1);
        $n2 = $service->generateNomorAntrean($poli->id, now()->toDateString());

        $this->assertSame('W-001', $n1);
        $this->assertSame('W-002', $n2);
    }

    /** @test */
    public function nomor_antrean_melompati_nomor_yang_sudah_dipakai_kunjungan_aktif(): void
    {
        ['dokter' => $dokter, 'poli' => $poli] = $this->buatDokterPoli();
        // Data "tidak rapi" -- cuma ada 1 kunjungan aktif, tapi nomornya
        // W-002 (bukan W-001 seperti urutan normal, mis. dari input manual).
        // count()=1 -> tebakan awal count+1="W-002", yang PERSIS bentrok
        // dengan baris ini sendiri -- pengaman harus melompat ke W-003.
        $this->buatKunjunganAntrean($dokter, $poli, 'W-002');

        $nomor = app(KunjunganService::class)->generateNomorAntrean($poli->id, now()->toDateString());

        $this->assertSame('W-003', $nomor);
    }

    /** @test */
    public function nomor_antrean_kunjungan_dibatalkan_boleh_dipakai_ulang(): void
    {
        ['dokter' => $dokter, 'poli' => $poli] = $this->buatDokterPoli();
        $this->buatKunjunganAntrean($dokter, $poli, 'W-001', 'dibatalkan');

        // Kunjungan dibatalkan sengaja TIDAK dihitung -- perilaku existing,
        // jadi nomor W-001 boleh dipakai ulang oleh kunjungan baru.
        $nomor = app(KunjunganService::class)->generateNomorAntrean($poli->id, now()->toDateString());

        $this->assertSame('W-001', $nomor);
    }

    // ── #5: Validasi no_bpjs harus digit-only ─────────────────────

    /** @test */
    public function no_bpjs_yang_bukan_angka_ditolak_di_pasien_form(): void
    {
        $admin = User::where('email', 'admin@emr.app')->firstOrFail();
        $this->actingAs($admin);

        Livewire::test(PasienForm::class)
            ->set('nama', 'Pasien BPJS Test')
            ->set('tempat_lahir', 'Denpasar')
            ->set('tanggal_lahir', '1990-01-01')
            ->set('jenis_kelamin', 'L')
            ->set('tipe_pasien', 'WNI')
            ->set('nik', '3171012501900001')
            ->set('alamat', 'Jl. Test')
            ->set('telepon', '081234567890')
            ->set('no_bpjs', '12AB56789012') // 13 char campur huruf
            ->call('save')
            ->assertHasErrors(['no_bpjs' => 'regex']);
    }

    // ── #6: cancelKunjungan() menolak kalau masih ada tagihan aktif ──

    private function buatKunjunganDenganDokter(): Kunjungan
    {
        ['dokter' => $dokter, 'poli' => $poli] = $this->buatDokterPoli();
        return $this->buatKunjunganAntrean($dokter, $poli, 'W-' . rand(100, 999));
    }

    /** @test */
    public function batalkan_kunjungan_ditolak_kalau_masih_ada_tagihan_aktif(): void
    {
        $kunjungan = $this->buatKunjunganDenganDokter();
        Invoice::create([
            'kunjungan_id' => $kunjungan->id, 'nomor_invoice' => 'INV-' . uniqid(),
            'total_tagihan' => 100000, 'sisa' => 100000, 'status' => 'belum_bayar',
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(KunjunganService::class)->cancelKunjungan($kunjungan->id);
    }

    /** @test */
    public function batalkan_kunjungan_tetap_boleh_kalau_tagihannya_sendiri_sudah_dibatalkan(): void
    {
        $kunjungan = $this->buatKunjunganDenganDokter();
        Invoice::create([
            'kunjungan_id' => $kunjungan->id, 'nomor_invoice' => 'INV-' . uniqid(),
            'total_tagihan' => 100000, 'sisa' => 100000, 'status' => 'dibatalkan',
        ]);

        $hasil = app(KunjunganService::class)->cancelKunjungan($kunjungan->id);
        $this->assertSame('dibatalkan', $hasil->status);
    }

    /** @test */
    public function batalkan_kunjungan_tanpa_tagihan_tetap_normal(): void
    {
        $kunjungan = $this->buatKunjunganDenganDokter();

        $hasil = app(KunjunganService::class)->cancelKunjungan($kunjungan->id);
        $this->assertSame('dibatalkan', $hasil->status);
    }

    /** @test */
    public function list_pendaftaran_menampilkan_pesan_error_saat_batal_ditolak_karena_tagihan(): void
    {
        $kunjungan = $this->buatKunjunganDenganDokter();
        Invoice::create([
            'kunjungan_id' => $kunjungan->id, 'nomor_invoice' => 'INV-' . uniqid(),
            'total_tagihan' => 50000, 'sisa' => 50000, 'status' => 'belum_bayar',
        ]);

        $admin = User::where('email', 'admin@emr.app')->firstOrFail();
        $this->actingAs($admin);

        // Tidak boleh melempar exception sampai ke luar komponen -- harus
        // ditangkap & ditampilkan lewat notify (sebelumnya tidak ditangkap
        // sama sekali di sini).
        Livewire::test(ListPendaftaran::class)->call('cancel', $kunjungan->id);

        $this->assertSame('menunggu', $kunjungan->fresh()->status, 'Status kunjungan tidak boleh berubah karena pembatalan ditolak.');
    }

    /** @test */
    public function detail_pemeriksaan_menampilkan_pesan_error_saat_batal_ditolak_karena_tagihan(): void
    {
        $kunjungan = $this->buatKunjunganDenganDokter();
        Invoice::create([
            'kunjungan_id' => $kunjungan->id, 'nomor_invoice' => 'INV-' . uniqid(),
            'total_tagihan' => 50000, 'sisa' => 50000, 'status' => 'belum_bayar',
        ]);

        $admin = User::where('email', 'admin@emr.app')->firstOrFail();
        $this->actingAs($admin);

        Livewire::test(DetailPemeriksaan::class, ['kunjunganId' => $kunjungan->id])
            ->call('batalkanRegistrasi');

        $this->assertSame('menunggu', $kunjungan->fresh()->status);
    }

    // ── #7: fetchIhsSemua() dibatasi per-batch ────────────────────

    /** @test */
    public function fetch_ihs_semua_dibatasi_per_batch_dan_beri_tahu_sisanya(): void
    {
        ConfigSatuSehat::query()->delete();
        ConfigSatuSehat::create(['is_active' => true, 'environment' => 'sandbox']);
        \Illuminate\Support\Facades\Cache::forget('satusehat.aktif');

        // Data baseline (mis. dari PasienSeeder) juga bisa punya pasien
        // ihs_status kosong -- tandai semua "sudah" dulu (di dalam transaksi
        // test ini, ikut di-rollback) supaya batch di bawah pasti isinya
        // 25 pasien yang baru dibuat test ini sendiri, bukan tercampur data lain.
        Pasien::whereNull('ihs_status')->orWhere('ihs_status', 'error')
            ->update(['ihs_status' => 'ditemukan']);

        // 25 pasien belum ber-IHS -- batch size 20, jadi 5 harus tersisa.
        for ($i = 0; $i < 25; $i++) {
            Pasien::create([
                'nomor_rm' => 'RM-' . uniqid(), 'nama' => 'Pasien IHS ' . $i, 'tempat_lahir' => 'Denpasar',
                'tanggal_lahir' => '1990-01-01', 'jenis_kelamin' => 'L', 'alamat' => 'Jl. Test', 'telepon' => '08123',
                'tipe_pasien' => 'WNI', 'nik' => (string) (3171000000000000 + $i),
            ]);
        }

        $this->mock(SatuSehatIhsService::class, function ($mock) {
            // Ikut mereplikasi efek samping fetchPasien() sungguhan (update
            // ihs_status di pasien) -- supaya test ini benar-benar
            // membuktikan hanya 20 dari 25 pasien yang diproses, bukan cuma
            // menghitung counter Livewire-nya saja.
            $mock->shouldReceive('fetchPasien')
                ->andReturnUsing(function (Pasien $pasien) {
                    $pasien->update(['ihs_status' => 'ditemukan', 'ihs_synced_at' => now()]);
                    return ['status' => 'ditemukan', 'ihs_id' => 'IHS-TEST'];
                });
        });

        $admin = User::where('email', 'admin@emr.app')->firstOrFail();
        $this->actingAs($admin);

        $component = Livewire::test(PasienTable::class)->call('fetchIhsSemua');

        $component->assertSet('ihsTotal', 20);
        $component->assertSet('ihsDone', 20);

        $sisa = Pasien::where('nik', 'like', '3171%')->whereNull('ihs_status')->count();
        $this->assertSame(5, $sisa, 'Harus ada 5 pasien tersisa dari 25 (batch=20).');
    }

    // ── #10: JadwalPraktek::hasOverlap() utk jadwal lewat tengah malam ──

    /** @test */
    public function jadwal_lewat_tengah_malam_yang_bentrok_terdeteksi(): void
    {
        ['dokter' => $dokter, 'poli' => $poli] = $this->buatDokterPoli();
        $dokterPoli = DokterPoli::create(['dokter_id' => $dokter->id, 'poli_id' => $poli->id, 'is_aktif' => true]);
        JadwalPraktek::create([
            'dokter_poli_id' => $dokterPoli->id, 'hari' => 'senin',
            'jam_mulai' => '22:00', 'jam_selesai' => '02:00', 'is_aktif' => true,
        ]);

        // Dua-duanya jadwal "senin" yang sama-sama melewati tengah malam
        // (22:00-02:00 dan 23:00-03:00) -- beririsan nyata di jam 23:00-02:00
        // (Senin malam sampai dini hari Selasa). Tanpa perbaikan, kedua jam
        // selesai (02:00 & 03:00) dibaca lebih kecil dari jam mulainya
        // sendiri di hari kalender yang sama, jadi overlap salah dianggap
        // tidak ada.
        $bentrok = JadwalPraktek::hasOverlap($dokterPoli->id, 'senin', '23:00', '03:00');
        $this->assertTrue($bentrok, 'Jadwal 23:00-03:00 harus terdeteksi bentrok dengan 22:00-02:00 (sama-sama lewat tengah malam).');
    }

    /** @test */
    public function jadwal_lewat_tengah_malam_yang_tidak_bentrok_tidak_terdeteksi(): void
    {
        ['dokter' => $dokter, 'poli' => $poli] = $this->buatDokterPoli();
        $dokterPoli = DokterPoli::create(['dokter_id' => $dokter->id, 'poli_id' => $poli->id, 'is_aktif' => true]);
        JadwalPraktek::create([
            'dokter_poli_id' => $dokterPoli->id, 'hari' => 'senin',
            'jam_mulai' => '22:00', 'jam_selesai' => '02:00', 'is_aktif' => true,
        ]);

        // 03:00-06:00 TIDAK beririsan dengan ekor 22:00-02:00.
        $bentrok = JadwalPraktek::hasOverlap($dokterPoli->id, 'senin', '03:00', '06:00');
        $this->assertFalse($bentrok, 'Jadwal 03:00-06:00 tidak boleh dianggap bentrok dengan 22:00-02:00.');
    }
}

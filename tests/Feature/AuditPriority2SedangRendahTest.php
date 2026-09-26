<?php

namespace Tests\Feature;

use App\Livewire\Keuangan\Penagihan\PenagihanForm;
use App\Models\Asuransi;
use App\Models\DepositPasien;
use App\Models\Dokter;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Kunjungan;
use App\Models\Pasien;
use App\Models\PenagihanAsuransi;
use App\Models\PiutangAsuransi;
use App\Models\Poli;
use App\Models\SesiKas;
use App\Models\TransaksiDeposit;
use App\Models\User;
use App\Services\Akuntansi\PeriodeAkuntansiService;
use App\Services\Asuransi\PenagihanService;
use App\Services\Kasir\BillingService;
use App\Services\Kasir\DepositService;
use App\Services\Kasir\SesiKasService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regresi Audit Priority 2 (Kasir & Billing lanjutan + Piutang SoD), temuan Sedang & Rendah:
 *
 * [Sedang]
 * 1. verifySuperAdminPassword() dikonsolidasi ke trait VerifiesSuperAdminPassword
 *    (dari 3 duplikat identik di SesiKasService/BillingService/
 *    PeriodeAkuntansiService) & diperbaiki supaya mengecek SEMUA akun
 *    super_admin aktif, bukan cuma yang pertama.
 * 2. DepositService::topup() sekarang lockForUpdate() sebelum baca saldo
 *    (konsisten dgn pakai()/refund()/refundManual()).
 * 3. PenagihanService::buatPenagihan() sekarang lockForUpdate() piutang
 *    yang dipilih.
 * 4. PenagihanForm::buat() sekarang authorize('piutang.tagih') sbg
 *    defense-in-depth.
 *
 * [Rendah]
 * 5. Nomor generation (DepositService::generateNomorTransaksi(),
 *    PenagihanService::generateNomorPenagihan()/generateNomorPembayaran())
 *    sekarang lockForUpdate(), pola sama dgn ReturResep/ReturGr.
 *
 * Pakai DatabaseTransactions -- bukan RefreshDatabase.
 */
class AuditPriority2SedangRendahTest extends TestCase
{
    use DatabaseTransactions;

    // ── Fixtures ─────────────────────────────────────────────────

    private function buatUserDenganRole(string $role): User
    {
        $user = User::create([
            'nama' => ucfirst($role) . ' Test ' . uniqid(),
            'email' => strtolower(str_replace(' ', '', $role)) . '-' . uniqid() . '@example.test',
            'password' => Hash::make('password'), 'is_active' => true,
        ]);
        $user->assignRole($role);
        return $user;
    }

    private function buatSuperAdmin(string $password): User
    {
        $u = User::create([
            'nama' => 'Super Admin Test ' . uniqid(), 'email' => 'superadmin-' . uniqid() . '@example.test',
            'password' => Hash::make($password), 'is_active' => true,
        ]);
        $u->assignRole('super_admin');
        return $u;
    }

    private function buatKunjunganSelesai(): Kunjungan
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
            'tanggal' => now(), 'status' => 'selesai',
        ]);
    }

    private function buatInvoice(Kunjungan $kunjungan, float $total = 100000): Invoice
    {
        $invoice = Invoice::create([
            'kunjungan_id' => $kunjungan->id, 'nomor_invoice' => 'INV-' . uniqid(),
            'total_tagihan' => $total, 'total_bayar' => 0, 'sisa' => $total, 'status' => 'belum_bayar',
        ]);
        InvoiceItem::create([
            'billing_id' => $invoice->id, 'jenis' => 'manual', 'nama_item' => 'Biaya Konsultasi',
            'qty' => 1, 'satuan' => 'layanan', 'harga_satuan' => $total, 'diskon_item' => 0, 'subtotal' => $total,
        ]);
        return $invoice;
    }

    // ── #1: verifySuperAdminPassword() menerima SEMUA akun super_admin ──

    /** @test */
    public function verify_super_admin_password_menerima_password_akun_kedua_bukan_cuma_yang_pertama(): void
    {
        $admin1 = $this->buatSuperAdmin('password-admin-satu');
        $admin2 = $this->buatSuperAdmin('password-admin-dua');

        // Via BillingService (dipakai jg oleh SesiKasService & PeriodeAkuntansiService
        // lewat trait yang sama -- VerifiesSuperAdminPassword).
        $verified = app(BillingService::class)->verifySuperAdminPassword('password-admin-dua');
        $this->assertSame($admin2->id, $verified->id);

        // Pastikan password akun pertama tetap valid juga (bukan cuma yg kedua).
        $verifiedLagi = app(BillingService::class)->verifySuperAdminPassword('password-admin-satu');
        $this->assertSame($admin1->id, $verifiedLagi->id);
    }

    /** @test */
    public function sesi_kas_service_dan_periode_akuntansi_service_ikut_pakai_trait_yang_sama(): void
    {
        $admin2 = $this->buatSuperAdmin('password-admin-dua-lagi');
        // Buat 1 admin lain dgn password beda supaya benar2 ada >1 akun.
        $this->buatSuperAdmin('password-admin-lainnya');

        $v1 = app(SesiKasService::class)->verifySuperAdminPassword('password-admin-dua-lagi');
        $v2 = app(PeriodeAkuntansiService::class)->verifySuperAdminPassword('password-admin-dua-lagi');

        $this->assertSame($admin2->id, $v1->id);
        $this->assertSame($admin2->id, $v2->id);
    }

    /** @test */
    public function verify_super_admin_password_tetap_menolak_password_yang_salah(): void
    {
        $this->buatSuperAdmin('password-benar');

        $this->expectException(\RuntimeException::class);
        app(BillingService::class)->verifySuperAdminPassword('password-salah-total');
    }

    // ── #2: DepositService::topup() -- regresi setelah tambah lock ──────

    /** @test */
    public function topup_deposit_tetap_berhasil_menambah_saldo_setelah_ditambah_lock(): void
    {
        $kunjungan = $this->buatKunjunganSelesai();
        $pasien    = Pasien::find($kunjungan->pasien_id);
        $kasir     = $this->buatUserDenganRole('kasir');

        $trx = app(DepositService::class)->topup($pasien, 200000, $kasir->id);

        $this->assertSame(200000.0, (float) $trx->jumlah);
        $this->assertSame(0.0, (float) $trx->saldo_sebelum);
        $this->assertSame(200000.0, (float) $trx->saldo_sesudah);
        $this->assertSame(200000.0, (float) DepositPasien::where('pasien_id', $pasien->id)->value('saldo'));

        // Topup kedua di hari yang sama -- nomor transaksi harus tetap urut
        // (regresi lockForUpdate() di generateNomorTransaksi()).
        $trx2 = app(DepositService::class)->topup($pasien, 50000, $kasir->id);
        $this->assertSame(250000.0, (float) DepositPasien::where('pasien_id', $pasien->id)->value('saldo'));
        $this->assertNotSame($trx->nomor_transaksi, $trx2->nomor_transaksi);
    }

    // ── #3: PenagihanService::buatPenagihan() -- regresi setelah tambah lock ──

    /** @test */
    public function buat_penagihan_tetap_berhasil_mengumpulkan_piutang_tertagih_setelah_ditambah_lock(): void
    {
        $kunjungan = $this->buatKunjunganSelesai();
        $invoice   = $this->buatInvoice($kunjungan, 500000);
        $asuransi  = Asuransi::create(['kode' => 'AS-' . uniqid(), 'nama' => 'Asuransi Test', 'tipe' => 'swasta', 'is_active' => true]);
        $pasien    = Pasien::find($kunjungan->pasien_id);

        $piutang = PiutangAsuransi::create([
            'nomor_piutang' => 'PIU-' . uniqid(), 'billing_id' => $invoice->id, 'asuransi_id' => $asuransi->id,
            'pasien_id' => $pasien->id, 'jumlah_piutang' => 500000, 'jumlah_dibayar' => 0,
            'sisa_piutang' => 500000, 'tanggal_piutang' => now(), 'status' => 'tertagih',
        ]);

        $userId = $this->buatUserDenganRole('keuangan')->id;

        $penagihan = app(PenagihanService::class)->buatPenagihan($asuransi->id, [$piutang->id], $userId);

        $this->assertSame(500000.0, (float) $penagihan->total_tagihan);
        $this->assertSame('diajukan', $piutang->fresh()->status);
        $this->assertSame($penagihan->id, $piutang->fresh()->penagihan_id);
    }

    // ── #4: PenagihanForm::buat() -- authorize() defense-in-depth ────────

    /** @test */
    public function role_tanpa_piutang_tagih_ditolak_buat_penagihan(): void
    {
        // piutang_verifikator sengaja TIDAK dikasih piutang.tagih (SoD:
        // verifikator cuma mencatat pelunasan, bukan menagih aktif).
        $verifikator = $this->buatUserDenganRole('piutang_verifikator');
        $this->actingAs($verifikator);

        Livewire::test(PenagihanForm::class)
            ->call('buat')
            ->assertForbidden();
    }

    /** @test */
    public function piutang_kolektor_tetap_bisa_buat_penagihan(): void
    {
        $kunjungan = $this->buatKunjunganSelesai();
        $invoice   = $this->buatInvoice($kunjungan, 300000);
        $asuransi  = Asuransi::create(['kode' => 'AS-' . uniqid(), 'nama' => 'Asuransi Test', 'tipe' => 'swasta', 'is_active' => true]);
        $pasien    = Pasien::find($kunjungan->pasien_id);

        $piutang = PiutangAsuransi::create([
            'nomor_piutang' => 'PIU-' . uniqid(), 'billing_id' => $invoice->id, 'asuransi_id' => $asuransi->id,
            'pasien_id' => $pasien->id, 'jumlah_piutang' => 300000, 'jumlah_dibayar' => 0,
            'sisa_piutang' => 300000, 'tanggal_piutang' => now(), 'status' => 'tertagih',
        ]);

        $kolektor = $this->buatUserDenganRole('piutang_kolektor');
        $this->actingAs($kolektor);

        Livewire::test(PenagihanForm::class)
            ->set('asuransiId', $asuransi->id)
            ->set('piutangIds', [$piutang->id])
            ->call('buat')
            ->assertOk();

        $this->assertSame('diajukan', $piutang->fresh()->status);
    }
}

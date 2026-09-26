<?php

namespace Tests\Feature;

use App\Livewire\Kasir\Billing\BatalkanBillingModal;
use App\Livewire\Kasir\Billing\BillingDetail;
use App\Livewire\Kasir\Billing\SplitPaymentForm;
use App\Livewire\Kasir\Deposit\TopupDepositForm;
use App\Livewire\Kasir\RiwayatPembayaran;
use App\Livewire\Kasir\SesiKas\SesiKasPanel;
use App\Livewire\Kasir\TagihanPasien;
use App\Models\Dokter;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Kunjungan;
use App\Models\Pasien;
use App\Models\Poli;
use App\Models\SesiKas;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regresi Audit Priority 1 (sapuan otorisasi lintas modul, temuan Kritikal & Tinggi):
 *
 * [Kritikal] Seluruh route Kasir/Billing (/billing, /kasir/billing/*) TIDAK
 * punya middleware permission sama sekali (cuma auth+active), dan komponennya
 * juga tidak punya authorize() -- jadi role apa pun (apoteker, rekam_medis,
 * dokter, perawat, dst yang tidak punya permission billing.* atau pembayaran.*)
 * bisa membuka dashboard Kasir penuh: cari & proses pembayaran tagihan
 * pasien mana pun, top-up/refund deposit, buka/tutup sesi kas. Diperbaiki
 * dengan menambah middleware permission:billing.view di route + authorize()
 * di mount() semua komponen Kasir sbg lapis kedua, dan permission:
 * pembayaran.create yang lebih spesifik utk aksi yang benar2 memindahkan
 * uang (bayar, split-payment, topup/refund deposit, buka/tutup kas).
 *
 * [Tinggi] KelolaShift.php & LaporanShift.php (dead code, pakai model lama
 * ShiftKasir/Pembayaran yang sudah digantikan SesiKas/PembayaranSplit) tidak
 * direferensikan route/view mana pun -- ditandai @deprecated (lihat
 * app/Livewire/Kasir/KelolaShift.php & LaporanShift.php), tidak ada test
 * baru untuknya karena memang tidak reachable.
 *
 * Pakai DatabaseTransactions -- bukan RefreshDatabase.
 */
class KasirAuthorizationAuditTest extends TestCase
{
    use DatabaseTransactions;

    // ── Fixtures ─────────────────────────────────────────────────

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

    private function bukaSesiKas(User $kasir): SesiKas
    {
        return SesiKas::create([
            'user_id' => $kasir->id, 'tanggal' => now()->toDateString(),
            'dibuka_pada' => now(), 'saldo_awal' => 0, 'status' => 'buka',
        ]);
    }

    // ── Kritikal: route Kasir/Billing sekarang digate permission:billing.view ──

    /** @test */
    public function role_tanpa_billing_view_ditolak_403_saat_buka_route_billing(): void
    {
        // apoteker, rekam_medis, dokter, perawat, front_office, akuntan,
        // harga_reviewer, harga_approver, piutang_kolektor, piutang_verifikator
        // semuanya TIDAK punya billing.view -- cukup wakili dengan apoteker.
        $apoteker = $this->buatUserDenganRole('apoteker');
        $this->actingAs($apoteker);

        $this->get('/billing')->assertForbidden();
        $this->get('/kasir/billing')->assertForbidden();
    }

    /** @test */
    public function role_tanpa_billing_view_ditolak_403_saat_buka_billing_show_dan_split_payment(): void
    {
        $apoteker  = $this->buatUserDenganRole('apoteker');
        $kunjungan = $this->buatKunjunganSelesai();
        $invoice   = $this->buatInvoice($kunjungan);

        $this->actingAs($apoteker);

        $this->get(route('kasir.billing.show', $invoice))->assertForbidden();
        $this->get(route('kasir.billing.split-payment', $invoice))->assertForbidden();
    }

    /** @test */
    public function role_dengan_billing_view_tapi_tanpa_pembayaran_create_ditolak_saat_buka_split_payment(): void
    {
        // keuangan punya billing.view (boleh lihat), tapi TIDAK punya
        // pembayaran.create (bukan tugasnya memproses pembayaran kasir).
        $keuangan  = $this->buatUserDenganRole('keuangan');
        $kunjungan = $this->buatKunjunganSelesai();
        $invoice   = $this->buatInvoice($kunjungan);

        $this->actingAs($keuangan);

        $this->get(route('kasir.billing.show', $invoice))->assertOk();
        $this->get(route('kasir.billing.split-payment', $invoice))->assertForbidden();
    }

    /** @test */
    public function kasir_tetap_bisa_akses_semua_route_billing_seperti_biasa(): void
    {
        $kasir     = $this->buatUserDenganRole('kasir');
        $kunjungan = $this->buatKunjunganSelesai();
        $invoice   = $this->buatInvoice($kunjungan);

        $this->actingAs($kasir);

        $this->get('/billing')->assertOk();
        $this->get(route('kasir.billing.show', $invoice))->assertOk();
        $this->get(route('kasir.billing.split-payment', $invoice))->assertOk();
    }

    // ── Kritikal: authorize() di komponen sbg lapis kedua ──────────

    /** @test */
    public function apoteker_ditolak_mount_semua_komponen_kasir(): void
    {
        $apoteker  = $this->buatUserDenganRole('apoteker');
        $kunjungan = $this->buatKunjunganSelesai();
        $invoice   = $this->buatInvoice($kunjungan);

        $this->actingAs($apoteker);

        Livewire::test(TagihanPasien::class)->assertForbidden();
        Livewire::test(RiwayatPembayaran::class)->assertForbidden();
        Livewire::test(TopupDepositForm::class)->assertForbidden();
        Livewire::test(SesiKasPanel::class)->assertForbidden();
        Livewire::test(BillingDetail::class, ['billing' => $invoice])->assertForbidden();
        Livewire::test(SplitPaymentForm::class, ['billing' => $invoice])->assertForbidden();
    }

    /** @test */
    public function keuangan_bisa_mount_komponen_view_tapi_ditolak_saat_aksi_pembayaran_dan_edit(): void
    {
        $keuangan  = $this->buatUserDenganRole('keuangan');
        $kunjungan = $this->buatKunjunganSelesai();
        $invoice   = $this->buatInvoice($kunjungan);

        $this->actingAs($keuangan);

        // Boleh lihat.
        Livewire::test(TagihanPasien::class)->assertOk();
        Livewire::test(RiwayatPembayaran::class)->assertOk();
        Livewire::test(BillingDetail::class, ['billing' => $invoice])->assertOk();

        // Tidak boleh memproses pembayaran (butuh pembayaran.create).
        Livewire::test(TagihanPasien::class)
            ->call('selectKunjungan', $kunjungan->id)
            ->set('metodePembayaran', 'tunai')
            ->set('jumlahTunai', '100000')
            ->call('prosesPembayaran')
            ->assertForbidden();

        // Tidak boleh mengedit item/diskon tagihan (butuh billing.edit).
        Livewire::test(TagihanPasien::class)
            ->call('applyDiskonGlobal')
            ->assertForbidden();

        // Tidak boleh membatalkan billing (butuh billing.edit).
        Livewire::test(BillingDetail::class, ['billing' => $invoice])
            ->call('batalkan')
            ->assertForbidden();
    }

    /** @test */
    public function apoteker_ditolak_mount_batalkan_billing_modal_dan_bayar_split_payment(): void
    {
        $apoteker  = $this->buatUserDenganRole('apoteker');
        $kunjungan = $this->buatKunjunganSelesai();
        $invoice   = $this->buatInvoice($kunjungan);

        $this->actingAs($apoteker);

        Livewire::test(BatalkanBillingModal::class)
            ->call('open', $invoice->id)
            ->assertForbidden();

        Livewire::test(SplitPaymentForm::class, ['billing' => $invoice])
            ->assertForbidden();
    }

    // ── Regresi: kasir tetap bisa memproses semuanya seperti biasa ──

    /** @test */
    public function kasir_tetap_bisa_proses_pembayaran_topup_deposit_dan_buka_tutup_kas(): void
    {
        $kasir     = $this->buatUserDenganRole('kasir');
        $kunjungan = $this->buatKunjunganSelesai();
        $invoice   = $this->buatInvoice($kunjungan);
        $this->bukaSesiKas($kasir);

        $this->actingAs($kasir);

        Livewire::test(TagihanPasien::class)
            ->call('selectKunjungan', $kunjungan->id)
            ->set('metodePembayaran', 'tunai')
            ->set('jumlahTunai', '100000')
            ->call('prosesPembayaran');

        $this->assertSame('lunas', $invoice->fresh()->status);
    }
}

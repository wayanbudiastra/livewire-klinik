<?php

namespace Tests\Feature;

use App\Livewire\Pengaturan\User\UserForm;
use App\Models\Akuntansi\JurnalPending;
use App\Models\Dokter;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\KonfigurasiSharingFeePerawat;
use App\Models\Kunjungan;
use App\Models\MasterTindakan;
use App\Models\Pasien;
use App\Models\Poli;
use App\Models\SesiKas;
use App\Models\SharingFee;
use App\Models\Tindakan;
use App\Models\User;
use App\Services\Akuntansi\SharingFeeService;
use App\Services\Kasir\BillingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Fitur baru: sharing fee dokter default 10% (bulk-set + default utk dokter
 * baru) + sharing fee perawat 5% global (dihitung dari Tindakan::pelaksana_id).
 * Lihat SharingFeeService::catatSharingFeePerawat() dan
 * KonfigurasiSharingFeePerawat.
 *
 * Pakai DatabaseTransactions -- bukan RefreshDatabase.
 */
class SharingFeePerawatTest extends TestCase
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

    private function buatKunjungan(?int $dokterId = null): Kunjungan
    {
        $poli   = Poli::create(['nama' => 'Poli Test ' . uniqid(), 'kode' => 'PT' . rand(1000, 9999), 'is_active' => true]);
        $pasien = Pasien::create([
            'nomor_rm' => 'RM-' . uniqid(), 'nama' => 'Pasien Test ' . uniqid(), 'tempat_lahir' => 'Denpasar',
            'tanggal_lahir' => '1990-01-01', 'jenis_kelamin' => 'L', 'alamat' => 'Jl. Test', 'telepon' => '08123',
        ]);

        return Kunjungan::create([
            'nomor_antrean' => 'W-' . rand(100, 999), 'pasien_id' => $pasien->id,
            'dokter_id' => $dokterId, 'poli_id' => $poli->id,
            'tanggal' => now(), 'status' => 'selesai',
        ]);
    }

    private function buatMasterTindakan(float $tarif): MasterTindakan
    {
        return MasterTindakan::create([
            'kode' => 'MT' . rand(1000, 9999), 'nama' => 'Tindakan Test ' . uniqid(),
            'tarif' => $tarif, 'kategori' => 'umum', 'is_active' => true,
        ]);
    }

    /** Bikin 1 Tindakan + InvoiceItem (jenis=tindakan, ref_id=tindakan->id) yg sudah tersambung ke $invoice. */
    private function tambahItemTindakan(Invoice $invoice, Kunjungan $kunjungan, int $pelaksanaId, float $subtotal): Tindakan
    {
        $masterTindakan = $this->buatMasterTindakan($subtotal);
        $tindakan = Tindakan::create([
            'kunjungan_id' => $kunjungan->id, 'master_tindakan_id' => $masterTindakan->id,
            'pelaksana_id' => $pelaksanaId, 'jumlah' => 1,
        ]);
        InvoiceItem::create([
            'billing_id' => $invoice->id, 'jenis' => 'tindakan', 'ref_id' => $tindakan->id,
            'nama_item' => $masterTindakan->nama, 'qty' => 1, 'satuan' => 'layanan',
            'harga_satuan' => $subtotal, 'diskon_item' => 0, 'subtotal' => $subtotal,
        ]);
        return $tindakan;
    }

    private function buatInvoiceKosong(Kunjungan $kunjungan, float $total): Invoice
    {
        return Invoice::create([
            'kunjungan_id' => $kunjungan->id, 'nomor_invoice' => 'INV-' . uniqid(),
            'total_tagihan' => $total, 'total_bayar' => 0, 'sisa' => $total, 'status' => 'belum_bayar',
        ]);
    }

    private function bukaSesiKas(User $kasir): SesiKas
    {
        return SesiKas::create([
            'user_id' => $kasir->id, 'tanggal' => now()->toDateString(),
            'dibuka_pada' => now(), 'saldo_awal' => 0, 'status' => 'buka',
        ]);
    }

    private function setPersentasePerawat(float $persen): void
    {
        KonfigurasiSharingFeePerawat::updateOrCreate(['kategori' => 'tindakan'], ['persentase' => $persen]);
        KonfigurasiSharingFeePerawat::clearCache('tindakan');
    }

    // ── Kalkulasi sharing fee perawat ───────────────────────────────

    /** @test */
    public function perawat_hanya_dapat_fee_dari_tindakan_yg_dia_kerjakan_sendiri_bukan_seluruh_kunjungan(): void
    {
        $this->setPersentasePerawat(5);

        $kasir      = $this->buatUserDenganRole('kasir');
        $dokterUser = $this->buatUserDenganRole('dokter');
        $dokter     = Dokter::create(['user_id' => $dokterUser->id]);
        $perawat    = $this->buatUserDenganRole('perawat');

        $kunjungan = $this->buatKunjungan($dokter->id);
        $invoice   = $this->buatInvoiceKosong($kunjungan, 300000);

        // Tindakan #1 dikerjakan PERAWAT (subtotal 200rb) -- kena fee.
        $this->tambahItemTindakan($invoice, $kunjungan, $perawat->id, 200000);
        // Tindakan #2 dikerjakan DOKTER, bukan perawat (subtotal 100rb) -- TIDAK kena fee perawat.
        $this->tambahItemTindakan($invoice, $kunjungan, $dokterUser->id, 100000);

        $sesiKas = $this->bukaSesiKas($kasir);
        app(BillingService::class)->prosesSplitPayment(
            $invoice, [['metode' => 'tunai', 'jumlah' => 300000]], $kasir->id, $sesiKas
        );

        $jurnal = JurnalPending::where('sumber_tipe', 'billing')
            ->where('sumber_id', $invoice->id)
            ->where('tipe_transaksi', 'sharing_fee_perawat')
            ->first();

        $this->assertNotNull($jurnal, 'Jurnal sharing_fee_perawat harus tercatat.');
        // 5% dari 200rb (HANYA tindakan yg dikerjakan perawat) = 10rb, BUKAN 5% dari total 300rb (15rb).
        $this->assertEquals(10000, (float) $jurnal->nominal);
        $this->assertSame(SharingFeeService::AKUN_BIAYA_JASA_PERAWAT, $jurnal->kode_akun_debit);
        $this->assertSame(SharingFeeService::AKUN_HUTANG_JASA_PERAWAT, $jurnal->kode_akun_kredit);
    }

    /** @test */
    public function fee_beberapa_perawat_berbeda_diakumulasi_jadi_satu_baris_jurnal_dgn_rincian_di_metadata(): void
    {
        $this->setPersentasePerawat(5);

        $kasir    = $this->buatUserDenganRole('kasir');
        $perawatA = $this->buatUserDenganRole('perawat');
        $perawatB = $this->buatUserDenganRole('perawat');

        $kunjungan = $this->buatKunjungan();
        $invoice   = $this->buatInvoiceKosong($kunjungan, 300000);

        $this->tambahItemTindakan($invoice, $kunjungan, $perawatA->id, 200000); // fee 10rb
        $this->tambahItemTindakan($invoice, $kunjungan, $perawatB->id, 100000); // fee 5rb

        $sesiKas = $this->bukaSesiKas($kasir);
        app(BillingService::class)->prosesSplitPayment(
            $invoice, [['metode' => 'tunai', 'jumlah' => 300000]], $kasir->id, $sesiKas
        );

        $jurnal = JurnalPending::where('sumber_tipe', 'billing')
            ->where('sumber_id', $invoice->id)
            ->where('tipe_transaksi', 'sharing_fee_perawat')
            ->first();

        $this->assertNotNull($jurnal);
        $this->assertEquals(15000, (float) $jurnal->nominal); // 10rb + 5rb, satu baris gabungan
        $this->assertCount(2, $jurnal->metadata['rincian_perawat']);
    }

    /** @test */
    public function tidak_ada_fee_perawat_kalau_persentase_diset_0(): void
    {
        $this->setPersentasePerawat(0);

        $kasir   = $this->buatUserDenganRole('kasir');
        $perawat = $this->buatUserDenganRole('perawat');

        $kunjungan = $this->buatKunjungan();
        $invoice   = $this->buatInvoiceKosong($kunjungan, 200000);
        $this->tambahItemTindakan($invoice, $kunjungan, $perawat->id, 200000);

        $sesiKas = $this->bukaSesiKas($kasir);
        app(BillingService::class)->prosesSplitPayment(
            $invoice, [['metode' => 'tunai', 'jumlah' => 200000]], $kasir->id, $sesiKas
        );

        $this->assertSame(0, JurnalPending::where('sumber_tipe', 'billing')
            ->where('sumber_id', $invoice->id)
            ->where('tipe_transaksi', 'sharing_fee_perawat')
            ->count());
    }

    /** @test */
    public function pembatalan_billing_mereversal_jurnal_sharing_fee_perawat_yang_sudah_diposting(): void
    {
        $this->setPersentasePerawat(5);

        $kasir      = $this->buatUserDenganRole('kasir');
        $perawat    = $this->buatUserDenganRole('perawat');
        $superAdmin = User::role('super_admin')->where('is_active', true)->first();
        if (! $superAdmin) {
            $superAdmin = $this->buatUserDenganRole('super_admin');
        }
        $superAdmin->update(['password' => Hash::make('super-secret-test')]);

        $kunjungan = $this->buatKunjungan();
        $invoice   = $this->buatInvoiceKosong($kunjungan, 200000);
        $this->tambahItemTindakan($invoice, $kunjungan, $perawat->id, 200000);

        $sesiKas = $this->bukaSesiKas($kasir);
        app(BillingService::class)->prosesSplitPayment(
            $invoice, [['metode' => 'tunai', 'jumlah' => 200000]], $kasir->id, $sesiKas
        );

        // Posting jurnal pending yg baru tercatat spy statusnya 'posted' (spy reversal beneran jalan).
        $pending = JurnalPending::where('sumber_tipe', 'billing')->where('sumber_id', $invoice->id)->get();
        app(\App\Services\Akuntansi\JurnalService::class)->posting($pending->pluck('id')->all(), $superAdmin->id);

        app(BillingService::class)->batalkanBilling(
            $invoice->fresh(), 'super-secret-test', 'Alasan pembatalan test', $kasir->id
        );

        $reversal = JurnalPending::where('sumber_tipe', 'billing')
            ->where('sumber_id', $invoice->id)
            ->where('tipe_transaksi', 'pembatalan_sharing_fee_perawat')
            ->first();

        $this->assertNotNull($reversal, 'Reversal sharing_fee_perawat harus tercatat & langsung diposting.');
        $this->assertSame('posted', $reversal->status);
        $this->assertEquals(10000, (float) $reversal->nominal);
        // Dibalik dari baris asli: debit <-> kredit tertukar.
        $this->assertSame(SharingFeeService::AKUN_HUTANG_JASA_PERAWAT, $reversal->kode_akun_debit);
        $this->assertSame(SharingFeeService::AKUN_BIAYA_JASA_PERAWAT, $reversal->kode_akun_kredit);
    }

    // ── Dokter default 10% ──────────────────────────────────────────

    /** @test */
    public function dokter_baru_lewat_userform_otomatis_dapat_sharing_fee_tindakan_10_persen(): void
    {
        $superAdmin = $this->buatUserDenganRole('super_admin');
        $this->actingAs($superAdmin);

        $email = 'dokterbaru-' . uniqid() . '@example.test';

        Livewire::test(UserForm::class)
            ->call('openCreate')
            ->set('nama', 'Dr Baru Test')
            ->set('email', $email)
            ->set('password', 'password123')
            ->set('password_confirmation', 'password123')
            ->set('role', 'dokter')
            ->call('save');

        $user   = User::where('email', $email)->firstOrFail();
        $dokter = Dokter::where('user_id', $user->id)->first();

        $this->assertNotNull($dokter);
        $fee = SharingFee::where('dokter_id', $dokter->id)->where('kategori', 'tindakan')->first();
        $this->assertNotNull($fee);
        $this->assertEquals(10, (float) $fee->persentase);
    }

    /** @test */
    public function edit_dokter_existing_tidak_menimpa_sharing_fee_custom_yg_sudah_diset(): void
    {
        $superAdmin = $this->buatUserDenganRole('super_admin');
        $this->actingAs($superAdmin);

        $dokterUser = $this->buatUserDenganRole('dokter');
        $dokter     = Dokter::create(['user_id' => $dokterUser->id]);
        SharingFee::create(['dokter_id' => $dokter->id, 'kategori' => 'tindakan', 'persentase' => 25]);

        Livewire::test(UserForm::class)
            ->call('openEdit', $dokterUser->id)
            ->set('nama', 'Nama Diubah Test')
            ->call('save');

        $fee = SharingFee::where('dokter_id', $dokter->id)->where('kategori', 'tindakan')->first();
        $this->assertEquals(25, (float) $fee->persentase, 'Edit user existing tidak boleh menimpa persentase custom 25% jadi default 10%.');
    }
}

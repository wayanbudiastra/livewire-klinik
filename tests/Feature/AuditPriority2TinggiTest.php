<?php

namespace Tests\Feature;

use App\Livewire\Kasir\Billing\SplitPaymentForm;
use App\Models\Asuransi;
use App\Models\DepositPasien;
use App\Models\Dokter;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Kunjungan;
use App\Models\Pasien;
use App\Models\PembayaranSplit;
use App\Models\PenagihanAsuransi;
use App\Models\PenagihanItem;
use App\Models\PiutangAsuransi;
use App\Models\Poli;
use App\Models\SesiKas;
use App\Models\TransaksiDeposit;
use App\Models\User;
use App\Services\Asuransi\PenagihanService;
use App\Services\Kasir\BillingService;
use App\Services\Kasir\SesiKasService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regresi Audit Priority 2 (Kasir & Billing lanjutan + Piutang SoD), temuan Tinggi:
 *
 * 1. [Tinggi] BillingService::prosesSplitPayment() -- race-condition
 *    double-payment yang sama persis dgn bug yang sudah diperbaiki di
 *    TagihanPasien::prosesPembayaran() (Audit Transaksi #2), tapi jalur
 *    split-payment ini terlewat. Diperbaiki dgn kunci ulang + cek ulang
 *    status invoice DI DALAM transaksi (lockForUpdate()).
 *
 * 2. [Tinggi] BillingService::batalkanBilling() -- cek status
 *    'dibatalkan' juga di luar transaksi tanpa lock -- 2 klik "Batalkan"
 *    bersamaan bisa memicu refund deposit DOBEL. Diperbaiki dgn pola
 *    yang sama (lockForUpdate() + re-check di dalam transaksi).
 *
 * 3. [Tinggi] SesiKasService::bukaKas() -- bisa membuka 2 sesi kas aktif
 *    sekaligus utk kasir yang sama (tidak ada lock maupun unique
 *    constraint di DB). Diperbaiki dgn mengunci baris users milik kasir
 *    tsb sbg proxy lock (sesi_kas sendiri belum tentu punya baris utk
 *    hari ini) di dalam DB::transaction().
 *
 * 4. [Tinggi] PenagihanService::catatPembayaran() -- tidak ada lock sama
 *    sekali (beda dari service pembayaran lain yang sudah dibenahi) --
 *    2 klik "Catat Bayar" bersamaan / 2 tab dgn data sisa_tagihan basi
 *    bisa dobel-alokasi pembayaran ke piutang yang sama. Diperbaiki dgn
 *    kunci ulang + cek ulang sisa tagihan DI DALAM transaksi.
 *
 * Pakai DatabaseTransactions -- bukan RefreshDatabase.
 */
class AuditPriority2TinggiTest extends TestCase
{
    use DatabaseTransactions;

    // ── Fixtures ─────────────────────────────────────────────────

    private function buatKasir(): User
    {
        $user = User::create([
            'nama' => 'Kasir Test ' . uniqid(), 'email' => 'kasir-' . uniqid() . '@example.test',
            'password' => Hash::make('password'), 'is_active' => true,
        ]);
        $user->assignRole('kasir');
        return $user;
    }

    /**
     * Pastikan ada 1 akun super_admin dan paksa password-nya ke nilai yang
     * kita tahu -- dipakai apa adanya (bukan bikin akun baru) supaya test
     * ini tidak bergantung pada bug "cuma cek super_admin pertama" yang
     * jadi temuan Sedang terpisah (belum diperbaiki di sesi Tinggi ini).
     */
    private function pastikanSuperAdminDenganPassword(string $password): User
    {
        $superAdmin = User::role('super_admin')->where('is_active', true)->first();

        if (! $superAdmin) {
            $superAdmin = User::create([
                'nama' => 'Super Admin Test ' . uniqid(),
                'email' => 'superadmin-' . uniqid() . '@example.test',
                'password' => Hash::make($password), 'is_active' => true,
            ]);
            $superAdmin->assignRole('super_admin');
            return $superAdmin;
        }

        $superAdmin->update(['password' => Hash::make($password)]);
        return $superAdmin->fresh();
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

    // ── #1: BillingService::prosesSplitPayment() double-payment guard ──

    /** @test */
    public function split_payment_tidak_dobel_bayar_kalau_invoice_lunas_tepat_sebelum_konfirmasi(): void
    {
        $kasir     = $this->buatKasir();
        $kunjungan = $this->buatKunjunganSelesai();
        $invoice   = $this->buatInvoice($kunjungan, 100000);
        $sesiKas   = $this->bukaSesiKas($kasir);

        $this->actingAs($kasir);

        $component = Livewire::test(SplitPaymentForm::class, ['billing' => $invoice])
            ->set('splitItems', [[
                'metode' => 'tunai', 'label' => 'Tunai', 'jumlah' => 100000,
                'referensi' => null, 'nama_asuransi' => null, 'nomor_polis' => null,
                'jumlah_cover' => null, 'jumlah_pasien' => null,
            ]]);

        // "Proses lain" (mis. kasir lain via tab Tagihan Pasien) melunasi
        // invoice ini duluan, tepat sebelum kasir klik Konfirmasi di
        // split-payment. Komponen di atas sudah di-mount dgn state lama.
        Invoice::where('id', $invoice->id)->update(['status' => 'lunas', 'total_bayar' => 100000, 'sisa' => 0]);
        PembayaranSplit::create([
            'billing_id' => $invoice->id, 'sesi_kas_id' => $sesiKas->id,
            'user_id' => $kasir->id, 'metode' => 'tunai', 'jumlah' => 100000, 'tanggal_bayar' => now(),
        ]);

        $component->call('konfirmasi');

        $this->assertSame(1, PembayaranSplit::where('billing_id', $invoice->id)->count(),
            'Cuma boleh ada 1 PembayaranSplit (dari "proses lain" tsb) -- konfirmasi() tidak boleh menambah yang kedua.');
    }

    /** @test */
    public function split_payment_normal_tetap_berhasil_melunasi_invoice(): void
    {
        $kasir     = $this->buatKasir();
        $kunjungan = $this->buatKunjunganSelesai();
        $invoice   = $this->buatInvoice($kunjungan, 100000);
        $sesiKas   = $this->bukaSesiKas($kasir);

        $this->actingAs($kasir);

        Livewire::test(SplitPaymentForm::class, ['billing' => $invoice])
            ->set('splitItems', [[
                'metode' => 'tunai', 'label' => 'Tunai', 'jumlah' => 100000,
                'referensi' => null, 'nama_asuransi' => null, 'nomor_polis' => null,
                'jumlah_cover' => null, 'jumlah_pasien' => null,
            ]])
            ->call('konfirmasi');

        $this->assertSame('lunas', $invoice->fresh()->status);
        $this->assertSame(1, PembayaranSplit::where('billing_id', $invoice->id)->count());
    }

    // ── #2: BillingService::batalkanBilling() double-cancel guard ──────

    /** @test */
    public function batalkan_billing_ditolak_kalau_dibatalkan_dua_kali_dan_tidak_dobel_refund_deposit(): void
    {
        $kasir     = $this->buatKasir();
        $kunjungan = $this->buatKunjunganSelesai();
        $invoice   = $this->buatInvoice($kunjungan, 100000);
        $invoice->update(['status' => 'lunas', 'total_bayar' => 100000, 'sisa' => 0, 'total_deposit_dipakai' => 50000]);
        $this->bukaSesiKas($kasir);

        $pasien = Pasien::find($kunjungan->pasien_id);
        DepositPasien::create(['pasien_id' => $pasien->id, 'saldo' => 0, 'total_topup' => 50000, 'total_terpakai' => 50000]);

        $superAdmin = $this->pastikanSuperAdminDenganPassword('super-secret-test');

        $service = app(BillingService::class);

        // Panggilan pertama: berhasil normal.
        $service->batalkanBilling($invoice, 'super-secret-test', 'Alasan pembatalan pertama utk test ini', $kasir->id);

        // Panggilan kedua pakai OBJEK $invoice YANG SAMA (stale -- variabel
        // PHP ini tidak ikut ter-update oleh panggilan pertama krn service
        // bekerja di atas instance $billingLocked yang baru difetch, bukan
        // memutasi $invoice di tangan kita). Mensimulasikan race 2 klik
        // "Batalkan" bersamaan -- harus ditolak, BUKAN refund deposit lagi.
        try {
            $service->batalkanBilling($invoice, 'super-secret-test', 'Alasan pembatalan kedua utk test ini', $kasir->id);
            $this->fail('Panggilan kedua batalkanBilling() harusnya ditolak (invoice sudah dibatalkan).');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('sudah dibatalkan', $e->getMessage());
        }

        $this->assertSame(1, TransaksiDeposit::where('referensi_tipe', 'billing')
            ->where('referensi_id', $invoice->id)->where('tipe', 'refund')->count(),
            'Cuma boleh ada 1 refund deposit -- pembatalan kedua tidak boleh refund lagi.');
        $this->assertSame(50000.0, (float) DepositPasien::where('pasien_id', $pasien->id)->value('saldo'),
            'Saldo deposit cuma boleh nambah 1x (dari refund pertama), bukan 2x.');
    }

    // ── #3: SesiKasService::bukaKas() duplikat sesi guard ──────────────

    /** @test */
    public function buka_kas_normal_tetap_berhasil_dan_tetap_menolak_sesi_kedua_di_hari_yang_sama(): void
    {
        $kasir   = $this->buatKasir();
        $service = app(SesiKasService::class);

        $sesi = $service->bukaKas($kasir->id, 100000, 'Modal awal test');
        $this->assertSame('buka', $sesi->status);

        $this->expectException(\RuntimeException::class);
        $service->bukaKas($kasir->id, 50000, 'Percobaan buka kas kedua di hari yang sama');
    }

    // ── #4: PenagihanService::catatPembayaran() double-payment guard ───

    private function buatPiutangDanPenagihan(float $totalPiutang = 1000000): array
    {
        $kunjungan = $this->buatKunjunganSelesai();
        $invoice   = $this->buatInvoice($kunjungan, $totalPiutang);
        $asuransi  = Asuransi::create(['kode' => 'AS-' . uniqid(), 'nama' => 'Asuransi Test', 'tipe' => 'swasta', 'is_active' => true]);
        $pasien    = Pasien::find($kunjungan->pasien_id);

        $piutang = PiutangAsuransi::create([
            'nomor_piutang' => 'PIU-' . uniqid(), 'billing_id' => $invoice->id, 'asuransi_id' => $asuransi->id,
            'pasien_id' => $pasien->id, 'jumlah_piutang' => $totalPiutang, 'jumlah_dibayar' => 0,
            'sisa_piutang' => $totalPiutang, 'tanggal_piutang' => now(), 'status' => 'diajukan',
        ]);

        $penagihan = PenagihanAsuransi::create([
            'nomor_penagihan' => 'TAG-' . uniqid(), 'asuransi_id' => $asuransi->id,
            'dibuat_oleh' => $this->buatKasir()->id, 'tanggal_penagihan' => now(),
            'total_tagihan' => $totalPiutang, 'total_dibayar' => 0, 'status' => 'diajukan',
        ]);

        PenagihanItem::create([
            'penagihan_id' => $penagihan->id, 'piutang_asuransi_id' => $piutang->id,
            'jumlah_diajukan' => $totalPiutang,
        ]);

        $piutang->update(['penagihan_id' => $penagihan->id]);

        return [$penagihan, $piutang];
    }

    /** @test */
    public function catat_bayar_tidak_dobel_alokasi_kalau_penagihan_sudah_lunas_tepat_sebelum_dipanggil(): void
    {
        [$penagihan, $piutang] = $this->buatPiutangDanPenagihan(1000000);
        $userId = $this->buatKasir()->id;

        $service = app(PenagihanService::class);

        // "Proses lain" sudah melunasi penagihan ini duluan lewat instance
        // model terpisah -- $penagihan di tangan kita tetap stale
        // (total_dibayar=0, status='diajukan').
        PenagihanAsuransi::where('id', $penagihan->id)->update(['total_dibayar' => 1000000, 'status' => 'lunas']);
        PiutangAsuransi::where('id', $piutang->id)->update(['jumlah_dibayar' => 1000000, 'sisa_piutang' => 0, 'status' => 'lunas']);

        try {
            $service->catatPembayaran($penagihan, 1000000, 'transfer', now()->toDateString(), null, $userId);
            $this->fail('Harusnya ditolak krn penagihan sudah lunas duluan.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('sudah lunas', $e->getMessage());
        }

        $this->assertSame(0.0, (float) PiutangAsuransi::where('id', $piutang->id)->value('sisa_piutang'));
        $this->assertSame(1000000.0, (float) PenagihanAsuransi::where('id', $penagihan->id)->value('total_dibayar'),
            'total_dibayar tidak boleh nambah lagi jadi 2000000.');
    }

    /** @test */
    public function catat_bayar_normal_tetap_berhasil_mengalokasikan_ke_piutang(): void
    {
        [$penagihan, $piutang] = $this->buatPiutangDanPenagihan(1000000);
        $userId = $this->buatKasir()->id;

        app(PenagihanService::class)->catatPembayaran($penagihan, 1000000, 'transfer', now()->toDateString(), 'REF-001', $userId);

        $this->assertSame('lunas', $piutang->fresh()->status);
        $this->assertSame(0.0, (float) $piutang->fresh()->sisa_piutang);
        $this->assertSame('lunas', $penagihan->fresh()->status);
    }

    /** @test */
    public function catat_bayar_ditolak_kalau_jumlah_melebihi_sisa_tagihan_yang_sebenarnya(): void
    {
        [$penagihan, $piutang] = $this->buatPiutangDanPenagihan(1000000);
        $userId = $this->buatKasir()->id;

        // "Proses lain" sudah bayar sebagian duluan, sisa sebenarnya cuma 200000,
        // tapi $penagihan di tangan kita masih stale (total_dibayar=0).
        PenagihanAsuransi::where('id', $penagihan->id)->update(['total_dibayar' => 800000, 'status' => 'dibayar_sebagian']);
        PiutangAsuransi::where('id', $piutang->id)->update(['jumlah_dibayar' => 800000, 'sisa_piutang' => 200000, 'status' => 'dibayar_sebagian']);

        $service = app(PenagihanService::class);

        try {
            // Kasir mengira sisa tagihan masih 1.000.000 (data lama), coba bayar segitu.
            $service->catatPembayaran($penagihan, 1000000, 'transfer', now()->toDateString(), null, $userId);
            $this->fail('Harusnya ditolak krn melebihi sisa tagihan yang sebenarnya (200000).');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('melebihi sisa', $e->getMessage());
        }

        $this->assertSame(800000.0, (float) PenagihanAsuransi::where('id', $penagihan->id)->value('total_dibayar'));
    }
}

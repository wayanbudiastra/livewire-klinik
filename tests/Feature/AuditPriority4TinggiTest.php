<?php

namespace Tests\Feature;

use App\Livewire\Harga\ProposalHargaDetail;
use App\Livewire\Laporan\Kasir\CancelBillReport;
use App\Livewire\Laporan\Kasir\DepositReport;
use App\Models\Dokter;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Kunjungan;
use App\Models\Pasien;
use App\Models\Poli;
use App\Models\ProposalHarga;
use App\Models\TransaksiDeposit;
use App\Models\User;
use App\Services\Harga\ProposalHargaService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regresi Audit Priority 4 (Harga proposal maker-checker, PO/GR, Laporan/Export),
 * temuan Tinggi:
 *
 * 1. Kebocoran data Laporan Kasir -- laporan.kasir.view_all sebelumnya cuma
 *    diterapkan di tab "Transaksi Kasir" (TransaksiKasirReport), TIDAK di
 *    "Cancel Bill" (CancelBillReport) maupun "Deposit" (DepositReport),
 *    padahal ketiganya 1 halaman & 1 permission gate yang sama
 *    (laporan.kasir.view). Kasir tanpa laporan.kasir.view_all seharusnya
 *    cuma lihat data miliknya sendiri, tapi bisa lihat & export SEMUA
 *    pembatalan tagihan & SEMUA transaksi deposit se-klinik. Diperbaiki
 *    dengan menambah parameter $userId di KasirLaporanService::cancelBill()/
 *    deposit() (pola sama dgn transaksiKasir() yg sudah benar), diterapkan
 *    di kedua komponen Livewire-nya DAN class Export-nya (PDF & Excel).
 *
 * 2. ProposalHargaService::setujui() tidak ada guard "pembuat tidak boleh
 *    menyetujui proposal miliknya sendiri" -- diperbaiki dgn menambah cek
 *    dibuat_oleh !== $user->id.
 *
 * Pakai DatabaseTransactions -- bukan RefreshDatabase.
 */
class AuditPriority4TinggiTest extends TestCase
{
    use DatabaseTransactions;

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

    private function buatInvoiceDibatalkan(int $cancelledBy, float $total = 100000): Invoice
    {
        $kunjungan = $this->buatKunjunganSelesai();
        $invoice = Invoice::create([
            'kunjungan_id' => $kunjungan->id, 'nomor_invoice' => 'INV-' . uniqid(),
            'total_tagihan' => $total, 'total_bayar' => 0, 'sisa' => $total,
            'status' => 'dibatalkan', 'cancelled_by' => $cancelledBy,
            'cancel_reason' => 'Alasan test pembatalan', 'dibatalkan_pada' => now(),
        ]);
        InvoiceItem::create([
            'billing_id' => $invoice->id, 'jenis' => 'manual', 'nama_item' => 'Biaya Konsultasi',
            'qty' => 1, 'satuan' => 'layanan', 'harga_satuan' => $total, 'diskon_item' => 0, 'subtotal' => $total,
        ]);
        return $invoice;
    }

    // ── #1a: CancelBillReport ────────────────────────────────────────────

    /** @test */
    public function kasir_tanpa_view_all_cuma_lihat_cancel_bill_miliknya_sendiri(): void
    {
        $kasir1 = $this->buatUserDenganRole('kasir');
        $kasir2 = $this->buatUserDenganRole('kasir');

        $this->buatInvoiceDibatalkan($kasir1->id);
        $this->buatInvoiceDibatalkan($kasir2->id);

        $this->actingAs($kasir1);

        $component = Livewire::test(CancelBillReport::class)->call('generate');
        $this->assertSame(1, $component->get('hasil')['total_batal'],
            'Kasir tanpa laporan.kasir.view_all cuma boleh lihat pembatalan miliknya sendiri.');
    }

    /** @test */
    public function admin_dengan_view_all_lihat_semua_cancel_bill(): void
    {
        $kasir1 = $this->buatUserDenganRole('kasir');
        $kasir2 = $this->buatUserDenganRole('kasir');

        $this->buatInvoiceDibatalkan($kasir1->id);
        $this->buatInvoiceDibatalkan($kasir2->id);

        $admin = $this->buatUserDenganRole('admin');
        $this->actingAs($admin);

        $component = Livewire::test(CancelBillReport::class)->call('generate');
        $this->assertSame(2, $component->get('hasil')['total_batal'],
            'Role dgn laporan.kasir.view_all harus lihat semua kasir.');
    }

    // ── #1b: DepositReport ───────────────────────────────────────────────

    /** @test */
    public function kasir_tanpa_view_all_cuma_lihat_transaksi_deposit_miliknya_sendiri(): void
    {
        $kasir1 = $this->buatUserDenganRole('kasir');
        $kasir2 = $this->buatUserDenganRole('kasir');
        $pasien = $this->buatKunjunganSelesai()->pasien;

        TransaksiDeposit::create([
            'pasien_id' => $pasien->id, 'user_id' => $kasir1->id, 'nomor_transaksi' => 'DEP-TEST-' . uniqid(),
            'tipe' => 'topup', 'jumlah' => 100000, 'saldo_sebelum' => 0, 'saldo_sesudah' => 100000,
        ]);
        TransaksiDeposit::create([
            'pasien_id' => $pasien->id, 'user_id' => $kasir2->id, 'nomor_transaksi' => 'DEP-TEST-' . uniqid(),
            'tipe' => 'topup', 'jumlah' => 50000, 'saldo_sebelum' => 100000, 'saldo_sesudah' => 150000,
        ]);

        $this->actingAs($kasir1);

        $component = Livewire::test(DepositReport::class)->call('generate');
        $this->assertSame(1, $component->get('hasil')['jumlah_transaksi'],
            'Kasir tanpa laporan.kasir.view_all cuma boleh lihat transaksi deposit yg diproses sendiri.');
    }

    /** @test */
    public function admin_dengan_view_all_lihat_semua_transaksi_deposit(): void
    {
        $kasir1 = $this->buatUserDenganRole('kasir');
        $kasir2 = $this->buatUserDenganRole('kasir');
        $pasien = $this->buatKunjunganSelesai()->pasien;

        TransaksiDeposit::create([
            'pasien_id' => $pasien->id, 'user_id' => $kasir1->id, 'nomor_transaksi' => 'DEP-TEST-' . uniqid(),
            'tipe' => 'topup', 'jumlah' => 100000, 'saldo_sebelum' => 0, 'saldo_sesudah' => 100000,
        ]);
        TransaksiDeposit::create([
            'pasien_id' => $pasien->id, 'user_id' => $kasir2->id, 'nomor_transaksi' => 'DEP-TEST-' . uniqid(),
            'tipe' => 'topup', 'jumlah' => 50000, 'saldo_sebelum' => 100000, 'saldo_sesudah' => 150000,
        ]);

        $admin = $this->buatUserDenganRole('admin');
        $this->actingAs($admin);

        $component = Livewire::test(DepositReport::class)->call('generate');
        $this->assertSame(2, $component->get('hasil')['jumlah_transaksi']);
    }

    // ── #2: ProposalHargaService::setujui() self-approval guard ─────────

    /** @test */
    public function pembuat_proposal_tidak_bisa_menyetujui_proposalnya_sendiri(): void
    {
        $admin = $this->buatUserDenganRole('admin');

        $proposal = ProposalHarga::create([
            'judul' => 'Kenaikan Test', 'tahun' => now()->addYear()->year,
            'tanggal_efektif' => now()->addYear()->startOfYear(), 'cakupan' => 'barang',
            'konfigurasi_kenaikan' => ['obat' => 10], 'status' => 'menunggu_persetujuan',
            'dibuat_oleh' => $admin->id,
        ]);

        $service = app(ProposalHargaService::class);

        try {
            $service->setujui($proposal, $admin);
            $this->fail('Pembuat proposal harusnya ditolak saat menyetujui proposal sendiri.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('tidak boleh menyetujui', $e->getMessage());
        }

        $this->assertSame('menunggu_persetujuan', $proposal->fresh()->status);
    }

    /** @test */
    public function admin_lain_tetap_bisa_menyetujui_proposal_yang_dibuat_admin_lain(): void
    {
        $pembuat  = $this->buatUserDenganRole('admin');
        $penyetuju = $this->buatUserDenganRole('admin');

        $proposal = ProposalHarga::create([
            'judul' => 'Kenaikan Test 2', 'tahun' => now()->addYear()->year,
            'tanggal_efektif' => now()->addYear()->startOfYear(), 'cakupan' => 'barang',
            'konfigurasi_kenaikan' => ['obat' => 10], 'status' => 'menunggu_persetujuan',
            'dibuat_oleh' => $pembuat->id,
        ]);

        app(ProposalHargaService::class)->setujui($proposal, $penyetuju);

        $this->assertSame('disetujui', $proposal->fresh()->status);
        $this->assertSame($penyetuju->id, $proposal->fresh()->disetujui_oleh);
    }
}

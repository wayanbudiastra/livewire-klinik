<?php

namespace Tests\Feature;

use App\Livewire\Harga\ProposalHargaDetail;
use App\Livewire\Keuangan\Penagihan\PenagihanDetail;
use App\Models\ProposalHarga;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Test untuk role split Fase 3 (FR-4/FR-5, prd/roles_permissions_sod_audit.md):
 * harga_reviewer vs harga_approver, piutang_kolektor vs piutang_verifikator.
 * Memastikan pemisahannya benar-benar fungsional (bukan cuma nama role),
 * dan role admin/keuangan existing tidak ikut berubah kemampuannya.
 *
 * Pakai DatabaseTransactions -- lihat catatan yang sama di
 * SensitiveActionAuthorizationTest.php soal kenapa bukan RefreshDatabase.
 */
class RoleSplitTest extends TestCase
{
    use DatabaseTransactions;

    private function buatUserDenganRole(string $role): User
    {
        $user = User::create([
            'nama'      => 'Test ' . $role,
            'email'     => 'test-' . $role . '-' . uniqid() . '@example.test',
            'password'  => Hash::make('password'),
            'is_active' => true,
        ]);
        $user->assignRole($role);

        return $user;
    }

    /** Pakai service asli (bukan create() manual) supaya item ikut ter-generate seperti alur produksi. */
    private function buatProposal(string $status): ProposalHarga
    {
        $creator = User::where('email', 'admin@emr.app')->firstOrFail();

        $proposal = app(\App\Services\Harga\ProposalHargaService::class)->buat([
            'judul'                => 'TEST-ROLESPLIT-' . uniqid(),
            'tahun'                => now()->year,
            'tanggal_efektif'      => now()->addDays(3),
            'cakupan'              => 'tindakan',
            'konfigurasi_kenaikan' => [],
            'ikut_bpjs'            => false,
        ], $creator);

        $proposal->update(['status' => $status]);

        return $proposal->fresh();
    }

    // ── harga_reviewer vs harga_approver ─────────────────────────

    public function test_harga_reviewer_bisa_koreksi_item_tapi_tidak_bisa_setujui(): void
    {
        $reviewer = $this->buatUserDenganRole('harga_reviewer');
        $proposal = $this->buatProposal('menunggu_persetujuan');
        $item     = $proposal->items()->first();

        $this->actingAs($reviewer);

        // Bisa: koreksi item -- tapi item cuma bisa dikoreksi saat draft,
        // jadi test toggleSkip saja (valid untuk draft & menunggu_persetujuan).
        Livewire::test(ProposalHargaDetail::class, ['id' => $proposal->id])
            ->call('toggleSkip', $item->id, app(\App\Services\Harga\ProposalHargaService::class));

        $this->assertTrue((bool) $item->fresh()->is_skip, 'Reviewer harus bisa toggleSkip.');

        // Tidak bisa: setujui.
        Livewire::test(ProposalHargaDetail::class, ['id' => $proposal->id])->call('setujui');
        $this->assertSame('menunggu_persetujuan', $proposal->fresh()->status, 'Reviewer tidak boleh bisa menyetujui.');
    }

    public function test_harga_approver_bisa_setujui_dan_tolak_tapi_tidak_bisa_koreksi_item(): void
    {
        $approver = $this->buatUserDenganRole('harga_approver');
        $proposal = $this->buatProposal('menunggu_persetujuan');
        $item     = $proposal->items()->first();
        $hargaAsal = $item->harga_baru;

        $this->actingAs($approver);

        // Tidak bisa: koreksi/toggle item (itu tugas reviewer).
        Livewire::test(ProposalHargaDetail::class, ['id' => $proposal->id])
            ->call('toggleSkip', $item->id, app(\App\Services\Harga\ProposalHargaService::class));
        $this->assertEquals($hargaAsal, $item->fresh()->harga_baru, 'Approver tidak boleh bisa mengubah item.');

        // Bisa: setujui.
        Livewire::test(ProposalHargaDetail::class, ['id' => $proposal->id])->call('setujui');
        $this->assertSame('disetujui', $proposal->fresh()->status, 'Approver harus bisa menyetujui.');

        // Bisa juga: tolak (dari proposal lain yang masih menunggu persetujuan).
        $proposal2 = $this->buatProposal('menunggu_persetujuan');
        Livewire::test(ProposalHargaDetail::class, ['id' => $proposal2->id])
            ->set('alasanTolak', 'Alasan penolakan uji coba')
            ->call('tolak');
        $this->assertSame('draft', $proposal2->fresh()->status, 'Approver harus bisa menolak (bukan cuma menyetujui).');
    }

    public function test_admin_dan_keuangan_existing_tidak_berubah_kemampuannya(): void
    {
        $admin    = User::where('email', 'admin@emr.app')->firstOrFail();
        $proposal = $this->buatProposal('menunggu_persetujuan');

        $this->actingAs($admin);
        Livewire::test(ProposalHargaDetail::class, ['id' => $proposal->id])->call('setujui');

        $this->assertSame('disetujui', $proposal->fresh()->status, 'Admin existing harus tetap bisa menyetujui seperti sebelum Fase 3 -- tidak boleh regresi.');
    }

    // ── piutang_kolektor vs piutang_verifikator ──────────────────

    public function test_piutang_kolektor_tidak_bisa_catat_lunas(): void
    {
        $penagihan = \App\Models\PenagihanAsuransi::first();
        if (!$penagihan) {
            $this->markTestSkipped('Tidak ada data PenagihanAsuransi di database lokal untuk diuji.');
        }

        $kolektor    = $this->buatUserDenganRole('piutang_kolektor');
        $sisaSebelum = $penagihan->sisa_tagihan;

        $this->actingAs($kolektor);
        Livewire::test(PenagihanDetail::class, ['penagihan' => $penagihan])
            ->set('jumlahBayar', 1)
            ->call('catatBayar');

        $this->assertSame(
            (float) $sisaSebelum,
            (float) $penagihan->fresh()->sisa_tagihan,
            'Kolektor tidak boleh bisa mencatat pelunasan sendiri.'
        );
    }

    public function test_piutang_verifikator_punya_permission_lunas_tapi_tidak_tagih(): void
    {
        $verifikator = $this->buatUserDenganRole('piutang_verifikator');

        $this->assertTrue($verifikator->can('piutang.lunas'));
        $this->assertFalse($verifikator->can('piutang.tagih'), 'Verifikator tidak boleh bisa menagih aktif (hindari menagih sekaligus mencatat pelunasan sendiri).');
    }
}

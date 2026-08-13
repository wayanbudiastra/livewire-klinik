<?php

namespace Tests\Feature;

use App\Livewire\Akuntansi\JurnalPendingTable;
use App\Livewire\Harga\ProposalHargaDetail;
use App\Livewire\Keuangan\Penagihan\PenagihanDetail;
use App\Models\ProposalHarga;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * Regression test untuk temuan F1-F3 di prd/roles_permissions_sod_audit.md:
 * beberapa aksi finansial sensitif sebelumnya hanya dijaga di Blade (@can),
 * tanpa authorize() di backend method Livewire-nya. Test ini memastikan
 * gap itu sudah tertutup dan tidak regresi di kemudian hari.
 *
 * Verifikasi lewat EFEK SAMPING (state tidak berubah), bukan
 * expectException — Livewire::test() sengaja menangkap
 * AuthorizationException saat ->call() dan mengubahnya jadi response
 * (lihat RequestBroker::temporarilyDisableExceptionHandlingAndMiddleware,
 * yang mengecualikan AuthorizationException dari "throw mentah").
 * Verifikasi lewat state jauh lebih tahan terhadap detail implementasi
 * semacam itu.
 *
 * Pakai DatabaseTransactions (bukan RefreshDatabase) supaya TIDAK
 * migrate:fresh database lokal yang dipakai bersama untuk development
 * -- semua perubahan di test ini otomatis rollback setelah selesai.
 */
class SensitiveActionAuthorizationTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;
    private User $dokter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin  = User::where('email', 'admin@emr.app')->firstOrFail();
        $this->dokter = User::where('email', 'dokter@emr.app')->firstOrFail();

        // Sanity check: pastikan asumsi permission demo user masih benar
        // sebelum menguji enforcement-nya (kalau seeder berubah, test ini
        // harus gagal di sini dulu, bukan salah paham di assertion bawah).
        $this->assertTrue($this->admin->can('harga.setujui'));
        $this->assertFalse($this->dokter->can('harga.setujui'));
        $this->assertFalse($this->dokter->can('harga.proposal'));
        $this->assertTrue($this->admin->can('akuntansi.jurnal.posting'));
        $this->assertFalse($this->dokter->can('akuntansi.jurnal.posting'));
        $this->assertTrue($this->admin->can('piutang.lunas'));
        $this->assertFalse($this->dokter->can('piutang.lunas'));
    }

    private function buatProposal(string $status): ProposalHarga
    {
        return ProposalHarga::create([
            'judul'                => 'TEST-AUTHZ-' . uniqid(),
            'tahun'                => now()->year,
            'tanggal_efektif'      => now()->addDays(3),
            'cakupan'              => 'semua',
            'konfigurasi_kenaikan' => ['default_persen' => 5],
            'ikut_bpjs'            => false,
            'status'               => $status,
            'dibuat_oleh'          => $this->admin->id,
        ]);
    }

    // ── FR-1: Update Harga ──────────────────────────────────────

    public function test_dokter_tanpa_permission_tidak_bisa_submit_review_proposal_harga(): void
    {
        $proposal = $this->buatProposal('draft');

        $this->actingAs($this->dokter);
        Livewire::test(ProposalHargaDetail::class, ['id' => $proposal->id])->call('submitReview');

        $this->assertSame('draft', $proposal->fresh()->status, 'Status proposal tidak boleh berubah kalau dokter tidak berwenang.');
    }

    public function test_dokter_tanpa_permission_tidak_bisa_setujui_proposal_harga(): void
    {
        $proposal = $this->buatProposal('menunggu_persetujuan');

        $this->actingAs($this->dokter);
        Livewire::test(ProposalHargaDetail::class, ['id' => $proposal->id])->call('setujui');

        $this->assertSame('menunggu_persetujuan', $proposal->fresh()->status, 'Proposal tidak boleh ikut disetujui kalau dokter tidak berwenang.');
    }

    public function test_admin_dengan_permission_bisa_setujui_proposal_harga(): void
    {
        $proposal = $this->buatProposal('menunggu_persetujuan');

        $this->actingAs($this->admin);
        Livewire::test(ProposalHargaDetail::class, ['id' => $proposal->id])->call('setujui');

        $this->assertSame('disetujui', $proposal->fresh()->status, 'Admin yang berwenang harus tetap bisa menyetujui (tidak boleh regresi).');

        // FR-6: aksi persetujuan harus tercatat di activity_log dengan causer yang benar.
        $log = Activity::where('log_name', 'harga_proposal')
            ->where('subject_id', $proposal->id)
            ->where('causer_id', $this->admin->id)
            ->latest()
            ->first();

        $this->assertNotNull($log, 'Aksi setujui() harus tercatat di activity_log.');
        $this->assertStringContainsString('disetujui', $log->description);
    }

    public function test_terapkan_proposal_harga_tercatat_di_activity_log(): void
    {
        $proposal = $this->buatProposal('disetujui');
        $proposal->update(['tanggal_efektif' => now()->subDay()]);

        $this->actingAs($this->admin);
        Livewire::test(ProposalHargaDetail::class, ['id' => $proposal->id])->call('terapkan');

        $this->assertSame('efektif', $proposal->fresh()->status);

        $log = Activity::where('log_name', 'harga_proposal')
            ->where('subject_id', $proposal->id)
            ->where('causer_id', $this->admin->id)
            ->where('description', 'like', '%diterapkan%')
            ->first();

        $this->assertNotNull($log, 'Aksi terapkan() harus tercatat di activity_log.');
        $this->assertArrayHasKey('jumlah_item_diterapkan', $log->properties->toArray());
    }

    public function test_dokter_tanpa_permission_tidak_bisa_terapkan_proposal_harga(): void
    {
        $proposal = $this->buatProposal('disetujui');
        $proposal->update(['tanggal_efektif' => now()->subDay()]); // supaya lolos syarat tanggal efektif

        $this->actingAs($this->dokter);
        Livewire::test(ProposalHargaDetail::class, ['id' => $proposal->id])->call('terapkan');

        $this->assertSame('disetujui', $proposal->fresh()->status, 'Proposal tidak boleh ikut diterapkan kalau dokter tidak berwenang.');
    }

    // ── FR-2: Posting Jurnal ────────────────────────────────────

    public function test_dokter_tanpa_permission_tidak_bisa_posting_jurnal_pending(): void
    {
        $jurnal = \App\Models\Akuntansi\JurnalPending::first();

        if (!$jurnal) {
            $this->markTestSkipped('Tidak ada data JurnalPending di database lokal untuk diuji.');
        }

        $statusSebelum = $jurnal->status;

        $this->actingAs($this->dokter);
        Livewire::test(JurnalPendingTable::class)
            ->set('selected', [(string) $jurnal->id])
            ->call('postingTerpilih');

        $this->assertSame($statusSebelum, $jurnal->fresh()->status, 'Jurnal tidak boleh ikut terposting kalau dokter tidak berwenang.');
    }

    // ── FR-3: Pelunasan Piutang ─────────────────────────────────

    public function test_dokter_tanpa_permission_tidak_bisa_catat_lunas_piutang(): void
    {
        $penagihan = \App\Models\PenagihanAsuransi::first();

        if (!$penagihan) {
            $this->markTestSkipped('Tidak ada data PenagihanAsuransi di database lokal untuk diuji.');
        }

        $sisaSebelum = $penagihan->sisa_tagihan;

        $this->actingAs($this->dokter);
        Livewire::test(PenagihanDetail::class, ['penagihan' => $penagihan])
            ->set('jumlahBayar', 1)
            ->call('catatBayar');

        $this->assertSame(
            (float) $sisaSebelum,
            (float) $penagihan->fresh()->sisa_tagihan,
            'Sisa tagihan tidak boleh berubah kalau dokter tidak berwenang mencatat pelunasan.'
        );
    }
}

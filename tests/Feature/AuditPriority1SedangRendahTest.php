<?php

namespace Tests\Feature;

use App\Livewire\Akuntansi\ChartOfAccountManager;
use App\Livewire\Akuntansi\JurnalManualForm;
use App\Livewire\Akuntansi\PeriodeAkuntansiTable;
use App\Livewire\Harga\ProposalHargaForm;
use App\Models\Akuntansi\ChartOfAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Regresi Audit Priority 1 (sapuan otorisasi lintas modul), temuan Sedang & Rendah:
 *
 * [Sedang] Komponen Akuntansi (JurnalManualForm, ChartOfAccountManager,
 * PeriodeAkuntansiTable) & Harga (ProposalHargaForm) sudah digate benar di
 * level route (permission:akuntansi.* atau harga.*), tapi method state-changing
 * di dalam komponennya sendiri (simpan(), tutup(), bukaKembali(),
 * toggleAktif()) tidak punya authorize() sendiri. Ditambahkan sbg lapis
 * kedua (defense in depth) -- aman sekarang krn akses awal ke halaman sudah
 * tersaring, tapi melindungi kalau nanti komponen ini di-embed di tempat
 * lain tanpa sadar.
 *
 * [Rendah] Route /inventory/po/create & /inventory/gr/create tidak punya
 * middleware permission:obat.create eksplisit (beda dari retur-gr/create,
 * bhp/create, opname/create yang sudah pakai obat.edit) -- tidak berdampak
 * nyata sekarang krn satu2nya role dgn obat.view (apoteker) juga otomatis
 * punya obat.create, tapi celah aktif kalau nanti ada role baru "gudang"
 * yang cuma obat.view. Dites dgn role ad hoc utk membuktikan middleware-nya
 * benar2 bekerja.
 *
 * Pakai DatabaseTransactions -- bukan RefreshDatabase.
 */
class AuditPriority1SedangRendahTest extends TestCase
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

    // ── JurnalManualForm::mount() -- butuh akuntansi.jurnal_manual.create ──

    /** @test */
    public function role_tanpa_permission_jurnal_manual_create_ditolak_mount(): void
    {
        // 'kasir' tidak punya permission akuntansi apa pun.
        $kasir = $this->buatUserDenganRole('kasir');
        $this->actingAs($kasir);

        Livewire::test(JurnalManualForm::class)->assertForbidden();
    }

    /** @test */
    public function keuangan_tetap_bisa_mount_jurnal_manual_form(): void
    {
        $keuangan = $this->buatUserDenganRole('keuangan');
        $this->actingAs($keuangan);

        Livewire::test(JurnalManualForm::class)->assertOk();
    }

    // ── ChartOfAccountManager::simpan()/toggleAktif() -- butuh akuntansi.coa.manage ──

    /** @test */
    public function role_tanpa_coa_manage_ditolak_simpan_dan_toggle_akun(): void
    {
        // 'keuangan' punya beberapa permission akuntansi lain tapi bukan coa.manage.
        $keuangan = $this->buatUserDenganRole('keuangan');
        $this->actingAs($keuangan);

        Livewire::test(ChartOfAccountManager::class)
            ->set('kode', '1-9999')
            ->set('nama', 'Akun Test')
            ->set('golongan', 'biaya')
            ->set('tipe_normal', 'debit')
            ->call('simpan')
            ->assertForbidden();

        $akun = ChartOfAccount::create([
            'kode' => '1-8888', 'nama' => 'Akun Toggle Test', 'golongan' => 'biaya',
            'tipe_normal' => 'debit', 'is_aktif' => true,
        ]);

        Livewire::test(ChartOfAccountManager::class)
            ->call('toggleAktif', $akun->id)
            ->assertForbidden();

        $this->assertTrue($akun->fresh()->is_aktif, 'Status akun tidak boleh berubah kalau ditolak otorisasi.');
    }

    /** @test */
    public function akuntan_tetap_bisa_simpan_akun_baru(): void
    {
        $akuntan = $this->buatUserDenganRole('akuntan');
        $this->actingAs($akuntan);

        Livewire::test(ChartOfAccountManager::class)
            ->set('kode', '1-7777')
            ->set('nama', 'Akun Test Akuntan')
            ->set('golongan', 'biaya')
            ->set('tipe_normal', 'debit')
            ->call('simpan')
            ->assertOk();

        $this->assertDatabaseHas('chart_of_accounts', ['kode' => '1-7777']);
    }

    // ── PeriodeAkuntansiTable::tutup()/bukaKembali() -- butuh akuntansi.periode.tutup ──

    /** @test */
    public function role_tanpa_periode_tutup_ditolak_tutup_dan_buka_kembali_periode(): void
    {
        $keuangan = $this->buatUserDenganRole('keuangan');
        $this->actingAs($keuangan);

        Livewire::test(PeriodeAkuntansiTable::class)
            ->call('tutup', now()->year, now()->month)
            ->assertForbidden();

        Livewire::test(PeriodeAkuntansiTable::class)
            ->call('bukaKembali')
            ->assertForbidden();
    }

    /** @test */
    public function akuntan_tidak_diblokir_authorize_saat_tutup_periode(): void
    {
        $akuntan = $this->buatUserDenganRole('akuntan');
        $this->actingAs($akuntan);

        // Cukup pastikan tidak lagi 403 (authorize() lolos) -- hasil bisnis
        // (berhasil/gagal tutup krn ada jurnal pending dsb) di luar cakupan
        // fix otorisasi ini.
        Livewire::test(PeriodeAkuntansiTable::class)
            ->call('tutup', now()->year, now()->month)
            ->assertOk();
    }

    // ── ProposalHargaForm::mount() -- butuh harga.proposal ──

    /** @test */
    public function harga_reviewer_ditolak_mount_proposal_harga_form(): void
    {
        // harga_reviewer sengaja TIDAK dikasih harga.proposal (SoD: reviewer
        // cuma boleh koreksi angka, bukan bikin proposal baru).
        $reviewer = $this->buatUserDenganRole('harga_reviewer');
        $this->actingAs($reviewer);

        Livewire::test(ProposalHargaForm::class)->assertForbidden();
    }

    /** @test */
    public function admin_tetap_bisa_mount_proposal_harga_form(): void
    {
        $admin = $this->buatUserDenganRole('admin');
        $this->actingAs($admin);

        Livewire::test(ProposalHargaForm::class)->assertOk();
    }

    // ── Rendah: route /inventory/po/create & /inventory/gr/create ──

    /** @test */
    public function role_dengan_obat_view_saja_tanpa_obat_create_ditolak_403_di_po_dan_gr_create(): void
    {
        // Tidak ada role default yang cuma punya obat.view tanpa obat.create
        // (satu2nya role dgn obat.view yaitu apoteker juga otomatis punya
        // obat.create) -- dibuat role ad hoc utk membuktikan middleware-nya
        // benar2 bekerja begitu ada role seperti ini di masa depan.
        $role = Role::firstOrCreate(['name' => 'gudang_test_only_view']);
        $role->syncPermissions(['obat.view']);

        $user = $this->buatUserDenganRole('gudang_test_only_view');
        $this->actingAs($user);

        $this->get(route('inventory.po.create'))->assertForbidden();
        $this->get(route('inventory.gr.create'))->assertForbidden();
    }

    /** @test */
    public function apoteker_tetap_bisa_akses_po_dan_gr_create(): void
    {
        $apoteker = $this->buatUserDenganRole('apoteker');
        $this->actingAs($apoteker);

        $this->get(route('inventory.po.create'))->assertOk();
        $this->get(route('inventory.gr.create'))->assertOk();
    }
}

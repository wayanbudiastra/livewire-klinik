<?php

namespace Tests\Feature;

use App\Livewire\Pengaturan\User\UserForm;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * Test untuk fitur "Hak Akses Tambahan" (direct permission per-user, di luar
 * role standarnya) -- dibangun untuk kondisi lapangan di mana satu staf
 * merangkap tugas lebih dari role standarnya (mis. perawat yang juga jadi
 * kasir/FO/farmasi di klinik kecil). Lihat prd/roles_permissions_sod_audit.md.
 *
 * Pakai DatabaseTransactions (bukan RefreshDatabase) -- lihat catatan yang
 * sama di SensitiveActionAuthorizationTest.php.
 */
class ExtraPermissionTest extends TestCase
{
    use DatabaseTransactions;

    private User $superAdmin;
    private User $admin;
    private User $perawat;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::where('email', 'superadmin@emr.app')->firstOrFail();
        $this->admin      = User::where('email', 'admin@emr.app')->firstOrFail();
        $this->perawat    = User::where('email', 'perawat@emr.app')->firstOrFail();

        $this->assertFalse($this->perawat->can('billing.create'), 'Prasyarat: perawat belum boleh billing.create dari role-nya.');
    }

    public function test_super_admin_bisa_memberi_hak_akses_tambahan(): void
    {
        app(UserService::class)->syncExtraPermissions(
            $this->perawat->id,
            ['billing.create', 'pembayaran.create'],
            $this->superAdmin
        );

        $this->perawat->unsetRelation('permissions');
        $this->assertTrue($this->perawat->fresh()->can('billing.create'), 'Setelah diberi hak akses tambahan, harus bisa billing.create.');
        $this->assertTrue($this->perawat->fresh()->can('pembayaran.create'));

        // Role standarnya (dan permission lain dari role itu) tidak boleh berubah.
        $this->assertTrue($this->perawat->fresh()->hasRole('perawat'));
        $this->assertTrue($this->perawat->fresh()->can('asesmen.view'), 'Permission dari role asli tidak boleh hilang.');
    }

    public function test_hak_akses_tambahan_tercatat_di_activity_log(): void
    {
        app(UserService::class)->syncExtraPermissions(
            $this->perawat->id,
            ['billing.create'],
            $this->superAdmin
        );

        $log = Activity::where('log_name', 'user')
            ->where('subject_id', $this->perawat->id)
            ->where('causer_id', $this->superAdmin->id)
            ->where('description', 'like', '%tambahan%')
            ->latest()
            ->first();

        $this->assertNotNull($log, 'Perubahan hak akses tambahan harus tercatat di activity_log.');
        $this->assertSame(['billing.create'], $log->properties->toArray()['sesudah'] ?? null);
    }

    public function test_admin_biasa_tidak_bisa_memberi_hak_akses_tambahan_lewat_form(): void
    {
        // Simulasi: admin (bukan super_admin) membuka form edit user & entah
        // bagaimana caranya mengirim payload extraPermissions -- backend
        // HARUS mengabaikannya, bukan cuma bergantung field-nya disembunyikan
        // di Blade (pola yang sama seperti F1-F3).
        $this->actingAs($this->admin);

        Livewire::test(UserForm::class)
            ->call('openEdit', $this->perawat->id)
            ->set('extraPermissions', ['billing.create'])
            ->set('nama', $this->perawat->nama) // field wajib supaya validasi lolos
            ->set('email', $this->perawat->email)
            ->set('role', 'perawat')
            ->call('save');

        $this->assertFalse(
            $this->perawat->fresh()->can('billing.create'),
            'Admin biasa (bukan super_admin) tidak boleh bisa memberi hak akses tambahan lewat form ini.'
        );
    }

    public function test_daftar_permission_dikelompokkan_per_modul_untuk_checklist(): void
    {
        $this->actingAs($this->superAdmin);

        $component = Livewire::test(UserForm::class)->call('openEdit', $this->perawat->id);

        $groups = $component->instance()->permissionGroups;

        $this->assertArrayHasKey('billing', $groups);
        $this->assertContains('billing.create', $groups['billing']);
    }
}

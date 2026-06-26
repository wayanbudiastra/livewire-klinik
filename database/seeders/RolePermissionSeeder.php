<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'pasien.view', 'pasien.create', 'pasien.edit', 'pasien.delete',
            'kunjungan.view', 'kunjungan.create', 'kunjungan.edit', 'kunjungan.delete',
            'asesmen.view', 'asesmen.create', 'asesmen.edit',
            'soap.view', 'soap.create', 'soap.edit',
            'resep.view', 'resep.create', 'resep.edit',
            'obat.view', 'obat.create', 'obat.edit', 'obat.delete',
            'tindakan.view', 'tindakan.create',
            'billing.view', 'billing.create', 'billing.edit',
            'pembayaran.view', 'pembayaran.create',
            'laporan.view', 'laporan.keuangan', 'laporan.farmasi',
            'rekammedis.view', 'rekammedis.create', 'rekammedis.edit',
            'pengaturan.view', 'pengaturan.edit',
            'user.view', 'user.create', 'user.edit', 'user.delete',

            // Master Data Klinis (v2)
            'masterdata.view', 'masterdata.create', 'masterdata.edit', 'masterdata.delete',
            'peralatan.pakai',
            'penunjang.create', 'penunjang.view',

            // Laporan (v1)
            'laporan.registrasi.view',
            'laporan.pemeriksaan.view',
            'laporan.kasir.view',
            'laporan.kasir.view_all',
            'laporan.pharmacy.view',
            'laporan.export',

            // Asuransi & Piutang
            'asuransi.config_bpjs',
            'asuransi.master.view', 'asuransi.master.manage',
            'asuransi.pasien.manage',
            'piutang.view', 'piutang.tagih', 'piutang.lunas',

            // Akuntansi
            'akuntansi.coa.manage',
            'akuntansi.jurnal.posting',
            'akuntansi.jurnal.view',
            'akuntansi.laporan.view',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm]);
        }

        $roles = [
            'pasien' => [
                'kunjungan.view', 'rekammedis.view', 'billing.view',
            ],
            'apoteker' => [
                'resep.view', 'resep.edit',
                'obat.view', 'obat.create', 'obat.edit',
                'laporan.farmasi',
                'laporan.pharmacy.view', 'laporan.export',
            ],
            'rekam_medis' => [
                'pasien.view', 'pasien.create', 'pasien.edit',
                'rekammedis.view', 'rekammedis.create', 'rekammedis.edit',
                'laporan.view',
                'laporan.registrasi.view', 'laporan.pemeriksaan.view', 'laporan.export',
            ],
            'dokter' => [
                'pasien.view',
                'kunjungan.view', 'kunjungan.edit',
                'soap.view', 'soap.create', 'soap.edit',
                'resep.view', 'resep.create', 'resep.edit',
                'tindakan.view', 'tindakan.create',
                'laporan.view',
                'laporan.pemeriksaan.view',
                'masterdata.view',
                'penunjang.create', 'penunjang.view',
                'peralatan.pakai',
            ],
            'perawat' => [
                'pasien.view', 'pasien.create', 'pasien.edit',
                'kunjungan.view', 'kunjungan.create', 'kunjungan.edit',
                'asesmen.view', 'asesmen.create', 'asesmen.edit',
                'tindakan.view', 'tindakan.create',
                'masterdata.view',
                'peralatan.pakai',
            ],
            'kasir' => [
                'pasien.view', 'kunjungan.view',
                'billing.view', 'billing.create', 'billing.edit',
                'pembayaran.view', 'pembayaran.create',
                'laporan.keuangan',
                'laporan.kasir.view',
                'masterdata.view',
                'asuransi.master.view', 'asuransi.pasien.manage',
            ],
            'front_office' => [
                'pasien.view', 'pasien.create', 'pasien.edit',
                'kunjungan.view', 'kunjungan.create',
                'asuransi.master.view', 'asuransi.pasien.manage',
            ],
            'keuangan' => [
                'pasien.view', 'kunjungan.view',
                'billing.view',
                'laporan.view', 'laporan.keuangan',
                'laporan.kasir.view', 'laporan.export',
                'asuransi.master.view',
                'piutang.view', 'piutang.tagih', 'piutang.lunas',
                'akuntansi.jurnal.view', 'akuntansi.laporan.view',
            ],
            'akuntan' => [
                'laporan.view', 'laporan.keuangan', 'laporan.export',
                'piutang.view',
                'akuntansi.coa.manage',
                'akuntansi.jurnal.posting', 'akuntansi.jurnal.view',
                'akuntansi.laporan.view',
            ],
            'admin' => [
                'pasien.view', 'pasien.create', 'pasien.edit', 'pasien.delete',
                'kunjungan.view', 'kunjungan.create', 'kunjungan.edit', 'kunjungan.delete',
                'user.view', 'user.create', 'user.edit',
                'laporan.view', 'laporan.keuangan',
                'laporan.registrasi.view', 'laporan.pemeriksaan.view',
                'laporan.kasir.view', 'laporan.kasir.view_all',
                'laporan.pharmacy.view', 'laporan.export',
                'pengaturan.view', 'pengaturan.edit',
                'masterdata.view', 'masterdata.create', 'masterdata.edit',
                'asuransi.config_bpjs', 'asuransi.master.view', 'asuransi.master.manage',
                'asuransi.pasien.manage',
                'piutang.view', 'piutang.tagih', 'piutang.lunas',
                'akuntansi.coa.manage', 'akuntansi.jurnal.posting',
                'akuntansi.jurnal.view', 'akuntansi.laporan.view',
            ],
            'super_admin' => [],
        ];

        foreach ($roles as $roleName => $rolePerms) {
            $role = Role::firstOrCreate(['name' => $roleName]);
            $role->syncPermissions($rolePerms);
        }
    }
}

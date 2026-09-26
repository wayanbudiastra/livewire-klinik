<?php

namespace App\Services\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * Verifikasi password SuperAdmin utk aksi sensitif (batalkan billing, buka
 * kas kembali, buka kembali periode akuntansi, dst).
 *
 * Audit Priority 2 (Sedang): sebelumnya method ini diduplikat identik di 3
 * tempat (SesiKasService, BillingService, PeriodeAkuntansiService) dan
 * ketiganya punya bug yang sama -- cuma mengambil SATU akun super_admin
 * pertama (User::role('super_admin')->first()) lalu cek password terhadap
 * akun itu SAJA. Kalau klinik punya lebih dari 1 akun super_admin, password
 * akun super_admin ke-2 dst akan SELALU ditolak walau valid. Dikonsolidasi
 * ke satu trait supaya perbaikannya tidak perlu diduplikasi 3x lagi, dan
 * sekarang mengecek SEMUA akun super_admin aktif.
 */
trait VerifiesSuperAdminPassword
{
    public function verifySuperAdminPassword(string $password): User
    {
        $superAdmins = User::role('super_admin')->where('is_active', true)->get();

        foreach ($superAdmins as $superAdmin) {
            if (Hash::check($password, $superAdmin->password)) {
                return $superAdmin;
            }
        }

        throw new \RuntimeException('Password SuperAdmin tidak valid.');
    }
}

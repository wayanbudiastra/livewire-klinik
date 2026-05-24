<?php

namespace App\Services\Kasir;

use App\Models\AuditKasir;

class AuditKasirService
{
    public static function log(
        string  $aksi,
        int     $userId,
        ?string $referensiTipe = null,
        ?int    $referensiId   = null,
        array   $detail        = [],
        ?int    $superAdminId  = null
    ): void {
        AuditKasir::create([
            'user_id'        => $userId,
            'superadmin_id'  => $superAdminId,
            'aksi'           => $aksi,
            'referensi_tipe' => $referensiTipe,
            'referensi_id'   => $referensiId,
            'detail'         => $detail,
            'ip_address'     => request()->ip(),
        ]);
    }
}

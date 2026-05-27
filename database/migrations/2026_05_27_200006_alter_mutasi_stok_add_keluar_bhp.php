<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE mutasi_stok MODIFY COLUMN tipe ENUM(
            'masuk_pembelian',
            'keluar_resep',
            'keluar_tindakan',
            'keluar_bhp',
            'penyesuaian_masuk',
            'penyesuaian_keluar',
            'retur_ke_supplier',
            'expired'
        ) NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE mutasi_stok MODIFY COLUMN tipe ENUM(
            'masuk_pembelian',
            'keluar_resep',
            'keluar_tindakan',
            'penyesuaian_masuk',
            'penyesuaian_keluar',
            'retur_ke_supplier',
            'expired'
        ) NOT NULL");
    }
};

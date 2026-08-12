<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Tambah 'resume_medis' ke enum kolom tipe di surat_keterangan.
     * Laravel Schema Builder tidak bisa MODIFY enum langsung, jadi pakai raw SQL.
     */
    public function up(): void
    {
        DB::statement("
            ALTER TABLE surat_keterangan
            MODIFY tipe ENUM('keterangan_sehat', 'keterangan_sakit', 'rujukan', 'kontrol', 'resume_medis')
            NOT NULL
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE surat_keterangan
            MODIFY tipe ENUM('keterangan_sehat', 'keterangan_sakit', 'rujukan', 'kontrol')
            NOT NULL
        ");
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kolom nyata (bukan cuma di JSON `data`) supaya rekap/laporan Surat
     * Rujukan bisa GROUP BY / JOIN langsung ke tabel tujuan_rujukan tanpa
     * JSON_EXTRACT. Nama tujuan tetap disimpan juga di `data` (snapshot
     * point-in-time, konsisten dgn field snapshot lain) -- kalau nama master
     * diubah/di-rename belakangan, surat yang sudah terbit tetap tampilkan
     * nama aslinya saat diterbitkan.
     */
    public function up(): void
    {
        Schema::table('surat_keterangan', function (Blueprint $table) {
            if (! Schema::hasColumn('surat_keterangan', 'tujuan_rujukan_id')) {
                $table->foreignId('tujuan_rujukan_id')->nullable()->after('dokter_id')
                    ->constrained('tujuan_rujukan')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('surat_keterangan', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tujuan_rujukan_id');
        });
    }
};

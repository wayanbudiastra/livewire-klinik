<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('item_penunjang', function (Blueprint $table) {
            // Cuma dipakai utk kategori='lab' (biaya reagen/bahan per
            // pemeriksaan) -- dasar hitung markup otomatis Lab. Radiologi
            // TIDAK ikut sistem markup otomatis (tetap manual spt sebelumnya),
            // jadi kolom ini dibiarkan null utk baris radiologi.
            $table->decimal('harga_modal', 14, 2)->nullable()->after('tarif_wna');
        });
    }

    public function down(): void
    {
        Schema::table('item_penunjang', function (Blueprint $table) {
            $table->dropColumn('harga_modal');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jejak audit tiap kali data icd10 diimpor/diperbarui -- lihat
     * prd/seeder_icd10_who_resmi.md §6.1. Tabel terpisah (bukan kolom
     * tambahan di icd10) supaya histori tiap batch tetap tersimpan,
     * bukan cuma snapshot "sumber terakhir".
     */
    public function up(): void
    {
        Schema::create('icd10_import_log', function (Blueprint $table) {
            $table->id();
            $table->string('sumber');
            $table->string('sumber_url')->nullable();
            $table->string('versi_who')->nullable();
            $table->string('mode')->default('upsert');
            $table->unsignedInteger('jumlah_baris')->default(0);
            $table->unsignedInteger('jumlah_baru')->default(0);
            $table->unsignedInteger('jumlah_diperbarui')->default(0);
            $table->text('catatan_qa')->nullable();
            $table->foreignId('dijalankan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('icd10_import_log');
    }
};

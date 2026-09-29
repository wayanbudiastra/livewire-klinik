<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sharing fee perawat -- BEDA dari sharing_fee (dokter, per-individu,
     * bisa beda tiap dokter) -- ini 1 persentase GLOBAL per kategori,
     * berlaku sama utk semua perawat. Dihitung dari Tindakan.pelaksana_id:
     * kalau pelaksana tindakan itu perawat, perawat itu dapat X% dari
     * subtotal tindakan yang DIA KERJAKAN SENDIRI (bukan seluruh kunjungan
     * spt dokter, krn kunjungan tidak py "perawat penanggung jawab").
     *
     * Kolom kategori disiapkan supaya nanti bisa diperluas (lab/radiologi/
     * peralatan) tanpa migration baru -- cakupan skrg cuma 'tindakan'.
     */
    public function up(): void
    {
        Schema::create('konfigurasi_sharing_fee_perawat', function (Blueprint $table) {
            $table->id();
            $table->string('kategori', 30)->unique(); // 'tindakan' (baru cakupan ini)
            $table->decimal('persentase', 5, 2);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        DB::table('konfigurasi_sharing_fee_perawat')->insert([
            'kategori' => 'tindakan', 'persentase' => 5, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('konfigurasi_sharing_fee_perawat');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Konfigurasi markup harga jual OTOMATIS berbasis harga modal, per
     * kategori barang/jasa. Beda dari konfigurasi_harga_wna (1 persen
     * global, basis harga_jual, cuma generator manual) -- tabel ini
     * dasarnya harga_pokok/harga_modal (modal), multiplier per kategori
     * beda2, dan diterapkan OTOMATIS setiap kali harga modal berubah.
     *
     * Cakupan saat ini cuma 'obat_bhp' (Barang jenis obat & bahan_habis_pakai)
     * dan 'lab' (ItemPenunjang kategori lab) -- Radiologi, Tindakan, Alkes,
     * dan jenis Barang lainnya SENGAJA tidak ikut, tetap pakai markup WNA
     * manual (konfigurasi_harga_wna) spt sebelumnya.
     */
    public function up(): void
    {
        Schema::create('konfigurasi_markup_harga', function (Blueprint $table) {
            $table->id();
            $table->string('kategori', 30)->unique(); // 'obat_bhp' | 'lab'
            $table->decimal('multiplier_wna', 6, 2);
            $table->decimal('multiplier_ktp', 6, 2);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Default sesuai kebijakan klinik saat migrasi ini dibuat.
        DB::table('konfigurasi_markup_harga')->insert([
            ['kategori' => 'obat_bhp', 'multiplier_wna' => 2.4, 'multiplier_ktp' => 1.6, 'created_at' => now(), 'updated_at' => now()],
            ['kategori' => 'lab',      'multiplier_wna' => 2.0, 'multiplier_ktp' => 1.3, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('konfigurasi_markup_harga');
    }
};

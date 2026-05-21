<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('peralatan_medis', function (Blueprint $table) {
            $table->id();
            $table->string('kode')->unique();
            $table->string('nama');
            $table->string('merk')->nullable();
            $table->string('nomor_seri')->nullable()->unique();
            $table->string('deskripsi')->nullable();
            $table->enum('status', ['tersedia', 'digunakan', 'maintenance', 'rusak'])
                  ->default('tersedia');
            $table->string('lokasi_terakhir')->nullable();
            $table->foreignId('poli_terakhir_id')->nullable()
                  ->constrained('poli')->nullOnDelete();
            $table->date('tanggal_kalibrasi')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('peralatan_medis');
    }
};

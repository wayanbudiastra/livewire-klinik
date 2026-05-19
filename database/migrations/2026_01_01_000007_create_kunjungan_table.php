<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kunjungan', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_antrean');
            $table->foreignId('pasien_id')->constrained('pasien')->onDelete('restrict');
            $table->foreignId('dokter_id')->nullable()->constrained('dokter')->nullOnDelete();
            $table->foreignId('poli_id')->nullable()->constrained('poli')->nullOnDelete();
            $table->dateTime('tanggal')->useCurrent();
            $table->text('keluhan')->nullable();
            $table->enum('status', ['menunggu', 'dalam_pemeriksaan', 'selesai', 'dibatalkan'])->default('menunggu');
            $table->string('tipe_pembayaran')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kunjungan');
    }
};

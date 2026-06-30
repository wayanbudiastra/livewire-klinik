<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('surat_keterangan', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_surat', 30)->unique();
            $table->foreignId('kunjungan_id')->constrained('kunjungan')->cascadeOnDelete();
            $table->enum('tipe', ['keterangan_sehat', 'keterangan_sakit', 'rujukan', 'kontrol']);
            $table->foreignId('dokter_id')->constrained('dokter')->restrictOnDelete();
            $table->json('data');
            $table->foreignId('dicetak_oleh')->constrained('users')->restrictOnDelete();
            $table->timestamp('dicetak_pada');
            $table->timestamps();

            $table->index(['kunjungan_id', 'tipe']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('surat_keterangan');
    }
};

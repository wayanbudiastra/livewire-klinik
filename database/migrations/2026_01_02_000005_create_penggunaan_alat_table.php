<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('penggunaan_alat', function (Blueprint $table) {
            $table->id();
            $table->foreignId('peralatan_id')
                  ->constrained('peralatan_medis')->onDelete('restrict');
            $table->foreignId('poli_id')
                  ->constrained('poli')->onDelete('restrict');
            $table->foreignId('kunjungan_id')->nullable()
                  ->constrained('kunjungan')->nullOnDelete();
            $table->string('dipakai_oleh')->nullable();
            $table->dateTime('waktu_mulai')->useCurrent();
            $table->dateTime('waktu_selesai')->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penggunaan_alat');
    }
};

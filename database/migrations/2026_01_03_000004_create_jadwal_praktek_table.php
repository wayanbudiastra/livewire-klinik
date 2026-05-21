<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jadwal_praktek', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dokter_poli_id')
                  ->constrained('dokter_poli')->onDelete('cascade');
            $table->enum('hari', ['senin','selasa','rabu','kamis','jumat','sabtu','minggu']);
            $table->time('jam_mulai');
            $table->time('jam_selesai');
            $table->unsignedSmallInteger('kuota_pasien')->default(20);
            $table->boolean('is_aktif')->default(true);
            $table->string('keterangan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jadwal_praktek');
    }
};

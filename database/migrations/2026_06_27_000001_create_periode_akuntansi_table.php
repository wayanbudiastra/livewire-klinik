<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('periode_akuntansi', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('tahun');
            $table->unsignedTinyInteger('bulan'); // 1-12
            $table->enum('status', ['terbuka', 'ditutup'])->default('terbuka');
            $table->foreignId('ditutup_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('ditutup_pada')->nullable();
            $table->foreignId('dibuka_kembali_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('dibuka_kembali_pada')->nullable();
            $table->text('alasan_dibuka_kembali')->nullable();
            $table->timestamps();

            $table->unique(['tahun', 'bulan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('periode_akuntansi');
    }
};

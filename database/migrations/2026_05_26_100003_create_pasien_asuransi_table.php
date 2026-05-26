<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pasien_asuransi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pasien_id')->constrained('pasien')->onDelete('cascade');
            $table->foreignId('asuransi_id')->constrained('asuransi')->onDelete('cascade');
            $table->string('nomor_polis', 50);
            $table->string('nama_pemegang_polis', 100)->nullable();
            $table->date('berlaku_mulai')->nullable();
            $table->date('berlaku_sampai')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['pasien_id', 'asuransi_id', 'nomor_polis']);
            $table->index('pasien_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pasien_asuransi');
    }
};

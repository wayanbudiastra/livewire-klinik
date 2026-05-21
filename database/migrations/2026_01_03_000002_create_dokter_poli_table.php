<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dokter_poli', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dokter_id')->constrained('dokter')->onDelete('cascade');
            $table->foreignId('poli_id')->constrained('poli')->onDelete('cascade');
            $table->boolean('is_aktif')->default(true);
            $table->unique(['dokter_id', 'poli_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dokter_poli');
    }
};

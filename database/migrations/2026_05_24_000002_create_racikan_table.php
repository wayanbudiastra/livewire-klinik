<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('racikan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resep_id')->constrained('resep')->onDelete('cascade');
            $table->string('nama_racikan');
            $table->enum('metode', ['puyer', 'kapsul', 'salep', 'krim', 'sirup'])->default('puyer');
            $table->unsignedSmallInteger('jumlah_sediaan')->default(10);
            $table->string('aturan_pakai')->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('racikan');
    }
};

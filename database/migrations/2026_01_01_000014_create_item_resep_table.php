<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_resep', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resep_id')->constrained('resep')->onDelete('cascade');
            $table->foreignId('obat_id')->constrained('obat')->onDelete('restrict');
            $table->unsignedSmallInteger('jumlah');
            $table->string('aturan_pakai')->nullable();
            $table->string('catatan')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_resep');
    }
};

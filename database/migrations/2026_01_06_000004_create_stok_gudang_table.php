<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stok_gudang', function (Blueprint $table) {
            $table->id();
            $table->foreignId('obat_id')->constrained('obat')->onDelete('cascade');
            $table->foreignId('lokasi_gudang_id')->constrained('lokasi_gudang')->onDelete('cascade');
            $table->unsignedInteger('stok')->default(0);
            $table->unsignedInteger('stok_min')->default(10);
            $table->unsignedInteger('stok_max')->default(100);
            $table->unique(['obat_id', 'lokasi_gudang_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stok_gudang');
    }
};

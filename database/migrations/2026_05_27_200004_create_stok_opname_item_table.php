<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stok_opname_item', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stok_opname_id')->constrained('stok_opname')->onDelete('cascade');
            $table->foreignId('barang_id')->constrained('barang')->onDelete('restrict');
            $table->decimal('stok_sistem', 10, 2);
            $table->decimal('stok_fisik', 10, 2)->nullable();
            $table->decimal('selisih', 10, 2)->nullable();
            $table->decimal('hpr_saat_itu', 14, 2)->default(0);
            $table->decimal('nilai_selisih', 14, 2)->default(0);
            $table->enum('tipe_selisih', ['sesuai', 'lebih', 'kurang'])->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->unique(['stok_opname_id', 'barang_id']);
            $table->index('barang_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stok_opname_item');
    }
};

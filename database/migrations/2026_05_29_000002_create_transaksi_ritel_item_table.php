<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaksi_ritel_item', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaksi_ritel_id')->constrained('transaksi_ritel')->onDelete('cascade');
            $table->foreignId('barang_id')->constrained('barang')->onDelete('restrict');
            $table->unsignedSmallInteger('jumlah');
            $table->decimal('harga_satuan', 14, 2);
            $table->decimal('subtotal', 14, 2);
            $table->string('catatan')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaksi_ritel_item');
    }
};

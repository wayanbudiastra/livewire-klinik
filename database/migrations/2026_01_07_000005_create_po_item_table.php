<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('po_item', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_order')->onDelete('cascade');
            $table->foreignId('barang_id')->constrained('barang')->onDelete('restrict');
            $table->unsignedInteger('jumlah_pesan');
            $table->decimal('harga_satuan', 14, 2);
            $table->decimal('diskon_persen', 5, 2)->default(0);
            $table->decimal('subtotal', 14, 2);
            $table->unsignedInteger('jumlah_diterima')->default(0);
            $table->timestamps();
            $table->index('purchase_order_id');
            $table->index('barang_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('po_item');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('retur_gr_item', function (Blueprint $table) {
            $table->id();
            $table->foreignId('retur_gr_id')->constrained('retur_gr')->onDelete('cascade');
            $table->foreignId('gr_item_id')->constrained('gr_item')->onDelete('restrict');
            $table->foreignId('barang_id')->constrained('barang')->onDelete('restrict');
            $table->unsignedInteger('jumlah_retur');
            $table->decimal('harga_satuan', 14, 2);
            $table->decimal('diskon_persen', 5, 2)->default(0);
            $table->decimal('subtotal', 14, 2);

            $table->index('gr_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retur_gr_item');
    }
};

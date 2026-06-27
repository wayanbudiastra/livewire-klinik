<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('retur_resep_item', function (Blueprint $table) {
            $table->id();
            $table->foreignId('retur_resep_id')->constrained('retur_resep')->onDelete('cascade');
            $table->foreignId('item_resep_id')->nullable()->constrained('item_resep')->nullOnDelete();
            $table->foreignId('racikan_id')->nullable()->constrained('racikan')->nullOnDelete();
            $table->foreignId('barang_id')->constrained('barang')->onDelete('restrict');
            $table->decimal('jumlah_retur', 8, 2);
            $table->decimal('harga_satuan', 14, 2);
            $table->decimal('subtotal', 14, 2);

            $table->index('item_resep_id');
            $table->index('racikan_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retur_resep_item');
    }
};

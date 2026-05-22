<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gr_item', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_receipt_id')->constrained('goods_receipt')->onDelete('cascade');
            $table->foreignId('barang_id')->constrained('barang')->onDelete('restrict');
            $table->foreignId('po_item_id')->nullable()->constrained('po_item')->nullOnDelete();
            $table->unsignedInteger('jumlah_terima');
            $table->decimal('harga_satuan', 14, 2);
            $table->decimal('diskon_persen', 5, 2)->default(0);
            $table->decimal('subtotal', 14, 2);
            $table->string('nomor_batch', 50)->nullable();
            $table->date('expired_date')->nullable();
            $table->decimal('hpr_sebelum', 14, 2)->default(0);
            $table->decimal('hpr_sesudah', 14, 2)->default(0);
            $table->timestamps();
            $table->index('goods_receipt_id');
            $table->index('barang_id');
            $table->index('expired_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gr_item');
    }
};

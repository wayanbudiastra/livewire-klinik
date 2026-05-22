<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goods_receipt', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_gr', 30)->unique();
            $table->foreignId('purchase_order_id')->nullable()
                  ->constrained('purchase_order')->nullOnDelete();
            $table->foreignId('supplier_id')->constrained('supplier')->onDelete('restrict');
            $table->foreignId('diterima_oleh')->constrained('users')->onDelete('restrict');
            $table->date('tanggal_terima');
            $table->string('nomor_faktur_supplier', 50)->nullable();
            $table->date('tanggal_faktur')->nullable();
            $table->date('tanggal_jatuh_tempo')->nullable();
            $table->string('nomor_surat_jalan', 50)->nullable();
            $table->decimal('total_nilai', 16, 2)->default(0);
            $table->enum('status', ['draft', 'diverifikasi', 'dibatalkan'])->default('draft');
            $table->text('catatan')->nullable();
            $table->timestamps();
            $table->index(['supplier_id', 'tanggal_terima']);
            $table->index('purchase_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_receipt');
    }
};

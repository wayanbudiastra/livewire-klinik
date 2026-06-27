<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('retur_gr', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_retur', 30)->unique();
            $table->foreignId('goods_receipt_id')->constrained('goods_receipt')->onDelete('restrict');
            $table->foreignId('supplier_id')->constrained('supplier')->onDelete('restrict');
            $table->date('tanggal_retur');
            $table->string('alasan', 100);
            $table->text('catatan')->nullable();
            $table->enum('status', ['draft', 'diverifikasi', 'dibatalkan'])->default('draft');
            $table->decimal('total_nilai', 16, 2)->default(0);
            $table->foreignId('dibuat_oleh')->constrained('users')->onDelete('restrict');
            $table->foreignId('diverifikasi_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('diverifikasi_pada')->nullable();
            $table->timestamps();

            $table->index(['goods_receipt_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retur_gr');
    }
};

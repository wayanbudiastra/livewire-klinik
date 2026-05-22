<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_po', 30)->unique();
            $table->foreignId('supplier_id')->constrained('supplier')->onDelete('restrict');
            $table->foreignId('dibuat_oleh')->constrained('users')->onDelete('restrict');
            $table->foreignId('disetujui_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->date('tanggal_po');
            $table->date('tanggal_kirim_estimasi')->nullable();
            $table->date('tanggal_disetujui')->nullable();
            $table->enum('status', ['draft','dikirim','sebagian','selesai','dibatalkan'])->default('draft');
            $table->decimal('total_nilai', 16, 2)->default(0);
            $table->text('catatan')->nullable();
            $table->timestamps();
            $table->index(['supplier_id', 'status']);
            $table->index('tanggal_po');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order');
    }
};

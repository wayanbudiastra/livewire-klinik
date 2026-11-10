<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('adjustment_stok', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_adjustment', 32)->unique();
            $table->foreignId('barang_id')->constrained('barang')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->enum('tipe', ['penyesuaian_masuk', 'penyesuaian_keluar']);
            $table->unsignedInteger('jumlah');
            $table->text('catatan');
            $table->string('bukti_referensi')->nullable();
            $table->string('file_attachment')->nullable();
            $table->foreignId('mutasi_stok_id')->nullable()->unique()->constrained('mutasi_stok')->nullOnDelete();
            $table->string('status', 32)->default('submitted');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['barang_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index('tipe');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adjustment_stok');
    }
};

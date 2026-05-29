<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaksi_ritel', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_ritel', 25)->unique();
            $table->string('nama_pembeli', 100);
            $table->string('nomor_hp', 20)->nullable();
            $table->foreignId('pasien_id')->nullable()->constrained('pasien')->nullOnDelete();
            $table->foreignId('apoteker_id')->constrained('users')->onDelete('restrict');
            $table->foreignId('kasir_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['draft', 'menunggu_kasir', 'dibayar', 'selesai', 'dibatalkan'])->default('draft');
            $table->enum('metode_bayar', ['tunai', 'transfer', 'kartu', 'split'])->nullable();
            $table->decimal('total_harga', 14, 2)->default(0);
            $table->decimal('total_bayar', 14, 2)->nullable();
            $table->decimal('kembalian', 14, 2)->nullable();
            $table->string('catatan')->nullable();
            $table->timestamp('dibayar_at')->nullable();
            $table->timestamp('diserahkan_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('nama_pembeli');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaksi_ritel');
    }
};

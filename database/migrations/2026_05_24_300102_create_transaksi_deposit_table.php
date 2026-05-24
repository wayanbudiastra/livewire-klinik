<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaksi_deposit', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pasien_id')->constrained('pasien')->onDelete('restrict');
            $table->foreignId('sesi_kas_id')->nullable()->constrained('sesi_kas')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->onDelete('restrict');
            $table->string('nomor_transaksi', 30)->unique();
            $table->enum('tipe', ['topup', 'pemakaian', 'refund', 'koreksi']);
            $table->decimal('jumlah', 16, 2);
            $table->decimal('saldo_sebelum', 16, 2);
            $table->decimal('saldo_sesudah', 16, 2);
            $table->string('referensi_tipe', 50)->nullable();
            $table->unsignedBigInteger('referensi_id')->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->index(['pasien_id', 'created_at']);
            $table->index(['referensi_tipe', 'referensi_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaksi_deposit');
    }
};

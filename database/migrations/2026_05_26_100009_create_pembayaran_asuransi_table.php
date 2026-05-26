<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pembayaran_asuransi', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_pembayaran', 30)->unique();
            $table->foreignId('penagihan_id')->constrained('penagihan_asuransi')->onDelete('restrict');
            $table->foreignId('asuransi_id')->constrained('asuransi')->onDelete('restrict');
            $table->foreignId('dicatat_oleh')->constrained('users')->onDelete('restrict');
            $table->decimal('jumlah_bayar', 16, 2);
            $table->date('tanggal_bayar');
            $table->enum('metode', ['transfer', 'cek', 'giro', 'tunai'])->default('transfer');
            $table->string('nomor_referensi', 100)->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->index('penagihan_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pembayaran_asuransi');
    }
};

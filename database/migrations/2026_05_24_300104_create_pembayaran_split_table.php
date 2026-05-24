<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pembayaran_split', function (Blueprint $table) {
            $table->id();
            $table->foreignId('billing_id')->constrained('billing')->onDelete('cascade');
            $table->foreignId('sesi_kas_id')->nullable()->constrained('sesi_kas')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->onDelete('restrict');
            $table->enum('metode', [
                'tunai', 'debit', 'kredit', 'transfer', 'qris',
                'bpjs', 'asuransi', 'deposit',
            ]);
            $table->decimal('jumlah', 14, 2);
            $table->string('referensi', 100)->nullable();
            $table->string('nama_asuransi', 100)->nullable();
            $table->string('nomor_polis', 50)->nullable();
            $table->decimal('jumlah_cover', 14, 2)->nullable();
            $table->decimal('jumlah_pasien', 14, 2)->nullable();
            $table->dateTime('tanggal_bayar')->useCurrent();
            $table->timestamps();

            $table->index('billing_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pembayaran_split');
    }
};

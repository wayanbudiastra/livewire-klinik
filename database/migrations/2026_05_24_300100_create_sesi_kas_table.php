<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sesi_kas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('restrict');
            $table->date('tanggal');
            $table->dateTime('dibuka_pada');
            $table->decimal('saldo_awal', 16, 2)->default(0);
            $table->dateTime('ditutup_pada')->nullable();
            $table->decimal('saldo_akhir', 16, 2)->nullable();
            $table->decimal('total_cash', 16, 2)->nullable();
            $table->decimal('total_non_cash', 16, 2)->nullable();
            $table->decimal('total_deposit', 16, 2)->nullable();
            $table->decimal('total_bpjs', 16, 2)->nullable();
            $table->decimal('total_asuransi', 16, 2)->nullable();
            $table->decimal('total_pembatalan', 16, 2)->nullable()->default(0);
            $table->enum('status', ['buka', 'tutup'])->default('buka');
            $table->foreignId('dibuka_kembali_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('dibuka_kembali_pada')->nullable();
            $table->text('alasan_dibuka_kembali')->nullable();
            $table->foreignId('ditutup_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->index(['tanggal', 'status']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sesi_kas');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('piutang_asuransi', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_piutang', 30)->unique();
            $table->foreignId('billing_id')->constrained('billing')->onDelete('restrict');
            $table->foreignId('asuransi_id')->constrained('asuransi')->onDelete('restrict');
            $table->foreignId('pasien_id')->constrained('pasien')->onDelete('restrict');
            $table->decimal('jumlah_piutang', 14, 2);
            $table->decimal('jumlah_dibayar', 14, 2)->default(0);
            $table->decimal('sisa_piutang', 14, 2);
            $table->date('tanggal_piutang');
            $table->date('tanggal_jatuh_tempo')->nullable();
            $table->enum('status', ['tertagih', 'diajukan', 'dibayar_sebagian', 'lunas', 'ditolak'])->default('tertagih');
            $table->foreignId('penagihan_id')
                  ->nullable()
                  ->constrained('penagihan_asuransi')
                  ->nullOnDelete();
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->index(['asuransi_id', 'status']);
            $table->index('tanggal_jatuh_tempo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('piutang_asuransi');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('penagihan_asuransi', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_penagihan', 30)->unique();
            $table->foreignId('asuransi_id')->constrained('asuransi')->onDelete('restrict');
            $table->foreignId('dibuat_oleh')->constrained('users')->onDelete('restrict');
            $table->date('tanggal_penagihan');
            $table->date('periode_mulai')->nullable();
            $table->date('periode_akhir')->nullable();
            $table->decimal('total_tagihan', 16, 2)->default(0);
            $table->decimal('total_dibayar', 16, 2)->default(0);
            $table->enum('status', ['draft', 'diajukan', 'dibayar_sebagian', 'lunas', 'ditutup'])->default('draft');
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->index(['asuransi_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penagihan_asuransi');
    }
};

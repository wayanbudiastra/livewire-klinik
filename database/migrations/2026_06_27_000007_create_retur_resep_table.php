<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('retur_resep', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_retur', 30)->unique();
            $table->foreignId('resep_id')->constrained('resep')->onDelete('restrict');
            $table->foreignId('kunjungan_id')->constrained('kunjungan')->onDelete('restrict');
            $table->foreignId('billing_id')->constrained('billing')->onDelete('restrict');
            $table->date('tanggal_retur');
            $table->string('alasan', 100);
            $table->text('catatan')->nullable();
            $table->enum('metode_pengembalian', ['tunai', 'bank', 'deposit']);
            $table->decimal('total_nilai_retur', 16, 2)->default(0);
            $table->foreignId('sesi_kas_id')->nullable()->constrained('sesi_kas')->nullOnDelete();
            $table->foreignId('diproses_oleh')->constrained('users')->onDelete('restrict');
            $table->timestamps();

            $table->index(['resep_id']);
            $table->index(['billing_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retur_resep');
    }
};

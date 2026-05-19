<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kunjungan_id')->unique()->constrained('kunjungan')->onDelete('restrict');
            $table->string('nomor_invoice')->unique();
            $table->decimal('total_tagihan', 14, 2);
            $table->decimal('total_bayar', 14, 2)->default(0);
            $table->decimal('sisa', 14, 2);
            $table->enum('status', ['belum_bayar', 'sebagian', 'lunas', 'dibatalkan'])->default('belum_bayar');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing');
    }
};

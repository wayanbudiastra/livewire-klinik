<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stok_opname', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_opname', 30)->unique();
            $table->foreignId('dibuat_oleh')->constrained('users')->onDelete('restrict');
            $table->foreignId('diverifikasi_oleh')->nullable()->constrained('users')->onDelete('restrict');
            $table->date('tanggal_opname');
            $table->string('keterangan_periode', 100)->nullable();
            $table->enum('status', ['draft', 'menunggu_verifikasi', 'selesai', 'dibatalkan'])->default('draft');
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->index(['tanggal_opname', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stok_opname');
    }
};

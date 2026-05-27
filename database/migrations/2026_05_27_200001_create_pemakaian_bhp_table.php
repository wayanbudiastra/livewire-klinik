<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pemakaian_bhp', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_bhp', 30)->unique();
            $table->foreignId('dicatat_oleh')->constrained('users')->onDelete('restrict');
            $table->foreignId('diverifikasi_oleh')->nullable()->constrained('users')->onDelete('restrict');
            $table->date('tanggal_pemakaian');
            $table->enum('status', ['draft', 'selesai', 'dibatalkan'])->default('draft');
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->index(['tanggal_pemakaian', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pemakaian_bhp');
    }
};

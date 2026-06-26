<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jurnal_manual', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal');
            $table->string('kategori', 30)->nullable();
            $table->string('kode_akun_debit', 10);
            $table->string('kode_akun_kredit', 10);
            $table->decimal('nominal', 16, 2);
            $table->string('keterangan', 255);
            $table->string('dokumen_pendukung')->nullable();
            $table->foreignId('dibuat_oleh')->constrained('users');
            $table->timestamps();

            $table->foreign('kode_akun_debit')->references('kode')->on('chart_of_accounts');
            $table->foreign('kode_akun_kredit')->references('kode')->on('chart_of_accounts');
            $table->index(['tanggal', 'kategori']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jurnal_manual');
    }
};

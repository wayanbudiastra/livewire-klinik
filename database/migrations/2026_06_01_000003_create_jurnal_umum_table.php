<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jurnal_umum', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_jurnal', 30)->unique();
            $table->date('tanggal');
            $table->string('kode_akun_debit', 10);
            $table->string('kode_akun_kredit', 10);
            $table->decimal('nominal', 16, 2);
            $table->string('keterangan', 255)->nullable();
            $table->string('sumber_tipe', 50)->nullable();
            $table->unsignedBigInteger('sumber_id')->nullable();
            $table->unsignedBigInteger('diposting_oleh')->nullable();
            $table->timestamp('diposting_pada')->nullable();
            $table->timestamps();

            $table->index(['tanggal', 'kode_akun_debit']);
            $table->index(['tanggal', 'kode_akun_kredit']);
            $table->index(['sumber_tipe', 'sumber_id']);

            $table->foreign('kode_akun_debit')->references('kode')->on('chart_of_accounts');
            $table->foreign('kode_akun_kredit')->references('kode')->on('chart_of_accounts');
            $table->foreign('diposting_oleh')->references('id')->on('users')->nullOnDelete();
        });

        // Tambah FK di jurnal_pending (kolom sudah dibuat di migration sebelumnya)
        Schema::table('jurnal_pending', function (Blueprint $table) {
            $table->foreign('jurnal_umum_id')->references('id')->on('jurnal_umum')->nullOnDelete();
            $table->foreign('kode_akun_debit')->references('kode')->on('chart_of_accounts');
            $table->foreign('kode_akun_kredit')->references('kode')->on('chart_of_accounts');
        });
    }

    public function down(): void
    {
        Schema::table('jurnal_pending', function (Blueprint $table) {
            $table->dropForeign(['jurnal_umum_id']);
            $table->dropForeign(['kode_akun_debit']);
            $table->dropForeign(['kode_akun_kredit']);
        });

        Schema::dropIfExists('jurnal_umum');
    }
};

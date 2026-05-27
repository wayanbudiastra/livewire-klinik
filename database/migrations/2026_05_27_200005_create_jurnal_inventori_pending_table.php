<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jurnal_inventori_pending', function (Blueprint $table) {
            $table->id();
            $table->string('sumber_tipe', 50);
            $table->unsignedBigInteger('sumber_id');
            $table->string('tipe_transaksi', 50);
            $table->date('tanggal_transaksi');
            $table->string('kode_akun_debit', 20);
            $table->string('kode_akun_kredit', 20);
            $table->decimal('nominal', 16, 2);
            $table->string('keterangan', 255)->nullable();
            $table->json('metadata')->nullable();
            $table->enum('status', ['pending', 'posted', 'diabaikan'])->default('pending');
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->index(['sumber_tipe', 'sumber_id']);
            $table->index(['status', 'tanggal_transaksi']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jurnal_inventori_pending');
    }
};

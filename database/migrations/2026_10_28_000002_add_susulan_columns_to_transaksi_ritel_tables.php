<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Susulan = lab ATAU obat tambahan atas saran dokter untuk pasien yang
        // sudah punya registrasi utama (kunjungan asal). kunjungan_id tetap
        // nullable di DB karena ritel biasa tidak punya kunjungan; kewajibannya
        // untuk tipe 'susulan' ditegakkan di SusulanService.
        Schema::table('transaksi_ritel', function (Blueprint $table) {
            $table->string('tipe', 20)->default('ritel')->after('nomor_ritel');
            $table->string('jenis_susulan', 10)->nullable()->after('tipe');
            $table->foreignId('kunjungan_id')->nullable()->after('pasien_id')
                ->constrained('kunjungan')->restrictOnDelete();
            $table->foreignId('billing_id')->nullable()->after('kunjungan_id')
                ->constrained('billing')->restrictOnDelete();
            $table->foreignId('dokter_id')->nullable()->after('billing_id')
                ->constrained('dokter')->restrictOnDelete();

            $table->index(['kunjungan_id', 'tipe']);
        });

        // Item lab susulan tidak punya barang (tidak ada stok/HPP).
        Schema::table('transaksi_ritel_item', function (Blueprint $table) {
            $table->foreignId('item_penunjang_id')->nullable()->after('barang_id')
                ->constrained('item_penunjang')->restrictOnDelete();
        });
        Schema::table('transaksi_ritel_item', function (Blueprint $table) {
            $table->unsignedBigInteger('barang_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('transaksi_ritel_item', function (Blueprint $table) {
            $table->dropConstrainedForeignId('item_penunjang_id');
        });

        Schema::table('transaksi_ritel', function (Blueprint $table) {
            // FK kunjungan_id memakai index gabungan (kunjungan_id, tipe):
            // FK harus dilepas dulu sebelum index boleh dihapus (MySQL).
            $table->dropForeign(['kunjungan_id']);
            $table->dropIndex(['kunjungan_id', 'tipe']);
            $table->dropColumn('kunjungan_id');
            $table->dropConstrainedForeignId('dokter_id');
            $table->dropConstrainedForeignId('billing_id');
            $table->dropColumn(['tipe', 'jenis_susulan']);
        });
    }
};

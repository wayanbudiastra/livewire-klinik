<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mutasi_stok', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barang_id')->constrained('barang')->onDelete('restrict');
            $table->foreignId('user_id')->constrained('users')->onDelete('restrict');
            $table->enum('tipe', [
                'masuk_pembelian', 'keluar_resep', 'keluar_tindakan',
                'penyesuaian_masuk', 'penyesuaian_keluar',
                'retur_ke_supplier', 'expired',
            ]);
            $table->unsignedInteger('jumlah');
            $table->unsignedInteger('stok_sebelum');
            $table->unsignedInteger('stok_sesudah');
            $table->decimal('hpr_sebelum', 14, 2)->default(0);
            $table->decimal('hpr_sesudah', 14, 2)->default(0);
            $table->string('referensi_tipe', 50)->nullable();
            $table->unsignedBigInteger('referensi_id')->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamps();
            $table->index(['barang_id', 'created_at']);
            $table->index(['referensi_tipe', 'referensi_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mutasi_stok');
    }
};

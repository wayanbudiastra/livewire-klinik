<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_kasir', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('restrict');
            $table->foreignId('superadmin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('aksi', [
                'buka_kas', 'tutup_kas', 'buka_kas_kembali',
                'batalkan_tagihan', 'topup_deposit', 'pakai_deposit',
                'refund_deposit', 'proses_split_payment', 'cetak_invoice',
            ]);
            $table->string('referensi_tipe', 50)->nullable();
            $table->unsignedBigInteger('referensi_id')->nullable();
            $table->json('detail')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['aksi', 'created_at']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_kasir');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('audit_kasir', function (Blueprint $table) {
            $table->enum('aksi', [
                'buka_kas', 'tutup_kas', 'buka_kas_kembali',
                'batalkan_tagihan', 'topup_deposit', 'pakai_deposit',
                'refund_deposit', 'refund_deposit_manual', 'proses_split_payment', 'cetak_invoice',
            ])->change();
        });
    }

    public function down(): void
    {
        Schema::table('audit_kasir', function (Blueprint $table) {
            $table->enum('aksi', [
                'buka_kas', 'tutup_kas', 'buka_kas_kembali',
                'batalkan_tagihan', 'topup_deposit', 'pakai_deposit',
                'refund_deposit', 'proses_split_payment', 'cetak_invoice',
            ])->change();
        });
    }
};

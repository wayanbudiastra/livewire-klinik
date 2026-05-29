<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaksi_ritel', function (Blueprint $table) {
            $table->foreignId('sesi_kas_id')
                  ->nullable()
                  ->after('kasir_id')
                  ->constrained('sesi_kas')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transaksi_ritel', function (Blueprint $table) {
            $table->dropForeign(['sesi_kas_id']);
            $table->dropColumn('sesi_kas_id');
        });
    }
};

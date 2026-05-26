<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kunjungan', function (Blueprint $table) {
            $table->foreignId('pasien_asuransi_id')
                  ->nullable()
                  ->after('tipe_pembayaran')
                  ->constrained('pasien_asuransi')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('kunjungan', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pasien_asuransi_id');
        });
    }
};

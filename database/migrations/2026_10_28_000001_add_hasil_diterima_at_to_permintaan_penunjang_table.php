<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Lab dikerjakan pihak ketiga: order langsung 'selesai' (tertagih) saat
        // disimpan, sedangkan kapan hasilnya diterima dicatat terpisah di sini.
        Schema::table('permintaan_penunjang', function (Blueprint $table) {
            $table->timestamp('hasil_diterima_at')->nullable()->after('hasil_url');
        });
    }

    public function down(): void
    {
        Schema::table('permintaan_penunjang', function (Blueprint $table) {
            $table->dropColumn('hasil_diterima_at');
        });
    }
};

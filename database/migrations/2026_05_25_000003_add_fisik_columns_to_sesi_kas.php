<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sesi_kas', function (Blueprint $table) {
            $table->decimal('uang_fisik_akhir', 16, 2)->nullable()->after('saldo_akhir');
            $table->decimal('selisih', 16, 2)->nullable()->after('uang_fisik_akhir');
        });
    }

    public function down(): void
    {
        Schema::table('sesi_kas', function (Blueprint $table) {
            $table->dropColumn(['uang_fisik_akhir', 'selisih']);
        });
    }
};

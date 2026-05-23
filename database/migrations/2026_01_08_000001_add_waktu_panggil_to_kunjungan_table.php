<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kunjungan', function (Blueprint $table) {
            $table->dateTime('waktu_panggil')->nullable()->after('status');
            $table->string('asal_kedatangan', 100)->nullable()->after('waktu_panggil');
            $table->text('catatan_penting')->nullable()->after('asal_kedatangan');
        });
    }

    public function down(): void
    {
        Schema::table('kunjungan', function (Blueprint $table) {
            $table->dropColumn(['waktu_panggil', 'asal_kedatangan', 'catatan_penting']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // RR / respiratory rate -- permintaan user, tanda vital yang
        // sebelumnya belum ada di asesmen_perawat (BB, TB, TD, Nadi, Suhu,
        // SpO2, GDS sudah ada sejak awal).
        Schema::table('asesmen_perawat', function (Blueprint $table) {
            $table->unsignedSmallInteger('frekuensi_napas')->nullable()->after('nadi');
        });
    }

    public function down(): void
    {
        Schema::table('asesmen_perawat', function (Blueprint $table) {
            $table->dropColumn('frekuensi_napas');
        });
    }
};

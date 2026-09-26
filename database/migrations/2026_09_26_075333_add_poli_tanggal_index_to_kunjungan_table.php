<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Index komposit (poli_id, tanggal) -- dipakai KunjunganService::
     * generateNomorAntrean() (query range tanggal yang sekarang sargable,
     * lihat perbaikan di sana) supaya lockForUpdate() benar-benar mengunci
     * baris yang relevan, dan juga mempercepat query list/waiting-area
     * yang sudah lama filter berdasarkan poli_id + tanggal.
     */
    public function up(): void
    {
        Schema::table('kunjungan', function (Blueprint $table) {
            $table->index(['poli_id', 'tanggal'], 'kunjungan_poli_tanggal_index');
        });
    }

    public function down(): void
    {
        Schema::table('kunjungan', function (Blueprint $table) {
            $table->dropIndex('kunjungan_poli_tanggal_index');
        });
    }
};

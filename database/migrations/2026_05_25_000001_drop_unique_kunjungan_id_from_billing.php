<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing', function (Blueprint $table) {
            // Drop FK first (it depends on the unique index), then drop unique, then re-add FK as plain index
            $table->dropForeign(['kunjungan_id']);
            $table->dropUnique(['kunjungan_id']);
            $table->foreign('kunjungan_id')->references('id')->on('kunjungan')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::table('billing', function (Blueprint $table) {
            $table->dropForeign(['kunjungan_id']);
            $table->unique('kunjungan_id');
            $table->foreign('kunjungan_id')->references('id')->on('kunjungan')->onDelete('restrict');
        });
    }
};

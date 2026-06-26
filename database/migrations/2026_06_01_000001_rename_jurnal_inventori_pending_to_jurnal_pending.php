<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('jurnal_inventori_pending', 'jurnal_pending');

        Schema::table('jurnal_pending', function (Blueprint $table) {
            $table->unsignedBigInteger('jurnal_umum_id')->nullable()->after('posted_at');
        });
    }

    public function down(): void
    {
        Schema::table('jurnal_pending', function (Blueprint $table) {
            $table->dropColumn('jurnal_umum_id');
        });

        Schema::rename('jurnal_pending', 'jurnal_inventori_pending');
    }
};

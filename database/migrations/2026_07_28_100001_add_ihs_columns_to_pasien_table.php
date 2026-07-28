<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pasien', function (Blueprint $table) {
            $table->string('ihs_id', 50)->nullable()->after('nik');
            $table->enum('ihs_status', ['ditemukan', 'tidak_ditemukan', 'error'])->nullable()->after('ihs_id');
            $table->timestamp('ihs_synced_at')->nullable()->after('ihs_status');
            $table->text('ihs_error_msg')->nullable()->after('ihs_synced_at');
        });
    }

    public function down(): void
    {
        Schema::table('pasien', function (Blueprint $table) {
            $table->dropColumn(['ihs_id', 'ihs_status', 'ihs_synced_at', 'ihs_error_msg']);
        });
    }
};

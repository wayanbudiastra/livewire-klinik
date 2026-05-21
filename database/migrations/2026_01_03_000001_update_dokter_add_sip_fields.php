<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dokter', function (Blueprint $table) {
            $table->string('nik', 16)->nullable()->unique()->after('nip');
            $table->string('no_sip')->nullable()->unique()->after('nik');
            $table->date('tgl_expired_sip')->nullable()->after('no_sip');
        });
    }

    public function down(): void
    {
        Schema::table('dokter', function (Blueprint $table) {
            $table->dropColumn(['nik', 'no_sip', 'tgl_expired_sip']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tindakan', function (Blueprint $table) {
            $table->foreignId('pelaksana_id')->nullable()
                  ->constrained('users')->nullOnDelete()
                  ->after('master_tindakan_id');
            $table->dateTime('waktu_tindakan')->nullable()->after('jumlah');
        });
    }

    public function down(): void
    {
        Schema::table('tindakan', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pelaksana_id');
            $table->dropColumn('waktu_tindakan');
        });
    }
};

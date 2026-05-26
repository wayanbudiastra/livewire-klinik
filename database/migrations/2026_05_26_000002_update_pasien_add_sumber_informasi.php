<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pasien', function (Blueprint $table) {
            $table->foreignId('sumber_informasi_id')
                  ->nullable()
                  ->after('is_active')
                  ->constrained('sumber_informasi')
                  ->nullOnDelete();
            $table->string('sumber_informasi_keterangan', 200)
                  ->nullable()
                  ->after('sumber_informasi_id');
        });
    }

    public function down(): void
    {
        Schema::table('pasien', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sumber_informasi_id');
            $table->dropColumn('sumber_informasi_keterangan');
        });
    }
};

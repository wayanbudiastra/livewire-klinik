<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing', function (Blueprint $table) {
            $table->decimal('total_cover_asuransi', 14, 2)->default(0)->after('total_tagihan');
            $table->decimal('total_tanggungan_pasien', 14, 2)->default(0)->after('total_cover_asuransi');
            $table->foreignId('asuransi_id')
                  ->nullable()
                  ->after('total_tanggungan_pasien')
                  ->constrained('asuransi')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('billing', function (Blueprint $table) {
            $table->dropConstrainedForeignId('asuransi_id');
            $table->dropColumn(['total_cover_asuransi', 'total_tanggungan_pasien']);
        });
    }
};

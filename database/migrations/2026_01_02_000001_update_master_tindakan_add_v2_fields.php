<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('master_tindakan', function (Blueprint $table) {
            $table->string('deskripsi')->nullable()->after('nama');
            $table->decimal('tarif_bpjs', 12, 2)->nullable()->after('tarif');
        });
    }

    public function down(): void
    {
        Schema::table('master_tindakan', function (Blueprint $table) {
            $table->dropColumn(['deskripsi', 'tarif_bpjs']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('icd10', function (Blueprint $table) {
            $table->string('nama_en')->nullable()->after('nama');   // nama internasional (WHO English)
            $table->string('nama_id')->nullable()->after('nama_en'); // nama Indonesia (Kemenkes)
        });

        Schema::table('klinik', function (Blueprint $table) {
            $table->enum('bahasa_icd', ['id', 'en'])->default('id')->after('footer_struk');
        });
    }

    public function down(): void
    {
        Schema::table('icd10', function (Blueprint $table) {
            $table->dropColumn(['nama_en', 'nama_id']);
        });
        Schema::table('klinik', function (Blueprint $table) {
            $table->dropColumn('bahasa_icd');
        });
    }
};

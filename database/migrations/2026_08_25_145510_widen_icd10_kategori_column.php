<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * kategori sebelumnya varchar(100) -- cukup untuk label kurasi manual lama
     * (mis. "Infeksi"), tapi nama bab resmi WHO ICD-10 lengkap bisa sampai
     * ~113 karakter (mis. "XVIII Symptoms, signs and abnormal clinical and
     * laboratory findings, not elsewhere classified (R00-R99)"). Lihat
     * app/Console/Commands/Icd10KategoriBackfill.php.
     */
    public function up(): void
    {
        Schema::table('icd10', function (Blueprint $table) {
            $table->string('kategori', 150)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('icd10', function (Blueprint $table) {
            $table->string('kategori', 100)->nullable()->change();
        });
    }
};

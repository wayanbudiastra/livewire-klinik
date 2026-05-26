<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sumber_informasi', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 30)->unique();
            $table->string('nama', 100);
            $table->enum('kategori', ['digital', 'sosial_media', 'word_of_mouth', 'offline', 'lainnya'])->default('lainnya');
            $table->string('icon', 10)->nullable();
            $table->boolean('butuh_keterangan')->default(false);
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'urutan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sumber_informasi');
    }
};

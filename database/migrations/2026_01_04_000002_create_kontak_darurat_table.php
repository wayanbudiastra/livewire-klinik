<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kontak_darurat', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pasien_id')->constrained('pasien')->onDelete('cascade');
            $table->string('nama');
            $table->string('nomor_hp');
            $table->enum('hubungan', [
                'suami','istri','ayah','ibu','anak','kakak','adik',
                'kakek','nenek','paman','bibi','keponakan',
                'teman','rekan_kerja','lainnya',
            ]);
            $table->string('alamat')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kontak_darurat');
    }
};

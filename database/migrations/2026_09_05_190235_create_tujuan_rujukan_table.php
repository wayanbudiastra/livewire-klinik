<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Master data tujuan rujukan (RS/fasilitas) -- supaya Surat Rujukan
     * bisa direkap konsisten (bukan free-text bebas). Baris baru bisa
     * dibuat langsung dari combobox saat dokter isi Surat Rujukan
     * (create-on-the-fly), lihat SuratKeteranganService::buildDataRujukan().
     */
    public function up(): void
    {
        Schema::create('tujuan_rujukan', function (Blueprint $table) {
            $table->id();
            $table->string('nama')->unique();
            $table->boolean('is_aktif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tujuan_rujukan');
    }
};

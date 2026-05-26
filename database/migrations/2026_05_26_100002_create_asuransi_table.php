<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asuransi', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 30)->unique();
            $table->string('nama', 150);
            $table->enum('tipe', ['swasta', 'bpjs', 'pemerintah', 'corporate'])->default('swasta');
            $table->date('periode_mulai')->nullable();
            $table->date('periode_berakhir')->nullable();
            $table->decimal('cover_prosedur', 5, 2)->default(0);
            $table->decimal('cover_laboratorium', 5, 2)->default(0);
            $table->decimal('cover_radiologi', 5, 2)->default(0);
            $table->decimal('cover_peralatan', 5, 2)->default(0);
            $table->decimal('plafon_per_kunjungan', 14, 2)->nullable();
            $table->decimal('plafon_per_tahun', 16, 2)->nullable();
            $table->string('pic', 100)->nullable();
            $table->string('telepon', 20)->nullable();
            $table->string('email')->nullable();
            $table->text('alamat')->nullable();
            $table->unsignedSmallInteger('term_pembayaran_hari')->default(30);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['nama', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asuransi');
    }
};

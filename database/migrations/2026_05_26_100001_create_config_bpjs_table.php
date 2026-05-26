<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('config_bpjs', function (Blueprint $table) {
            $table->id();
            $table->boolean('kerjasama')->default(false);
            $table->boolean('is_active')->default(false);
            $table->string('kode_faskes', 30)->nullable();
            $table->string('nama_faskes', 150)->nullable();
            $table->date('tanggal_kerjasama')->nullable();
            $table->date('tanggal_berakhir')->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('config_bpjs');
    }
};

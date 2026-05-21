<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permintaan_penunjang', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kunjungan_id')
                  ->constrained('kunjungan')->onDelete('restrict');
            $table->foreignId('item_penunjang_id')
                  ->constrained('item_penunjang')->onDelete('restrict');
            $table->unsignedSmallInteger('jumlah')->default(1);
            $table->text('catatan')->nullable();
            $table->enum('status', ['dipesan', 'diproses', 'selesai', 'dibatalkan'])
                  ->default('dipesan');
            $table->string('hasil_url')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permintaan_penunjang');
    }
};

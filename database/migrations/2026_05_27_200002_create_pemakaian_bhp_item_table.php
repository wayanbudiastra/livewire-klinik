<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pemakaian_bhp_item', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pemakaian_bhp_id')->constrained('pemakaian_bhp')->onDelete('cascade');
            $table->foreignId('barang_id')->constrained('barang')->onDelete('restrict');
            $table->decimal('jumlah', 10, 2);
            $table->decimal('harga_pokok_saat_itu', 14, 2)->default(0);
            $table->decimal('nilai_total', 14, 2)->default(0);
            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->index('pemakaian_bhp_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pemakaian_bhp_item');
    }
};

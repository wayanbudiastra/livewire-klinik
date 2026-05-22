<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_barang', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barang_id')->constrained('barang')->onDelete('cascade');
            $table->foreignId('supplier_id')->constrained('supplier')->onDelete('cascade');
            $table->string('kode_barang_supplier', 50)->nullable();
            $table->string('nama_barang_supplier', 150)->nullable();
            $table->decimal('harga_terakhir', 14, 2)->nullable();
            $table->boolean('is_supplier_utama')->default(false);
            $table->timestamps();
            $table->unique(['barang_id', 'supplier_id']);
            $table->index('barang_id');
            $table->index('supplier_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_barang');
    }
};

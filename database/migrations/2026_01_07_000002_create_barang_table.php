<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('barang', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 20)->unique();
            $table->string('nama', 150);
            $table->string('nama_generik', 150)->nullable();
            $table->enum('jenis', ['obat', 'alkes', 'bahan_habis_pakai', 'lainnya']);
            $table->string('kategori', 50)->nullable();
            $table->string('satuan', 20);
            $table->string('satuan_besar', 20)->nullable();
            $table->unsignedSmallInteger('isi_satuan_besar')->nullable();
            $table->string('kemasan', 50)->nullable();

            // Stok & harga
            $table->unsignedInteger('stok')->default(0);
            $table->unsignedInteger('stok_minimum')->default(10);
            $table->unsignedInteger('stok_maksimum')->nullable();
            $table->decimal('harga_pokok', 14, 2)->default(0);
            $table->decimal('harga_jual', 14, 2)->default(0);

            // Info tambahan
            $table->string('golongan', 20)->nullable();
            $table->boolean('butuh_resep')->default(false);
            $table->boolean('is_active')->default(true);

            $table->foreignId('supplier_utama_id')->nullable()
                  ->constrained('supplier')->nullOnDelete();

            $table->timestamps();
            $table->index(['nama', 'jenis', 'is_active']);
            $table->index('stok');
            $table->index('stok_minimum');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('barang');
    }
};

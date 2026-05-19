<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('obat', function (Blueprint $table) {
            $table->id();
            $table->string('kode')->unique();
            $table->string('nama');
            $table->string('generik')->nullable();
            $table->string('satuan');
            $table->unsignedInteger('stok')->default(0);
            $table->decimal('harga', 12, 2);
            $table->decimal('harga_beli', 12, 2)->nullable();
            $table->string('kategori')->nullable();
            $table->boolean('is_active')->default(true);
            $table->date('expired_date')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('obat');
    }
};

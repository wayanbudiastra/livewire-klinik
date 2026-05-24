<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_item', function (Blueprint $table) {
            $table->id();
            $table->foreignId('billing_id')->constrained('billing')->onDelete('cascade');
            $table->enum('jenis', ['tindakan', 'alkes', 'penunjang', 'obat', 'racikan', 'manual']);
            $table->unsignedBigInteger('ref_id')->nullable();
            $table->string('nama_item');
            $table->decimal('qty', 8, 2)->default(1);
            $table->string('satuan', 50)->nullable();
            $table->decimal('harga_satuan', 14, 2);
            $table->decimal('diskon_item', 14, 2)->default(0);
            $table->decimal('subtotal', 14, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_item');
    }
};

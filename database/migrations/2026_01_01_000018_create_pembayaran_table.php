<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pembayaran', function (Blueprint $table) {
            $table->id();
            $table->foreignId('billing_id')->constrained('billing')->onDelete('restrict');
            $table->decimal('jumlah', 14, 2);
            $table->enum('metode', ['tunai', 'transfer', 'bpjs', 'asuransi', 'kartu_debit', 'kartu_kredit']);
            $table->string('referensi')->nullable();
            $table->dateTime('tanggal')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pembayaran');
    }
};

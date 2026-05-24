<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cetak_invoice_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('billing_id')->constrained('billing')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('restrict');
            $table->unsignedSmallInteger('nomor_cetak');
            $table->enum('jenis', ['original', 'copy']);
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index('billing_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cetak_invoice_log');
    }
};

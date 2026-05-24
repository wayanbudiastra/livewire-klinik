<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deposit_pasien', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pasien_id')->unique()->constrained('pasien')->onDelete('restrict');
            $table->decimal('saldo', 16, 2)->default(0);
            $table->decimal('total_topup', 16, 2)->default(0);
            $table->decimal('total_terpakai', 16, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deposit_pasien');
    }
};

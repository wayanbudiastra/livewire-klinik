<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bahan_racikan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('racikan_id')->constrained('racikan')->onDelete('cascade');
            $table->foreignId('obat_id')->constrained('obat')->onDelete('restrict');
            $table->decimal('jumlah', 8, 2);
            $table->string('satuan')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bahan_racikan');
    }
};

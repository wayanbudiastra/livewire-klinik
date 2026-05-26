<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('penagihan_item', function (Blueprint $table) {
            $table->id();
            $table->foreignId('penagihan_id')->constrained('penagihan_asuransi')->onDelete('cascade');
            $table->foreignId('piutang_asuransi_id')->constrained('piutang_asuransi')->onDelete('restrict');
            $table->decimal('jumlah_diajukan', 14, 2);
            $table->decimal('jumlah_disetujui', 14, 2)->nullable();
            $table->timestamps();

            $table->index('penagihan_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penagihan_item');
    }
};

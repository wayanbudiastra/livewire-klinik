<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sharing_fee', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dokter_id')->constrained('dokter')->onDelete('cascade');
            $table->enum('kategori', ['tindakan', 'lab', 'radiologi', 'peralatan']);
            $table->decimal('persentase', 5, 2)->default(0);
            $table->unique(['dokter_id', 'kategori']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sharing_fee');
    }
};

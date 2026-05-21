<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tindakan_poli', function (Blueprint $table) {
            $table->id();
            $table->foreignId('master_tindakan_id')
                  ->constrained('master_tindakan')->onDelete('cascade');
            $table->foreignId('poli_id')
                  ->constrained('poli')->onDelete('cascade');
            $table->unique(['master_tindakan_id', 'poli_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tindakan_poli');
    }
};

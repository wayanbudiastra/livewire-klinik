<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dokter', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('poli_id')->nullable()->constrained('poli')->nullOnDelete();
            $table->string('nip')->nullable()->unique();
            $table->string('sip')->nullable();
            $table->string('spesialisasi')->nullable();
            $table->json('jadwal_praktek')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dokter');
    }
};

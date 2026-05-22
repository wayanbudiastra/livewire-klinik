<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 20)->unique();
            $table->string('nama', 150);
            $table->enum('tipe', ['distributor', 'prinsipal', 'apotek', 'lainnya'])->default('distributor');
            $table->string('pic', 100)->nullable();
            $table->string('telepon', 20)->nullable();
            $table->string('email')->nullable();
            $table->text('alamat')->nullable();
            $table->string('npwp', 30)->nullable();
            $table->unsignedSmallInteger('lead_time_hari')->default(3);
            $table->unsignedSmallInteger('top_hari')->default(30);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['nama', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_kasir', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('restrict');
            $table->decimal('modal_awal', 14, 2)->default(0);
            $table->decimal('total_tunai', 14, 2)->default(0);
            $table->decimal('total_nontunai', 14, 2)->default(0);
            $table->decimal('total_piutang', 14, 2)->default(0);
            $table->decimal('uang_fisik_akhir', 14, 2)->nullable();
            $table->decimal('selisih', 14, 2)->nullable();
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->timestamp('opened_at')->useCurrent();
            $table->timestamp('closed_at')->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_kasir');
    }
};

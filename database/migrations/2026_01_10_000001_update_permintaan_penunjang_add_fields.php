<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('permintaan_penunjang', function (Blueprint $table) {
            $table->enum('prioritas', ['normal', 'cito'])->default('normal')->after('jumlah');
            $table->string('lokasi_tubuh', 100)->nullable()->after('prioritas');
            $table->text('indikasi_klinis')->nullable()->after('lokasi_tubuh');
            $table->foreignId('ordered_by')->nullable()->constrained('users')->after('indikasi_klinis');
        });
    }

    public function down(): void
    {
        Schema::table('permintaan_penunjang', function (Blueprint $table) {
            $table->dropForeign(['ordered_by']);
            $table->dropColumn(['prioritas', 'lokasi_tubuh', 'indikasi_klinis', 'ordered_by']);
        });
    }
};

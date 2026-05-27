<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('barang', function (Blueprint $table) {
            $table->string('barcode', 50)->nullable()->after('kode');
            $table->boolean('is_paten')->default(false)->after('butuh_resep');
            $table->decimal('harga_bpjs', 14, 2)->nullable()->after('harga_jual');
            $table->date('expired_date')->nullable()->after('harga_bpjs');
        });
    }

    public function down(): void
    {
        Schema::table('barang', function (Blueprint $table) {
            $table->dropColumn(['barcode', 'is_paten', 'harga_bpjs', 'expired_date']);
        });
    }
};

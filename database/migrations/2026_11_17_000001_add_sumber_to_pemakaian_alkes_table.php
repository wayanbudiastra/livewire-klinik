<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BHP yang diinput kasir dari panel "Tambah Komponen Tagihan" disimpan sebagai
 * baris pemakaian_alkes (sumber 'kasir') supaya tetap ada saat tagihan dimuat
 * ulang (InvoiceService::buildItems menagih pemakaian_alkes). Stok baru dipotong
 * saat tagihan lunas; stok_dipotong_at menandai baris yang sudah dipotong.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pemakaian_alkes', function (Blueprint $table) {
            $table->string('sumber', 10)->default('dokter')->after('catatan');
            $table->foreignId('dicatat_oleh')->nullable()->after('sumber')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('stok_dipotong_at')->nullable()->after('dicatat_oleh');
        });
    }

    public function down(): void
    {
        Schema::table('pemakaian_alkes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('dicatat_oleh');
            $table->dropColumn(['sumber', 'stok_dipotong_at']);
        });
    }
};

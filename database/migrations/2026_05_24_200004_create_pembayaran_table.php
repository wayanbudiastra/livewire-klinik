<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pembayaran', function (Blueprint $table) {
            // Add shift_id foreign key
            $table->foreignId('shift_id')->nullable()->after('billing_id')
                ->constrained('shift_kasir')->onDelete('restrict');

            // Add granular payment detail columns
            $table->string('bank_nama', 100)->nullable()->after('metode');
            $table->string('nomor_referensi', 100)->nullable()->after('bank_nama');
            $table->string('tipe_kartu', 30)->nullable()->after('nomor_referensi');
            $table->string('nama_asuransi', 100)->nullable()->after('tipe_kartu');
            $table->text('catatan')->nullable()->after('nama_asuransi');
        });

        // Update metode enum to include non_tunai
        DB::statement("ALTER TABLE pembayaran MODIFY COLUMN metode ENUM('tunai','non_tunai','asuransi','transfer','bpjs','kartu_debit','kartu_kredit') NOT NULL");
    }

    public function down(): void
    {
        Schema::table('pembayaran', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shift_id');
            $table->dropColumn(['bank_nama', 'nomor_referensi', 'tipe_kartu', 'nama_asuransi', 'catatan']);
        });
        DB::statement("ALTER TABLE pembayaran MODIFY COLUMN metode ENUM('tunai','transfer','bpjs','asuransi','kartu_debit','kartu_kredit') NOT NULL");
    }
};

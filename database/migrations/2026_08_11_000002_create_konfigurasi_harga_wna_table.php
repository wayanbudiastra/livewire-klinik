<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Konfigurasi single-row: persentase markup default untuk harga WNA.
     * Dipakai sebagai "generate awal" di form master data & bulk-apply,
     * BUKAN dipakai langsung saat transaksi — harga final tetap dibaca
     * dari kolom tarif_wna/harga_wna per item (lihat migration
     * 2026_08_11_000001_add_tarif_wna_columns).
     */
    public function up(): void
    {
        Schema::create('konfigurasi_harga_wna', function (Blueprint $table) {
            $table->id();
            $table->decimal('markup_persen', 5, 2)->default(50);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Selalu ada 1 baris supaya halaman setting tinggal edit, bukan create.
        DB::table('konfigurasi_harga_wna')->insert([
            'markup_persen' => 50,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('konfigurasi_harga_wna');
    }
};

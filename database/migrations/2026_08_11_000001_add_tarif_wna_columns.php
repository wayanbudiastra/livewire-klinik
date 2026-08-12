<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambah kolom harga khusus pasien WNA di master data yang sudah ada.
     *
     * Semua kolom nullable, tanpa backfill — data & transaksi lama tidak
     * berubah sama sekali. Selama kolom ini kosong, sistem otomatis fallback
     * ke tarif umum/BPJS yang sudah ada (lihat App\Services\Harga\TarifResolver).
     */
    public function up(): void
    {
        Schema::table('master_tindakan', function (Blueprint $table) {
            $table->decimal('tarif_wna', 12, 2)->nullable()->after('tarif_bpjs');
        });

        Schema::table('item_penunjang', function (Blueprint $table) {
            $table->decimal('tarif_wna', 12, 2)->nullable()->after('tarif_bpjs');
        });

        Schema::table('barang', function (Blueprint $table) {
            $table->decimal('harga_wna', 12, 2)->nullable()->after('harga_bpjs');
        });
    }

    public function down(): void
    {
        Schema::table('master_tindakan', function (Blueprint $table) {
            $table->dropColumn('tarif_wna');
        });

        Schema::table('item_penunjang', function (Blueprint $table) {
            $table->dropColumn('tarif_wna');
        });

        Schema::table('barang', function (Blueprint $table) {
            $table->dropColumn('harga_wna');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('obat', function (Blueprint $table) {
            if (! Schema::hasColumn('obat', 'barcode')) {
                $table->string('barcode')->nullable()->after('kode');
            }
            if (! Schema::hasColumn('obat', 'jenis_barang')) {
                $table->enum('jenis_barang', ['obat', 'alkes'])->default('obat')->after('nama');
            }
            if (! Schema::hasColumn('obat', 'is_paten')) {
                $table->boolean('is_paten')->default(false)->after('generik');
            }
            if (! Schema::hasColumn('obat', 'satuan_besar_id')) {
                $table->foreignId('satuan_besar_id')->nullable()
                      ->constrained('satuan')->nullOnDelete()->after('satuan');
            }
            if (! Schema::hasColumn('obat', 'satuan_kecil_id')) {
                $table->foreignId('satuan_kecil_id')->nullable()
                      ->constrained('satuan')->nullOnDelete()->after('satuan_besar_id');
            }
            if (! Schema::hasColumn('obat', 'konversi')) {
                $table->unsignedSmallInteger('konversi')->default(1)->after('satuan_kecil_id');
            }
            if (! Schema::hasColumn('obat', 'harga_bpjs')) {
                $table->decimal('harga_bpjs', 12, 2)->nullable()->after('harga');
            }
        });
    }

    public function down(): void
    {
        Schema::table('obat', function (Blueprint $table) {
            $cols = ['barcode','jenis_barang','is_paten','harga_bpjs','konversi'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('obat', $col)) {
                    $table->dropColumn($col);
                }
            }
            foreach (['satuan_besar_id','satuan_kecil_id'] as $fk) {
                if (Schema::hasColumn('obat', $fk)) {
                    $table->dropConstrainedForeignId($fk);
                }
            }
        });
    }
};

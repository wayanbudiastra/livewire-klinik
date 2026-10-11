<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Bug production: upload XLS Master Barang gagal total (97 baris ikut
 * ter-rollback krn 1 baris) dgn SQLSTATE[22001] "Data too long for column
 * 'nama'" -- nama obat lengkap dgn komposisi dlm kurung (umum utk obat
 * racikan paten Indonesia, mis. "HUFAGRIPP FLU BATUK KUNING SYR
 * (Paracetamol 120 mg, Pseudoephedrine HCL 7.5 mg, ...) 60 ML") bisa
 * tembus 150+ karakter, padahal kolom 'nama' cuma varchar(150).
 *
 * Dilebarkan ke 255 (nama_generik ikut, pola/tujuan kolomnya sama) --
 * pakai raw SQL (bukan Schema::table()->change()) supaya tidak perlu
 * nambah dependency doctrine/dbal cuma utk 1 migration ini.
 *
 * Index komposit ['nama','jenis','is_active'] yang sudah ada aman dgn
 * 255 char utf8mb4 (= 1020 byte) + jenis/is_active yang kecil -- masih
 * jauh dari limit 3072 byte per index InnoDB.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE barang MODIFY nama VARCHAR(255) NOT NULL');
        DB::statement('ALTER TABLE barang MODIFY nama_generik VARCHAR(255) NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE barang MODIFY nama VARCHAR(150) NOT NULL');
        DB::statement('ALTER TABLE barang MODIFY nama_generik VARCHAR(150) NULL');
    }
};

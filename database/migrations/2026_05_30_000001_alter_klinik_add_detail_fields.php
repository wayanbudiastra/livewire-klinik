<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('klinik', function (Blueprint $table) {
            $table->string('jenis')->nullable()->after('nama');          // Klinik Pratama / Utama / RS
            $table->string('nomor_izin')->nullable()->after('logo');     // Nomor izin operasional
            $table->string('npwp', 30)->nullable()->after('nomor_izin');
            $table->string('nama_pimpinan')->nullable()->after('npwp');
            $table->string('jabatan_pimpinan')->nullable()->after('nama_pimpinan');
            $table->string('kota')->nullable()->after('alamat');
            $table->string('provinsi')->nullable()->after('kota');
            $table->string('kode_pos', 10)->nullable()->after('provinsi');
            $table->string('fax')->nullable()->after('telepon');
            $table->string('website')->nullable()->after('email');
            $table->text('header_struk')->nullable();                    // Teks tambahan di atas struk
            $table->text('footer_struk')->nullable();                    // Teks di bawah struk / ucapan
        });
    }

    public function down(): void
    {
        Schema::table('klinik', function (Blueprint $table) {
            $table->dropColumn([
                'jenis', 'nomor_izin', 'npwp', 'nama_pimpinan', 'jabatan_pimpinan',
                'kota', 'provinsi', 'kode_pos', 'fax', 'website',
                'header_struk', 'footer_struk',
            ]);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Kolom yang sudah ditambahkan oleh migration gagal sebelumnya:
        // tempat_lahir, tipe_pasien, no_paspor, negara_asal — sudah ada
        // Yang masih perlu ditambahkan:
        Schema::table('pasien', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('no_asuransi');
            $table->softDeletes();
        });

        // Isi nilai default untuk data lama lalu ubah ke not null
        \DB::statement("UPDATE pasien SET tempat_lahir = '-' WHERE tempat_lahir IS NULL OR tempat_lahir = ''");
        \DB::statement("UPDATE pasien SET alamat = '-' WHERE alamat IS NULL OR alamat = ''");
        \DB::statement("UPDATE pasien SET telepon = '000' WHERE telepon IS NULL OR telepon = ''");

        Schema::table('pasien', function (Blueprint $table) {
            $table->string('tempat_lahir')->nullable(false)->change();
            $table->string('alamat')->nullable(false)->change();
            $table->string('telepon')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('pasien', function (Blueprint $table) {
            $table->dropColumn([
                'tempat_lahir', 'tipe_pasien', 'no_paspor',
                'negara_asal', 'is_active', 'deleted_at',
            ]);
            $table->string('alamat')->nullable()->change();
            $table->string('telepon')->nullable()->change();
        });
    }
};

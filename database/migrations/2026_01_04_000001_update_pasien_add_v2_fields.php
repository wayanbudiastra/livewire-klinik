<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tambahkan kolom yang belum ada (safe untuk VPS bersih maupun lokal)
        Schema::table('pasien', function (Blueprint $table) {
            if (! Schema::hasColumn('pasien', 'tempat_lahir')) {
                $table->string('tempat_lahir')->nullable()->after('nama');
            }
            if (! Schema::hasColumn('pasien', 'tipe_pasien')) {
                $table->enum('tipe_pasien', ['WNI', 'WNA'])->default('WNI')->after('jenis_kelamin');
            }
            if (! Schema::hasColumn('pasien', 'no_paspor')) {
                $table->string('no_paspor')->nullable()->unique()->after('nik');
            }
            if (! Schema::hasColumn('pasien', 'negara_asal')) {
                $table->string('negara_asal')->nullable()->after('no_paspor');
            }
            if (! Schema::hasColumn('pasien', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('no_asuransi');
            }
            if (! Schema::hasColumn('pasien', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        // Isi nilai default untuk data lama (kolom sudah pasti ada sekarang)
        \DB::statement("UPDATE pasien SET tempat_lahir = '-' WHERE tempat_lahir IS NULL OR tempat_lahir = ''");
        \DB::statement("UPDATE pasien SET alamat = '-' WHERE alamat IS NULL OR alamat = ''");
        \DB::statement("UPDATE pasien SET telepon = '000' WHERE telepon IS NULL OR telepon = ''");

        // Ubah ke NOT NULL
        Schema::table('pasien', function (Blueprint $table) {
            $table->string('tempat_lahir')->nullable(false)->change();
            $table->string('alamat')->nullable(false)->change();
            $table->string('telepon')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('pasien', function (Blueprint $table) {
            $cols = [];
            foreach (['tempat_lahir','tipe_pasien','no_paspor','negara_asal','is_active','deleted_at'] as $col) {
                if (Schema::hasColumn('pasien', $col)) {
                    $cols[] = $col;
                }
            }
            if (! empty($cols)) {
                $table->dropColumn($cols);
            }
            $table->text('alamat')->nullable()->change();
            $table->string('telepon')->nullable()->change();
        });
    }
};

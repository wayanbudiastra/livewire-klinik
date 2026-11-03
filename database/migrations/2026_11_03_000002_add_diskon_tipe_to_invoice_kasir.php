<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ─── invoice_item: tambah tipe diskon (nominal/persen) + nilai asli ───
        Schema::table('invoice_item', function (Blueprint $table) {
            $table->enum('diskon_tipe', ['nominal', 'persen'])
                ->default('nominal')
                ->after('diskon_item')
                ->comment('Tipe diskon: nominal (Rp) atau persen (%)');
            $table->decimal('diskon_nilai', 14, 2)
                ->default(0)
                ->after('diskon_tipe')
                ->comment('Nilai diskon asli (Rp jika nominal, % jika persen)');
        });

        // ─── billing: tambah tipe diskon global ───
        Schema::table('billing', function (Blueprint $table) {
            $table->enum('diskon_global_tipe', ['nominal', 'persen'])
                ->default('nominal')
                ->after('diskon_global')
                ->comment('Tipe diskon global: nominal (Rp) atau persen (%)');
            $table->decimal('diskon_global_nilai', 14, 2)
                ->default(0)
                ->after('diskon_global_tipe')
                ->comment('Nilai diskon global asli (Rp jika nominal, % jika persen)');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_item', function (Blueprint $table) {
            $table->dropColumn(['diskon_tipe', 'diskon_nilai']);
        });

        Schema::table('billing', function (Blueprint $table) {
            $table->dropColumn(['diskon_global_tipe', 'diskon_global_nilai']);
        });
    }
};

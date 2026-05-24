<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing', function (Blueprint $table) {
            $table->foreignId('sesi_kas_id')->nullable()->after('shift_id')
                ->constrained('sesi_kas')->nullOnDelete();
            $table->decimal('total_deposit_dipakai', 14, 2)->default(0)->after('total_bayar');
            $table->boolean('sudah_cetak')->default(false)->after('status');
            $table->unsignedSmallInteger('jumlah_cetak')->default(0)->after('sudah_cetak');
            $table->dateTime('dibatalkan_pada')->nullable()->after('cancel_reason');
        });
    }

    public function down(): void
    {
        Schema::table('billing', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sesi_kas_id');
            $table->dropColumn(['total_deposit_dipakai', 'sudah_cetak', 'jumlah_cetak', 'dibatalkan_pada']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing', function (Blueprint $table) {
            $table->foreignId('shift_id')->nullable()->after('kunjungan_id')
                ->constrained('shift_kasir')->onDelete('restrict');
            $table->decimal('diskon_global', 14, 2)->default(0)->after('total_bayar');
            $table->foreignId('cancelled_by')->nullable()->after('status')
                ->constrained('users')->onDelete('restrict');
            $table->text('cancel_reason')->nullable()->after('cancelled_by');
        });
    }

    public function down(): void
    {
        Schema::table('billing', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shift_id');
            $table->dropColumn(['diskon_global', 'cancel_reason']);
            $table->dropConstrainedForeignId('cancelled_by');
        });
    }
};

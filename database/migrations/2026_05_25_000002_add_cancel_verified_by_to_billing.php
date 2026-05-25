<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing', function (Blueprint $table) {
            $table->foreignId('cancel_verified_by')
                  ->nullable()
                  ->after('cancelled_by')
                  ->constrained('users')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('billing', function (Blueprint $table) {
            $table->dropForeign(['cancel_verified_by']);
            $table->dropColumn('cancel_verified_by');
        });
    }
};

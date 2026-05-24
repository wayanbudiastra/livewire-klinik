<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resep', function (Blueprint $table) {
            $table->boolean('is_locked')->default(false)->after('catatan');
            $table->foreignId('locked_by')->nullable()
                  ->constrained('users')->nullOnDelete()->after('is_locked');
            $table->timestamp('locked_at')->nullable()->after('locked_by');
            $table->text('catatan_farmasi')->nullable()->after('locked_at');
        });
    }

    public function down(): void
    {
        Schema::table('resep', function (Blueprint $table) {
            $table->dropConstrainedForeignId('locked_by');
            $table->dropColumn(['is_locked', 'locked_at', 'catatan_farmasi']);
        });
    }
};

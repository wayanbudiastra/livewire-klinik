<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('nip')->nullable()->unique()->after('is_active');
            $table->string('telepon')->nullable()->after('nip');
            $table->timestamp('last_login_at')->nullable()->after('telepon');
            $table->timestamp('password_changed_at')->nullable()->after('last_login_at');
            $table->softDeletes()->after('updated_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['nip', 'telepon', 'last_login_at', 'password_changed_at', 'deleted_at']);
        });
    }
};

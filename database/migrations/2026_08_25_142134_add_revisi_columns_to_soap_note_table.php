<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('soap_note', function (Blueprint $table) {
            if (! Schema::hasColumn('soap_note', 'revised_at')) {
                $table->timestamp('revised_at')->nullable()->after('finalized_by');
            }
            if (! Schema::hasColumn('soap_note', 'revised_by')) {
                $table->foreignId('revised_by')->nullable()->after('revised_at')
                    ->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('soap_note', 'revision_count')) {
                $table->unsignedInteger('revision_count')->default(0)->after('revised_by');
            }
            if (! Schema::hasColumn('soap_note', 'revision_reason')) {
                $table->text('revision_reason')->nullable()->after('revision_count');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('soap_note', function (Blueprint $table) {
            $table->dropConstrainedForeignId('revised_by');
            $table->dropColumn(['revised_at', 'revision_count', 'revision_reason']);
        });
    }
};

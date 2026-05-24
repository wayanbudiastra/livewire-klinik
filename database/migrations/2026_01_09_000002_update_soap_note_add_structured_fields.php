<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('soap_note', function (Blueprint $table) {
            // Subjective
            $table->text('s_cc_hpi')->nullable()->after('subjektif');
            $table->text('s_past_medical')->nullable()->after('s_cc_hpi');
            $table->text('s_past_surgical')->nullable()->after('s_past_medical');
            $table->text('s_allergies')->nullable()->after('s_past_surgical');
            $table->text('s_other')->nullable()->after('s_allergies');

            // Objective
            $table->text('o_physical_exam')->nullable()->after('objektif');
            $table->text('o_systemic_exam')->nullable()->after('o_physical_exam');
            $table->text('o_observation')->nullable()->after('o_systemic_exam');
            $table->text('o_other')->nullable()->after('o_observation');

            // Assessment
            $table->text('a_problems')->nullable()->after('asesmen');
            $table->text('a_progress_note')->nullable()->after('a_problems');
            $table->text('a_other')->nullable()->after('a_progress_note');

            // Planning
            $table->text('p_advice')->nullable()->after('plan');
            $table->text('p_other')->nullable()->after('p_advice');

            // Status
            $table->boolean('is_final')->default(false)->after('icd_codes');
            $table->dateTime('finalized_at')->nullable()->after('is_final');
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete()->after('finalized_at');
        });
    }

    public function down(): void
    {
        Schema::table('soap_note', function (Blueprint $table) {
            $table->dropConstrainedForeignId('finalized_by');
            $table->dropColumn([
                's_cc_hpi','s_past_medical','s_past_surgical','s_allergies','s_other',
                'o_physical_exam','o_systemic_exam','o_observation','o_other',
                'a_problems','a_progress_note','a_other',
                'p_advice','p_other',
                'is_final','finalized_at',
            ]);
        });
    }
};

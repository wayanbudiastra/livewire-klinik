<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Restrukturisasi Objective/Assessment/Planning sesuai permintaan user --
     * lihat resources/views/livewire/pemeriksaan/soap-note.blade.php.
     *
     * o_systemic_exam/o_observation/o_other, a_problems/a_progress_note/
     * a_other, p_other TIDAK dihapus (data lama tetap ada) -- cuma tidak
     * ditulis/ditampilkan lagi mulai sekarang di form (field-nya diganti
     * total, bukan ditambah, sesuai keputusan user).
     */
    public function up(): void
    {
        Schema::table('soap_note', function (Blueprint $table) {
            // Objective
            if (! Schema::hasColumn('soap_note', 'o_supporting_examination')) {
                $table->text('o_supporting_examination')->nullable()->after('o_other');
            }

            // Assessment
            if (! Schema::hasColumn('soap_note', 'a_primary_diagnosis')) {
                $table->text('a_primary_diagnosis')->nullable()->after('a_other');
            }
            if (! Schema::hasColumn('soap_note', 'a_differential_diagnosis')) {
                $table->text('a_differential_diagnosis')->nullable()->after('a_primary_diagnosis');
            }

            // Planning
            if (! Schema::hasColumn('soap_note', 'p_treatment')) {
                $table->text('p_treatment')->nullable()->after('p_other');
            }
            if (! Schema::hasColumn('soap_note', 'p_transportation')) {
                $table->string('p_transportation', 20)->nullable()->after('p_treatment');
            }
            if (! Schema::hasColumn('soap_note', 'p_escort')) {
                $table->string('p_escort', 20)->nullable()->after('p_transportation');
            }
            if (! Schema::hasColumn('soap_note', 'p_notes')) {
                $table->text('p_notes')->nullable()->after('p_escort');
            }
        });
    }

    public function down(): void
    {
        Schema::table('soap_note', function (Blueprint $table) {
            $table->dropColumn([
                'o_supporting_examination',
                'a_primary_diagnosis', 'a_differential_diagnosis',
                'p_treatment', 'p_transportation', 'p_escort', 'p_notes',
            ]);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pisahkan "Chief Complaint & History of Present Illness (CC + HPI)"
     * (kolom gabungan s_cc_hpi) jadi 2 field terpisah sesuai charting SOAP
     * standar -- s_chief_complaint (singkat) & s_hpi (naratif detail).
     *
     * s_cc_hpi TIDAK dihapus (data lama tetap ada, dibaca sbg fallback di
     * app/Livewire/Pemeriksaan/SoapNote.php) -- cuma tidak ditulis lagi
     * mulai sekarang. Data existing disalin ke s_hpi (bukan
     * s_chief_complaint) karena isinya sudah berupa naratif gabungan,
     * lebih dekat ke HPI daripada CC singkat.
     */
    public function up(): void
    {
        Schema::table('soap_note', function (Blueprint $table) {
            if (! Schema::hasColumn('soap_note', 's_chief_complaint')) {
                $table->text('s_chief_complaint')->nullable()->after('subjektif');
            }
            if (! Schema::hasColumn('soap_note', 's_hpi')) {
                $table->text('s_hpi')->nullable()->after('s_chief_complaint');
            }
        });

        DB::table('soap_note')
            ->whereNotNull('s_cc_hpi')
            ->where('s_cc_hpi', '!=', '')
            ->whereNull('s_hpi')
            ->update(['s_hpi' => DB::raw('s_cc_hpi')]);
    }

    public function down(): void
    {
        Schema::table('soap_note', function (Blueprint $table) {
            $table->dropColumn(['s_chief_complaint', 's_hpi']);
        });
    }
};

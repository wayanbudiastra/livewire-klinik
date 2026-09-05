<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jejak revisi surat yang sudah terbit -- pola sama persis dengan
     * revised_at/revised_by/revision_count/revision_reason di soap_note
     * (lihat app/Livewire/Pemeriksaan/SoapNote.php). nomor_surat &
     * dicetak_pada/dicetak_oleh TIDAK berubah saat direvisi -- identitas
     * dokumen asli dipertahankan, cuma isi `data` yang diedit.
     */
    public function up(): void
    {
        Schema::table('surat_keterangan', function (Blueprint $table) {
            if (! Schema::hasColumn('surat_keterangan', 'revised_at')) {
                $table->timestamp('revised_at')->nullable()->after('dicetak_pada');
            }
            if (! Schema::hasColumn('surat_keterangan', 'revised_by')) {
                $table->foreignId('revised_by')->nullable()->after('revised_at')
                    ->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('surat_keterangan', 'revision_count')) {
                $table->unsignedInteger('revision_count')->default(0)->after('revised_by');
            }
            if (! Schema::hasColumn('surat_keterangan', 'revision_reason')) {
                $table->text('revision_reason')->nullable()->after('revision_count');
            }
        });
    }

    public function down(): void
    {
        Schema::table('surat_keterangan', function (Blueprint $table) {
            $table->dropConstrainedForeignId('revised_by');
            $table->dropColumn(['revised_at', 'revision_count', 'revision_reason']);
        });
    }
};

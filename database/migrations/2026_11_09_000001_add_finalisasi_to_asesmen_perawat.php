<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Perawat bisa "kunci" (finalisasi) tanda vital begitu lengkap diisi,
        // sehingga kasir sudah bisa memproses pembayaran tanpa perlu menunggu
        // dokter menulis/memfinalisasi SOAP Note. Ini memenuhi kebutuhan kasir
        // agar tidak terhenti saat antrian panjang, dengan tradeoff: pasien bisa
        // ditagih & dibayar TANPA rekam medis dokter (hanya ada tanda vital +
        // tindakan yang tercatat).
        //
        // NB: sebelumnya gate pembayaran memakai SOAP Note final; sekarang pakai
        // TTV final. Keputusan kebijakan ini harus dikonfirmasi ke dokter/pemilik
        // klinik bahwa billing tanpa SOAP sekarang sudah diperkenankan.
        Schema::table('asesmen_perawat', function (Blueprint $table) {
            $table->boolean('is_final')->default(false)->after('anamnesis_awal');
            $table->dateTime('finalized_at')->nullable()->after('is_final');
            $table->unsignedBigInteger('finalized_by')->nullable()->after('finalized_at');
            $table->foreign('finalized_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('asesmen_perawat', function (Blueprint $table) {
            $table->dropForeign(['finalized_by']);
            $table->dropColumn(['is_final', 'finalized_at', 'finalized_by']);
        });
    }
};

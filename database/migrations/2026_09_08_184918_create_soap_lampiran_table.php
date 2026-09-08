<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lampiran dokumen/foto untuk SOAP Note (permintaan user): foto luka,
     * foto hasil jahitan, gambaran radiologis, file ekspertise radiologi,
     * surat persetujuan tindakan, dll. Disimpan di disk 'local' (private,
     * bukan 'public') karena berisi data medis pasien -- diakses lewat
     * route ber-permission, bukan URL publik langsung.
     *
     * Ditautkan ke kunjungan_id (bukan soap_note_id) supaya bisa diupload
     * kapan saja selama pemeriksaan berlangsung, tidak perlu menunggu SOAP
     * Note pertama kali disimpan dulu (soap_note baru dibuat saat "Simpan
     * Draft" pertama -- lihat SoapNote::doSimpan()).
     */
    public function up(): void
    {
        Schema::create('soap_lampiran', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kunjungan_id')->constrained('kunjungan')->onDelete('cascade');
            $table->string('kategori', 40);
            $table->string('nama_file');
            $table->string('path');
            $table->string('mime_type', 100)->nullable();
            $table->unsignedInteger('ukuran')->nullable()->comment('bytes');
            $table->text('keterangan')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('soap_lampiran');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->string('kode_booking')->unique();
            $table->foreignId('pasien_id')->constrained('pasien')->onDelete('restrict');
            $table->foreignId('dokter_id')->constrained('dokter')->onDelete('restrict');
            $table->foreignId('poli_id')->constrained('poli')->onDelete('restrict');
            $table->foreignId('jadwal_praktek_id')->nullable()
                  ->constrained('jadwal_praktek')->nullOnDelete();
            $table->date('tanggal_appointment');
            $table->text('keluhan')->nullable();
            $table->enum('status', ['booked', 'checked_in', 'cancelled'])->default('booked');
            $table->string('catatan')->nullable();
            $table->timestamps();
        });

        // Tambah kolom appointment_id ke kunjungan
        Schema::table('kunjungan', function (Blueprint $table) {
            $table->foreignId('appointment_id')->nullable()
                  ->constrained('appointments')->nullOnDelete()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('kunjungan', function (Blueprint $table) {
            $table->dropConstrainedForeignId('appointment_id');
        });
        Schema::dropIfExists('appointments');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proposal_harga', function (Blueprint $table) {
            $table->id();
            $table->string('judul', 200);
            $table->unsignedSmallInteger('tahun');
            $table->date('tanggal_efektif');
            $table->enum('cakupan', ['semua', 'tindakan', 'barang'])->default('semua');
            $table->text('catatan')->nullable();
            $table->json('konfigurasi_kenaikan');
            $table->boolean('ikut_bpjs')->default(false);
            $table->enum('status', [
                'draft',
                'menunggu_persetujuan',
                'disetujui',
                'efektif',
                'dibatalkan',
            ])->default('draft');
            $table->foreignId('dibuat_oleh')->constrained('users');
            $table->foreignId('disetujui_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('diterapkan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('ditolak_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('disetujui_pada')->nullable();
            $table->timestamp('diterapkan_pada')->nullable();
            $table->timestamp('ditolak_pada')->nullable();
            $table->text('alasan_tolak')->nullable();
            $table->timestamps();

            $table->index(['status', 'tahun'], 'idx_proposal_status_tahun');
        });

        Schema::create('proposal_harga_item', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposal_harga_id')
                  ->constrained('proposal_harga')
                  ->cascadeOnDelete();
            $table->enum('item_type', ['tindakan', 'barang']);
            $table->unsignedBigInteger('item_id');
            $table->string('item_nama', 200);
            $table->string('item_kategori', 100)->nullable();
            $table->decimal('harga_lama', 14, 2);
            $table->decimal('persen_kenaikan', 5, 2)->default(0);
            $table->decimal('harga_kalkulasi', 14, 2);
            $table->decimal('harga_baru', 14, 2);
            $table->decimal('harga_bpjs_lama', 14, 2)->nullable();
            $table->decimal('harga_bpjs_baru', 14, 2)->nullable();
            $table->boolean('is_dikoreksi_manual')->default(false);
            $table->boolean('is_skip')->default(false);
            $table->foreignId('dikoreksi_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('dikoreksi_pada')->nullable();
            $table->timestamps();

            $table->index(['proposal_harga_id', 'item_type'], 'idx_proposal_item_type');
            $table->index(['item_type', 'item_id'], 'idx_item_lookup');
            $table->index(['item_type', 'item_id', 'proposal_harga_id'], 'idx_riwayat');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proposal_harga_item');
        Schema::dropIfExists('proposal_harga');
    }
};

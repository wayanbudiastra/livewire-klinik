<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('config_satusehat', function (Blueprint $table) {
            $table->id();

            // Status integrasi & lingkungan aktif
            $table->boolean('is_active')->default(false);
            $table->enum('environment', ['sandbox', 'production'])->default('sandbox');

            // Identitas faskes di SatuSehat (dipakai keduanya)
            $table->string('organization_id', 36)->nullable()
                  ->comment('IHS Organization ID faskes dari platform.satusehat.kemkes.go.id');

            // Kredensial Sandbox
            $table->string('sandbox_client_id')->nullable();
            $table->text('sandbox_client_secret')->nullable();

            // Kredensial Production
            $table->string('prod_client_id')->nullable();
            $table->text('prod_client_secret')->nullable();

            // Status koneksi terakhir (berhasil test ping)
            $table->timestamp('sandbox_last_ping_at')->nullable();
            $table->enum('sandbox_last_ping_status', ['ok', 'error'])->nullable();
            $table->timestamp('prod_last_ping_at')->nullable();
            $table->enum('prod_last_ping_status', ['ok', 'error'])->nullable();

            $table->text('catatan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('config_satusehat');
    }
};

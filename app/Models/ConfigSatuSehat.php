<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class ConfigSatuSehat extends Model
{
    protected $table = 'config_satusehat';

    protected $fillable = [
        'is_active', 'environment', 'organization_id',
        'sandbox_client_id', 'sandbox_client_secret',
        'prod_client_id', 'prod_client_secret',
        'sandbox_last_ping_at', 'sandbox_last_ping_status',
        'prod_last_ping_at', 'prod_last_ping_status',
        'catatan',
    ];

    protected function casts(): array
    {
        return [
            'is_active'              => 'boolean',
            'sandbox_last_ping_at'   => 'datetime',
            'prod_last_ping_at'      => 'datetime',
        ];
    }

    // Base URL per lingkungan (tidak disimpan di DB — tetap dari Kemkes)
    public const BASE_URL = [
        'sandbox'    => 'https://api-satusehat-stg.dto.kemkes.go.id',
        'production' => 'https://api-satusehat.kemkes.go.id',
    ];

    public const AUTH_URL = [
        'sandbox'    => 'https://api-satusehat-stg.dto.kemkes.go.id/oauth2/v1/accesstoken',
        'production' => 'https://api-satusehat.kemkes.go.id/oauth2/v1/accesstoken',
    ];

    /** Ambil konfigurasi (singleton, baris pertama atau baru). */
    public static function config(): self
    {
        return static::firstOrNew(['id' => 1]);
    }

    /** Apakah integrasi SatuSehat aktif (di-cache 60 detik). */
    public static function aktif(): bool
    {
        return Cache::remember('satusehat.aktif', 60, function () {
            return (bool) static::value('is_active');
        });
    }

    /** Hapus cache status aktif (dipanggil setelah config disimpan). */
    public static function clearCache(): void
    {
        Cache::forget('satusehat.aktif');
    }

    /** Kembalikan client_id untuk environment saat ini. */
    public function getClientId(): ?string
    {
        return $this->environment === 'production'
            ? $this->prod_client_id
            : $this->sandbox_client_id;
    }

    /** Kembalikan client_secret untuk environment saat ini. */
    public function getClientSecret(): ?string
    {
        return $this->environment === 'production'
            ? $this->prod_client_secret
            : $this->sandbox_client_secret;
    }

    /** Base URL API untuk environment saat ini. */
    public function getBaseUrl(): string
    {
        return self::BASE_URL[$this->environment] ?? self::BASE_URL['sandbox'];
    }

    /** URL OAuth token untuk environment saat ini. */
    public function getAuthUrl(): string
    {
        return self::AUTH_URL[$this->environment] ?? self::AUTH_URL['sandbox'];
    }
}

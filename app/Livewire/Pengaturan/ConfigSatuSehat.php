<?php

namespace App\Livewire\Pengaturan;

use App\Models\ConfigSatuSehat as ConfigSatuSehatModel;
use Illuminate\Support\Facades\Http;
use Livewire\Component;

class ConfigSatuSehat extends Component
{
    // ── Status ─────────────────────────────────────────────────────
    public bool   $isActive    = false;
    public string $environment = 'sandbox';

    // ── Identitas Faskes ───────────────────────────────────────────
    public string $organizationId = '';

    // ── Kredensial Sandbox ─────────────────────────────────────────
    public string $sandboxClientId     = '';
    public string $sandboxClientSecret = '';

    // ── Kredensial Production ──────────────────────────────────────
    public string $prodClientId     = '';
    public string $prodClientSecret = '';

    // ── Catatan ────────────────────────────────────────────────────
    public string $catatan = '';

    // ── State ping ─────────────────────────────────────────────────
    public ?string $pingEnv     = null;  // 'sandbox' | 'production'
    public bool    $pingLoading = false;
    public ?string $pingStatus  = null;  // 'ok' | 'error' | null
    public ?string $pingMessage = null;

    public function mount(): void
    {
        $c = ConfigSatuSehatModel::config();

        $this->isActive          = (bool) $c->is_active;
        $this->environment       = $c->environment ?? 'sandbox';
        $this->organizationId    = $c->organization_id ?? '';
        $this->sandboxClientId   = $c->sandbox_client_id ?? '';
        $this->sandboxClientSecret = $c->sandbox_client_secret ?? '';
        $this->prodClientId      = $c->prod_client_id ?? '';
        $this->prodClientSecret  = $c->prod_client_secret ?? '';
        $this->catatan           = $c->catatan ?? '';
    }

    public function simpan(): void
    {
        $this->validate([
            'environment'       => ['required', 'in:sandbox,production'],
            'organizationId'    => ['nullable', 'string', 'max:36'],
            'sandboxClientId'   => ['nullable', 'string', 'max:255'],
            'sandboxClientSecret' => ['nullable', 'string', 'max:1000'],
            'prodClientId'      => ['nullable', 'string', 'max:255'],
            'prodClientSecret'  => ['nullable', 'string', 'max:1000'],
        ], [
            'environment.in' => 'Lingkungan harus sandbox atau production.',
        ]);

        ConfigSatuSehatModel::updateOrCreate(['id' => 1], [
            'is_active'              => $this->isActive,
            'environment'            => $this->environment,
            'organization_id'        => $this->organizationId ?: null,
            'sandbox_client_id'      => $this->sandboxClientId ?: null,
            'sandbox_client_secret'  => $this->sandboxClientSecret ?: null,
            'prod_client_id'         => $this->prodClientId ?: null,
            'prod_client_secret'     => $this->prodClientSecret ?: null,
            'catatan'                => $this->catatan ?: null,
        ]);

        $this->dispatch('notify', type: 'success', message: 'Konfigurasi SatuSehat berhasil disimpan.');
    }

    /**
     * Uji koneksi ke endpoint OAuth SatuSehat.
     * Menggunakan Basic Auth: base64(client_id:client_secret).
     */
    public function testKoneksi(string $env): void
    {
        $clientId     = $env === 'production' ? $this->prodClientId     : $this->sandboxClientId;
        $clientSecret = $env === 'production' ? $this->prodClientSecret : $this->sandboxClientSecret;

        if (!$clientId || !$clientSecret) {
            $this->pingEnv     = $env;
            $this->pingStatus  = 'error';
            $this->pingMessage = 'Client ID dan Client Secret wajib diisi sebelum tes koneksi.';
            return;
        }

        $authUrl = ConfigSatuSehatModel::AUTH_URL[$env] ?? ConfigSatuSehatModel::AUTH_URL['sandbox'];

        $this->pingEnv     = $env;
        $this->pingStatus  = null;
        $this->pingMessage = null;

        try {
            $response = Http::timeout(10)
                ->withBasicAuth($clientId, $clientSecret)
                ->asForm()
                ->post($authUrl . '?grant_type=client_credentials');

            $now = now();

            if ($response->successful() && isset($response->json()['access_token'])) {
                $this->pingStatus  = 'ok';
                $this->pingMessage = 'Koneksi berhasil! Token diterima dari server SatuSehat.';

                // Simpan status ping ke DB
                $column = $env === 'production' ? 'prod' : 'sandbox';
                ConfigSatuSehatModel::where('id', 1)->update([
                    "{$column}_last_ping_at"     => $now,
                    "{$column}_last_ping_status" => 'ok',
                ]);
            } else {
                $body = $response->json();
                $pesan = $body['fault']['faultstring'] ?? ($body['error_description'] ?? 'Respons tidak valid dari server.');

                $this->pingStatus  = 'error';
                $this->pingMessage = "Gagal: {$pesan} (HTTP {$response->status()})";

                $column = $env === 'production' ? 'prod' : 'sandbox';
                ConfigSatuSehatModel::where('id', 1)->update([
                    "{$column}_last_ping_at"     => $now,
                    "{$column}_last_ping_status" => 'error',
                ]);
            }
        } catch (\Illuminate\Http\Client\ConnectionException) {
            $this->pingStatus  = 'error';
            $this->pingMessage = 'Tidak dapat terhubung ke server SatuSehat. Periksa koneksi internet.';
        } catch (\Throwable $e) {
            $this->pingStatus  = 'error';
            $this->pingMessage = 'Kesalahan tidak terduga: ' . $e->getMessage();
        }
    }

    public function render()
    {
        $config = ConfigSatuSehatModel::first();

        return view('livewire.pengaturan.config-satu-sehat', [
            'sandboxPingAt'     => $config?->sandbox_last_ping_at,
            'sandboxPingStatus' => $config?->sandbox_last_ping_status,
            'prodPingAt'        => $config?->prod_last_ping_at,
            'prodPingStatus'    => $config?->prod_last_ping_status,
            'baseUrlSandbox'    => ConfigSatuSehatModel::BASE_URL['sandbox'],
            'baseUrlProd'       => ConfigSatuSehatModel::BASE_URL['production'],
        ]);
    }
}

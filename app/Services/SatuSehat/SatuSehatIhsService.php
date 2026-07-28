<?php

namespace App\Services\SatuSehat;

use App\Models\ConfigSatuSehat;
use App\Models\Dokter;
use App\Models\Pasien;
use App\Models\Perawat;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class SatuSehatIhsService
{
    private ?string $accessToken = null;

    // ── Token OAuth2 ────────────────────────────────────────────

    /**
     * Ambil access token dari SatuSehat OAuth2.
     * Di-cache di property untuk satu sesi bulk fetch.
     *
     * @throws \RuntimeException jika login gagal
     */
    public function getAccessToken(): string
    {
        if ($this->accessToken) {
            return $this->accessToken;
        }

        $config   = ConfigSatuSehat::config();
        $clientId = $config->getClientId();
        $secret   = $config->getClientSecret();
        $authUrl  = $config->getAuthUrl();

        if (! $clientId || ! $secret) {
            throw new \RuntimeException(
                'Client ID atau Client Secret belum dikonfigurasi di Pengaturan SatuSehat.'
            );
        }

        try {
            $response = Http::timeout(15)
                ->withBasicAuth($clientId, $secret)
                ->asForm()
                ->post($authUrl . '?grant_type=client_credentials');
        } catch (ConnectionException) {
            throw new \RuntimeException(
                'Tidak dapat terhubung ke server SatuSehat. Periksa koneksi internet.'
            );
        }

        $data = $response->json();

        if (! $response->successful() || empty($data['access_token'])) {
            $pesan = $data['fault']['faultstring']
                ?? ($data['error_description'] ?? 'Respons tidak valid dari server SatuSehat.');
            throw new \RuntimeException("Login SatuSehat gagal: {$pesan}");
        }

        $this->accessToken = $data['access_token'];

        return $this->accessToken;
    }

    /** Reset token (dipanggil jika response 401 di tengah bulk fetch). */
    public function resetToken(): void
    {
        $this->accessToken = null;
    }

    // ── Fetch IHS Pasien ────────────────────────────────────────

    /**
     * Fetch dan simpan IHS ID untuk satu Pasien.
     *
     * @return array{ihs_id:?string, status:string, pesan:string}
     */
    public function fetchPasien(Pasien $pasien): array
    {
        $nik = $pasien->nik;

        // WNI lookup via NIK, WNA via paspor
        if ($pasien->tipe_pasien === 'WNA') {
            $identifier = $pasien->no_paspor;
            $prefix     = 'https://fhir.kemkes.go.id/id/paspor';
        } else {
            $identifier = $nik;
            $prefix     = 'https://fhir.kemkes.go.id/id/nik';
        }

        if (! $identifier) {
            $label = $pasien->tipe_pasien === 'WNA' ? 'No. Paspor' : 'NIK';
            $hasil = ['ihs_id' => null, 'status' => 'error', 'pesan' => "{$label} belum diisi."];
            $this->simpanHasilPasien($pasien, $hasil);
            return $hasil;
        }

        $config  = ConfigSatuSehat::config();
        $baseUrl = $config->getBaseUrl();

        $hasil = $this->queryFhir(
            "{$baseUrl}/fhir-r4/v1/Patient",
            ['identifier' => "{$prefix}|{$identifier}"],
            'Patient'
        );

        $this->simpanHasilPasien($pasien, $hasil);

        return $hasil;
    }

    private function simpanHasilPasien(Pasien $pasien, array $hasil): void
    {
        $pasien->update([
            'ihs_id'        => $hasil['ihs_id'],
            'ihs_status'    => $hasil['status'],
            'ihs_synced_at' => now(),
            'ihs_error_msg' => $hasil['status'] !== 'ditemukan' ? $hasil['pesan'] : null,
        ]);
    }

    // ── Fetch IHS Dokter ────────────────────────────────────────

    /**
     * Fetch dan simpan IHS ID untuk satu Dokter (Practitioner).
     *
     * @return array{ihs_id:?string, status:string, pesan:string}
     */
    public function fetchDokter(Dokter $dokter): array
    {
        if (! $dokter->nik) {
            $hasil = ['ihs_id' => null, 'status' => 'error', 'pesan' => 'NIK belum diisi.'];
            $this->simpanHasilDokter($dokter, $hasil);
            return $hasil;
        }

        $config  = ConfigSatuSehat::config();
        $baseUrl = $config->getBaseUrl();

        $hasil = $this->queryFhir(
            "{$baseUrl}/fhir-r4/v1/Practitioner",
            ['identifier' => "https://fhir.kemkes.go.id/id/nik|{$dokter->nik}"],
            'Practitioner'
        );

        $this->simpanHasilDokter($dokter, $hasil);

        return $hasil;
    }

    private function simpanHasilDokter(Dokter $dokter, array $hasil): void
    {
        $dokter->update([
            'ihs_id'        => $hasil['ihs_id'],
            'ihs_status'    => $hasil['status'],
            'ihs_synced_at' => now(),
            'ihs_error_msg' => $hasil['status'] !== 'ditemukan' ? $hasil['pesan'] : null,
        ]);
    }

    // ── Fetch IHS Perawat (Nakkes) ──────────────────────────────

    /**
     * Fetch dan simpan IHS ID untuk satu Perawat (Practitioner).
     *
     * @return array{ihs_id:?string, status:string, pesan:string}
     */
    public function fetchPerawat(Perawat $perawat): array
    {
        if (! $perawat->nik) {
            $hasil = ['ihs_id' => null, 'status' => 'error', 'pesan' => 'NIK belum diisi.'];
            $this->simpanHasilPerawat($perawat, $hasil);
            return $hasil;
        }

        $config  = ConfigSatuSehat::config();
        $baseUrl = $config->getBaseUrl();

        $hasil = $this->queryFhir(
            "{$baseUrl}/fhir-r4/v1/Practitioner",
            ['identifier' => "https://fhir.kemkes.go.id/id/nik|{$perawat->nik}"],
            'Practitioner'
        );

        $this->simpanHasilPerawat($perawat, $hasil);

        return $hasil;
    }

    private function simpanHasilPerawat(Perawat $perawat, array $hasil): void
    {
        $perawat->update([
            'ihs_id'        => $hasil['ihs_id'],
            'ihs_status'    => $hasil['status'],
            'ihs_synced_at' => now(),
            'ihs_error_msg' => $hasil['status'] !== 'ditemukan' ? $hasil['pesan'] : null,
        ]);
    }

    // ── Core FHIR query ─────────────────────────────────────────

    /**
     * Query endpoint FHIR, parse Bundle, return hasil standar.
     * Otomatis retry satu kali jika token expired (HTTP 401).
     *
     * @return array{ihs_id:?string, status:string, pesan:string}
     */
    private function queryFhir(string $url, array $params, string $resourceType, bool $isRetry = false): array
    {
        try {
            $token    = $this->getAccessToken();
            $response = Http::timeout(15)
                ->withToken($token)
                ->get($url, $params);
        } catch (ConnectionException) {
            return [
                'ihs_id' => null,
                'status' => 'error',
                'pesan'  => 'Tidak dapat terhubung ke server SatuSehat.',
            ];
        } catch (\RuntimeException $e) {
            return ['ihs_id' => null, 'status' => 'error', 'pesan' => $e->getMessage()];
        }

        // Jika 401 — refresh token sekali lagi
        if ($response->status() === 401 && ! $isRetry) {
            $this->resetToken();
            return $this->queryFhir($url, $params, $resourceType, true);
        }

        if ($response->status() === 429) {
            return [
                'ihs_id' => null,
                'status' => 'error',
                'pesan'  => 'Rate limit SatuSehat tercapai. Coba lagi beberapa menit.',
            ];
        }

        if (! $response->successful()) {
            return [
                'ihs_id' => null,
                'status' => 'error',
                'pesan'  => "Server SatuSehat error (HTTP {$response->status()}).",
            ];
        }

        return $this->parseFhirBundle($response->json(), $resourceType);
    }

    /**
     * Parse FHIR Bundle response.
     *
     * @return array{ihs_id:?string, status:string, pesan:string}
     */
    private function parseFhirBundle(array $data, string $resourceType): array
    {
        $total = $data['total'] ?? 0;

        if ($total < 1 || empty($data['entry'])) {
            return [
                'ihs_id' => null,
                'status' => 'tidak_ditemukan',
                'pesan'  => "Identitas tidak ditemukan di SatuSehat ({$resourceType}).",
            ];
        }

        $ihsId = $data['entry'][0]['resource']['id'] ?? null;

        if (! $ihsId) {
            return [
                'ihs_id' => null,
                'status' => 'error',
                'pesan'  => 'Response SatuSehat tidak mengandung ID.',
            ];
        }

        return [
            'ihs_id' => $ihsId,
            'status' => 'ditemukan',
            'pesan'  => "IHS ID ditemukan: {$ihsId}",
        ];
    }
}

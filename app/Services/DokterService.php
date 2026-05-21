<?php

namespace App\Services;

use App\Models\Dokter;
use App\Models\DokterPoli;
use App\Models\JadwalPraktek;
use App\Repositories\DokterRepository;
use Illuminate\Validation\ValidationException;

class DokterService
{
    public function __construct(
        private readonly DokterRepository $repo
    ) {}

    // ── Profil ────────────────────────────────────────────────

    public function updateProfil(int $dokterId, array $data): Dokter
    {
        if (! empty($data['nik'])) {
            $dup = Dokter::where('nik', $data['nik'])
                         ->where('id', '!=', $dokterId)->first();
            if ($dup) {
                throw ValidationException::withMessages([
                    'nik' => "NIK sudah digunakan dokter lain: {$dup->user->nama}.",
                ]);
            }
        }

        if (! empty($data['no_sip'])) {
            $dup = Dokter::where('no_sip', $data['no_sip'])
                         ->where('id', '!=', $dokterId)->first();
            if ($dup) {
                throw ValidationException::withMessages([
                    'no_sip' => "Nomor SIP sudah digunakan dokter lain: {$dup->user->nama}.",
                ]);
            }
        }

        $dokter = $this->repo->updateProfil($dokterId, $data);

        activity('dokter')
            ->performedOn($dokter)
            ->causedBy(auth()->user())
            ->log('Profil dokter diupdate');

        return $dokter;
    }

    // ── Mapping Poli ──────────────────────────────────────────

    public function addPoliMapping(int $dokterId, int $poliId): DokterPoli
    {
        $mapping = $this->repo->upsertMappingPoli($dokterId, $poliId);

        activity('dokter')
            ->causedBy(auth()->user())
            ->log("Mapping poli ditambahkan: dokter #{$dokterId} → poli #{$poliId}");

        return $mapping;
    }

    public function removePoliMapping(int $dokterId, int $poliId): void
    {
        $mapping = DokterPoli::where('dokter_id', $dokterId)
                             ->where('poli_id', $poliId)
                             ->where('is_aktif', true)
                             ->with('jadwalPraktek')
                             ->first();

        if ($mapping) {
            $jadwalAktif = $mapping->jadwalPraktek->where('is_aktif', true)->count();
            if ($jadwalAktif > 0) {
                throw ValidationException::withMessages([
                    'poli_id' => "Tidak bisa hapus mapping — masih ada {$jadwalAktif} jadwal aktif. Nonaktifkan jadwal terlebih dahulu.",
                ]);
            }
        }

        $this->repo->removeMappingPoli($dokterId, $poliId);
    }

    // ── Sharing Fee ───────────────────────────────────────────

    public function saveSharingFee(int $dokterId, array $fees): void
    {
        foreach ($fees as $kategori => $persentase) {
            if ($persentase < 0 || $persentase > 100) {
                throw ValidationException::withMessages([
                    "fee_{$kategori}" => "Persentase {$kategori} harus antara 0 dan 100.",
                ]);
            }
        }

        $this->repo->upsertSharingFee($dokterId, $fees);

        activity('dokter')
            ->causedBy(auth()->user())
            ->log("Sharing fee dokter #{$dokterId} diupdate");
    }

    public function hitungSharingFee(int $dokterId, array $items): array
    {
        return $this->repo->hitungSharingFee($dokterId, $items);
    }

    // ── Jadwal Praktek ────────────────────────────────────────

    public function createJadwal(array $data): JadwalPraktek
    {
        if (JadwalPraktek::hasOverlap(
            $data['dokter_poli_id'],
            $data['hari'],
            $data['jam_mulai'],
            $data['jam_selesai']
        )) {
            throw ValidationException::withMessages([
                'jam_mulai' => "Jadwal hari {$data['hari']} pukul {$data['jam_mulai']}–{$data['jam_selesai']} bertabrakan dengan jadwal yang sudah ada.",
            ]);
        }

        return JadwalPraktek::create($data);
    }

    public function updateJadwal(int $id, array $data): JadwalPraktek
    {
        $jadwal = JadwalPraktek::findOrFail($id);

        if (JadwalPraktek::hasOverlap(
            $data['dokter_poli_id'] ?? $jadwal->dokter_poli_id,
            $data['hari']           ?? $jadwal->hari,
            $data['jam_mulai']      ?? $jadwal->jam_mulai,
            $data['jam_selesai']    ?? $jadwal->jam_selesai,
            $id
        )) {
            throw ValidationException::withMessages([
                'jam_mulai' => 'Jadwal bertabrakan dengan jadwal lain di hari yang sama.',
            ]);
        }

        $jadwal->update($data);
        return $jadwal;
    }

    public function toggleJadwal(int $id): JadwalPraktek
    {
        $jadwal = JadwalPraktek::findOrFail($id);
        $jadwal->update(['is_aktif' => ! $jadwal->is_aktif]);
        return $jadwal;
    }

    public function deleteJadwal(int $id): void
    {
        JadwalPraktek::findOrFail($id)->delete();
    }
}

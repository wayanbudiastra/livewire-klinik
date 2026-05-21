<?php

namespace App\Services;

use App\Models\Pasien;
use App\Repositories\PasienRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PasienService
{
    public function __construct(
        private readonly PasienRepository $repo
    ) {}

    public function paginate(array $filters, int $perPage = 10): LengthAwarePaginator
    {
        return $this->repo->paginate($filters, $perPage);
    }

    public function generateNomorRM(): string
    {
        return DB::transaction(function () {
            $last = Pasien::lockForUpdate()
                ->orderByDesc('nomor_rm')
                ->value('nomor_rm');

            $nextNum = 1;
            if ($last && preg_match('/^RM-(\d{6})$/', $last, $m)) {
                $nextNum = (int) $m[1] + 1;
            }

            if ($nextNum > 999_999) {
                throw new \RuntimeException('Nomor RM mencapai batas RM-999999.');
            }

            $nomor = 'RM-' . str_pad($nextNum, 6, '0', STR_PAD_LEFT);

            // Safeguard duplikat race condition
            if (Pasien::where('nomor_rm', $nomor)->exists()) {
                $nomor = 'RM-' . str_pad($nextNum + 1, 6, '0', STR_PAD_LEFT);
            }

            return $nomor;
        });
    }

    public function create(array $data, array $kontakList = []): Pasien
    {
        // Cek NIK duplikat
        if (($data['tipe_pasien'] ?? '') === 'WNI' && ! empty($data['nik'])) {
            $dup = Pasien::where('nik', $data['nik'])->first();
            if ($dup) {
                throw ValidationException::withMessages([
                    'nik' => "NIK sudah terdaftar atas nama {$dup->nama} ({$dup->nomor_rm}).",
                ]);
            }
        }

        // Cek No. Paspor duplikat
        if (($data['tipe_pasien'] ?? '') === 'WNA' && ! empty($data['no_paspor'])) {
            $noPaspor = strtoupper($data['no_paspor']);
            $dup = Pasien::where('no_paspor', $noPaspor)->first();
            if ($dup) {
                throw ValidationException::withMessages([
                    'no_paspor' => "No. Paspor sudah terdaftar atas nama {$dup->nama} ({$dup->nomor_rm}).",
                ]);
            }
            $data['no_paspor'] = $noPaspor;
        }

        $data['nomor_rm'] = $this->generateNomorRM();

        $pasien = $this->repo->create($data, $kontakList);

        activity('pasien')
            ->performedOn($pasien)
            ->causedBy(auth()->user())
            ->withProperties(['nomor_rm' => $pasien->nomor_rm])
            ->log('Pasien baru didaftarkan');

        return $pasien;
    }

    public function update(int $id, array $data, ?array $kontakList = null): Pasien
    {
        $existing = Pasien::findOrFail($id);

        if (! empty($data['nik']) && $data['nik'] !== $existing->nik) {
            $dup = Pasien::where('nik', $data['nik'])->where('id', '!=', $id)->first();
            if ($dup) {
                throw ValidationException::withMessages([
                    'nik' => "NIK sudah digunakan pasien lain ({$dup->nomor_rm}).",
                ]);
            }
        }

        $pasien = $this->repo->update($id, $data, $kontakList);

        activity('pasien')
            ->performedOn($pasien)
            ->causedBy(auth()->user())
            ->log('Data pasien diupdate');

        return $pasien;
    }

    public function toggleActive(int $id, bool $state): Pasien
    {
        $pasien = $this->repo->toggleActive($id, $state);

        activity('pasien')
            ->performedOn($pasien)
            ->causedBy(auth()->user())
            ->log($state ? 'Pasien diaktifkan' : 'Pasien dinonaktifkan');

        return $pasien;
    }
}

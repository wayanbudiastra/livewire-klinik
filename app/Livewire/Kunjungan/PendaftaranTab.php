<?php

namespace App\Livewire\Kunjungan;

use App\Models\Appointment;
use App\Models\Dokter;
use App\Models\Pasien;
use App\Services\KunjunganService;
use Livewire\Attributes\Computed;
use Livewire\Component;

class PendaftaranTab extends Component
{
    public string $mode           = 'appointment'; // 'appointment' | 'walkin'

    // Mode appointment
    public string $kodeBooking    = '';
    public ?int   $appointmentId  = null; // simpan ID saja, bukan object
    public string $aptPasienNama  = '';
    public string $aptPasienRM    = '';
    public string $aptDokterNama  = '';
    public string $aptPoliNama    = '';
    public string $aptJadwal      = '';

    // Mode walk-in
    public string $searchPasien      = '';
    public ?int   $pasienId          = null;
    public string $namaPasien        = '';
    public string $filterSpesialisasi= '';
    public ?int   $dokterId          = null;
    public ?int   $poliId            = null;

    // Data bersama
    public string $keluhan        = '';
    public string $tipePembayaran = 'umum';

    // Hasil
    public bool   $showHasil      = false;
    public string $nomorAntrean   = '';
    public string $namaPasienHasil= '';

    // Auto-daftar flag (dari URL auto=1)
    public bool $autoDaftar = false;

    public function mount(): void
    {
        $this->tipePembayaran = 'umum';

        $kode = request()->query('kode');
        $auto = request()->query('auto');

        if ($kode) {
            $this->mode        = 'appointment';
            $this->kodeBooking = strtoupper($kode);
            $this->lookupAppointment(strtoupper($kode));
        }

        if ($kode && $auto === '1' && $this->appointmentId) {
            $this->autoDaftar = true;
        }
    }

    // ── Lookup appointment (simpan data flat, bukan object) ──────

    private function lookupAppointment(string $kode): void
    {
        $apt = Appointment::with([
            'pasien:id,nama,nomor_rm',
            'dokter.user:id,nama',
            'poli:id,nama',
            'jadwalPraktek:id,jam_mulai,jam_selesai,hari',
        ])->where('kode_booking', $kode)
          ->where('status', 'booked')
          ->first();

        if (! $apt) {
            $this->appointmentId = null;
            return;
        }

        $this->appointmentId  = $apt->id;
        $this->pasienId       = $apt->pasien_id;
        $this->namaPasien     = $apt->pasien->nama;
        $this->dokterId       = $apt->dokter_id;
        $this->poliId         = $apt->poli_id;
        $this->keluhan        = $apt->keluhan ?? '';

        // Simpan info display
        $this->aptPasienNama  = $apt->pasien->nama;
        $this->aptPasienRM    = $apt->pasien->nomor_rm;
        $this->aptDokterNama  = $apt->dokter->user->nama ?? '-';
        $this->aptPoliNama    = $apt->poli->nama ?? '-';
        $this->aptJadwal      = $apt->jadwalPraktek
            ? ucfirst($apt->jadwalPraktek->hari).' '.substr($apt->jadwalPraktek->jam_mulai,0,5).'–'.substr($apt->jadwalPraktek->jam_selesai,0,5)
            : '';
    }

    // Apakah data appointment sudah ditemukan
    public function getHasAppointmentAttribute(): bool
    {
        return $this->appointmentId !== null;
    }

    public function updatingSearchPasien(): void
    {
        $this->pasienId   = null;
        $this->namaPasien = '';
    }

    // ── Cari Appointment ────────────────────────────────────────

    public function cariAppointment(): void
    {
        $this->validate(['kodeBooking' => 'required|string']);

        $this->lookupAppointment(strtoupper($this->kodeBooking));

        if (! $this->appointmentId) {
            $this->addError('kodeBooking', 'Kode booking tidak ditemukan atau sudah digunakan.');
        } else {
            $this->resetValidation('kodeBooking');
        }
    }

    // ── Auto-daftar (dipanggil via wire:init dari blade) ────────

    public function prosesAutoDaftar(KunjunganService $service): void
    {
        if (! $this->autoDaftar || ! $this->appointmentId) return;

        $this->autoDaftar = false;

        try {
            $kunjungan = $service->daftarKunjungan([
                'pasien_id'       => $this->pasienId,
                'dokter_id'       => $this->dokterId,
                'poli_id'         => $this->poliId,
                'keluhan'         => $this->keluhan ?: null,
                'tipe_pembayaran' => 'umum',
            ], $this->appointmentId);

            $this->nomorAntrean    = $kunjungan->nomor_antrean;
            $this->namaPasienHasil = $kunjungan->pasien->nama ?? $this->namaPasien;
            $this->showHasil       = true;

            $this->dispatch('kunjungan-created');
            $this->dispatch('notify', type: 'success',
                message: "Pasien terdaftar! No. Antrean: {$kunjungan->nomor_antrean}");

        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->dispatch('notify', type: 'error',
                message: $e->errors()[array_key_first($e->errors())][0]);
        } catch (\Exception $e) {
            $this->dispatch('notify', type: 'error',
                message: 'Gagal mendaftarkan: ' . $e->getMessage());
        }
    }

    // ── Pasien Suggestions (walk-in) ─────────────────────────────

    #[Computed]
    public function pasienSuggestions()
    {
        if (strlen($this->searchPasien) < 2) return collect();
        return Pasien::aktif()
            ->where(fn ($q) =>
                $q->where('nama', 'like', "%{$this->searchPasien}%")
                  ->orWhere('nomor_rm', 'like', "%{$this->searchPasien}%")
                  ->orWhere('nik', 'like', "%{$this->searchPasien}%"))
            ->select('id', 'nomor_rm', 'nama', 'telepon', 'tanggal_lahir', 'alamat', 'tipe_pasien', 'nik')
            ->limit(10)->get();
    }

    public function pilihPasien(int $id, string $nama): void
    {
        $this->pasienId     = $id;
        $this->namaPasien   = $nama;
        $this->searchPasien = $nama;
    }

    // ── Dokter Tersedia (walk-in) ────────────────────────────────

    #[Computed]
    public function dokterList()
    {
        return app(KunjunganService::class)
            ->getDokterTersedia($this->filterSpesialisasi ?: null);
    }

    #[Computed]
    public function spesialisasiList(): array
    {
        return Dokter::whereNotNull('spesialisasi')->distinct()->pluck('spesialisasi')->toArray();
    }

    public function pilihDokter(int $dokterId, int $poliId): void
    {
        $this->dokterId = $dokterId;
        $this->poliId   = $poliId;
    }

    // ── Daftarkan (manual) ───────────────────────────────────────

    public function daftar(KunjunganService $service): void
    {
        if (! $this->pasienId) {
            $this->addError(
                $this->mode === 'appointment' ? 'kodeBooking' : 'searchPasien',
                $this->mode === 'appointment' ? 'Cari appointment terlebih dahulu.' : 'Pilih pasien terlebih dahulu.'
            );
            return;
        }

        if (! $this->dokterId || ! $this->poliId) {
            $this->addError('dokterId', 'Pilih dokter terlebih dahulu.');
            return;
        }

        $this->validate([
            'tipePembayaran' => 'required|in:umum,bpjs,asuransi',
            'keluhan'        => 'nullable|string|max:500',
        ]);

        try {
            $kunjungan = $service->daftarKunjungan([
                'pasien_id'       => $this->pasienId,
                'dokter_id'       => $this->dokterId,
                'poli_id'         => $this->poliId,
                'keluhan'         => $this->keluhan ?: null,
                'tipe_pembayaran' => $this->tipePembayaran,
            ], $this->mode === 'appointment' ? $this->appointmentId : null);

            $this->nomorAntrean    = $kunjungan->nomor_antrean;
            $this->namaPasienHasil = $kunjungan->pasien->nama ?? $this->namaPasien;
            $this->showHasil       = true;

            $this->dispatch('kunjungan-created');
            $this->dispatch('notify', type: 'success',
                message: "Pasien terdaftar! No. Antrean: {$kunjungan->nomor_antrean}");

        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->dispatch('notify', type: 'error',
                message: $e->errors()[array_key_first($e->errors())][0]);
        } catch (\Exception $e) {
            $this->dispatch('notify', type: 'error',
                message: 'Gagal: ' . $e->getMessage());
        }
    }

    public function resetForm(): void
    {
        $this->reset([
            'kodeBooking','appointmentId','aptPasienNama','aptPasienRM',
            'aptDokterNama','aptPoliNama','aptJadwal',
            'searchPasien','pasienId','namaPasien','dokterId','poliId',
            'keluhan','showHasil','nomorAntrean','namaPasienHasil','autoDaftar',
        ]);
        $this->tipePembayaran = 'umum';
    }

    public function render()
    {
        return view('livewire.kunjungan.pendaftaran-tab');
    }
}

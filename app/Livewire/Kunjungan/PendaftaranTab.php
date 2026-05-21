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
    public string $mode             = 'appointment'; // 'appointment' | 'walkin'

    // Mode appointment
    public string $kodeBooking      = '';
    public ?object $appointmentData = null;

    // Mode walk-in
    public string $searchPasien     = '';
    public ?int   $pasienId         = null;
    public string $namaPasien       = '';
    public string $filterSpesialisasi = '';
    public ?int   $dokterId         = null;
    public ?int   $poliId           = null;

    // Data bersama
    public string $keluhan          = '';
    public string $tipePembayaran   = 'umum';

    // Hasil
    public bool   $showHasil        = false;
    public string $nomorAntrean     = '';
    public string $namaPasienHasil  = '';

    public function mount(): void
    {
        $this->tipePembayaran = 'umum';

        // Auto-isi kode booking dari URL query: ?kode=BK-XXXXXXXX
        $kode = request()->query('kode');
        if ($kode) {
            $this->mode        = 'appointment';
            $this->kodeBooking = strtoupper($kode);
        }
    }

    public function updatingSearchPasien(): void
    {
        $this->pasienId   = null;
        $this->namaPasien = '';
    }

    // ── Cari Appointment ────────────────────────────────────

    public function cariAppointment(): void
    {
        $this->validate(['kodeBooking' => 'required|string']);

        $apt = Appointment::with(['pasien:id,nama,nomor_rm,telepon',
            'dokter.user:id,nama', 'poli:id,nama',
            'jadwalPraktek:id,jam_mulai,jam_selesai,hari'])
            ->where('kode_booking', strtoupper($this->kodeBooking))
            ->where('status', 'booked')
            ->first();

        if (! $apt) {
            $this->addError('kodeBooking', 'Kode booking tidak ditemukan atau sudah digunakan.');
            $this->appointmentData = null;
            return;
        }

        $this->appointmentData = $apt;
        $this->pasienId        = $apt->pasien_id;
        $this->namaPasien      = $apt->pasien->nama;
        $this->dokterId        = $apt->dokter_id;
        $this->poliId          = $apt->poli_id;
        $this->keluhan         = $apt->keluhan ?? '';
        $this->resetValidation('kodeBooking');
    }

    // ── Pasien Suggestions (walk-in) ─────────────────────────

    #[Computed]
    public function pasienSuggestions()
    {
        if (strlen($this->searchPasien) < 2) return collect();
        return Pasien::aktif()
            ->where(fn ($q) =>
                $q->where('nama', 'like', "%{$this->searchPasien}%")
                  ->orWhere('nomor_rm', 'like', "%{$this->searchPasien}%"))
            ->select('id', 'nomor_rm', 'nama', 'telepon')
            ->limit(8)->get();
    }

    public function pilihPasien(int $id, string $nama): void
    {
        $this->pasienId     = $id;
        $this->namaPasien   = $nama;
        $this->searchPasien = $nama;
    }

    // ── Dokter Tersedia (walk-in) ────────────────────────────

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

    // ── Daftarkan ────────────────────────────────────────────

    public function daftar(KunjunganService $service): void
    {
        if (! $this->pasienId) {
            if ($this->mode === 'appointment') {
                $this->addError('kodeBooking', 'Cari appointment terlebih dahulu.');
            } else {
                $this->addError('searchPasien', 'Pilih pasien terlebih dahulu.');
            }
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

        $appointmentId = $this->mode === 'appointment' && $this->appointmentData
            ? $this->appointmentData->id : null;

        $kunjungan = $service->daftarKunjungan([
            'pasien_id'       => $this->pasienId,
            'dokter_id'       => $this->dokterId,
            'poli_id'         => $this->poliId,
            'keluhan'         => $this->keluhan ?: null,
            'tipe_pembayaran' => $this->tipePembayaran,
        ], $appointmentId);

        $this->nomorAntrean   = $kunjungan->nomor_antrean;
        $this->namaPasienHasil= $kunjungan->pasien->nama ?? $this->namaPasien;
        $this->showHasil      = true;

        $this->dispatch('kunjungan-created');
        $this->dispatch('notify', type: 'success',
            message: "Pasien terdaftar! No. Antrean: {$kunjungan->nomor_antrean}");
    }

    public function resetForm(): void
    {
        $this->reset([
            'kodeBooking','appointmentData','searchPasien',
            'pasienId','namaPasien','dokterId','poliId',
            'keluhan','tipePembayaran','showHasil',
            'nomorAntrean','namaPasienHasil',
        ]);
        $this->tipePembayaran = 'umum';
    }

    public function render()
    {
        return view('livewire.kunjungan.pendaftaran-tab');
    }
}

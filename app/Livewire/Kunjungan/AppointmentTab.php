<?php

namespace App\Livewire\Kunjungan;

use App\Models\Appointment;
use App\Models\Dokter;
use App\Models\DokterPoli;
use App\Models\JadwalPraktek;
use App\Models\Pasien;
use App\Services\KunjunganService;
use Livewire\Attributes\Computed;
use Livewire\Component;

class AppointmentTab extends Component
{
    // Filter dokter
    public string $filterSpesialisasi = '';
    public string $filterDokter       = '';
    public string $tanggalAppointment = '';

    // Jadwal yang dipilih
    public ?int $selectedJadwalId    = null;
    public ?int $selectedDokterId    = null;
    public ?int $selectedPoliId      = null;
    public string $sisaKuota         = '';

    // Data pasien
    public string $searchPasien      = '';
    public ?int   $pasienId          = null;
    public string $namaPasien        = '';
    public string $modePasien        = 'lama'; // 'lama' | 'baru'

    // Input pasien baru (lengkap)
    public string $namaInputBaru       = '';
    public string $nikInputBaru        = '';
    public string $hpInputBaru         = '';
    public string $tempatLahirBaru     = '';
    public string $tanggalLahirBaru    = '';
    public string $jenisKelaminBaru    = 'L';
    public string $tipePasienBaru      = 'WNI';
    public string $alamatBaru          = '';

    // Keluhan
    public string $keluhan           = '';

    // Hasil
    public ?string $kodeBooking      = null;
    public bool    $showHasil        = false;

    public function mount(): void
    {
        $this->tanggalAppointment = now()->toDateString();
    }

    public function updatingSearchPasien(): void
    {
        $this->pasienId   = null;
        $this->namaPasien = '';
    }

    #[Computed]
    public function dokterList()
    {
        return app(KunjunganService::class)
            ->getDokterTersedia(
                $this->filterSpesialisasi ?: null,
                $this->tanggalAppointment ?: null
            );
    }

    #[Computed]
    public function spesialisasiList(): array
    {
        return Dokter::whereNotNull('spesialisasi')
            ->distinct()
            ->pluck('spesialisasi')
            ->toArray();
    }

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
            ->limit(8)
            ->get();
    }

    public function pilihPasien(int $id, string $nama): void
    {
        $this->pasienId       = $id;
        $this->namaPasien     = $nama;
        $this->searchPasien   = $nama;
    }

    public function pilihJadwal(int $jadwalId, int $dokterId, int $poliId): void
    {
        $this->selectedJadwalId  = $jadwalId;
        $this->selectedDokterId  = $dokterId;
        $this->selectedPoliId    = $poliId;

        $sisa = app(KunjunganService::class)
            ->cekSisaKuota($jadwalId, $this->tanggalAppointment);
        $this->sisaKuota = (string) $sisa;
    }

    public function buatAppointment(KunjunganService $service): void
    {
        $this->validate([
            'selectedJadwalId'  => 'required|integer',
            'selectedDokterId'  => 'required|integer',
            'selectedPoliId'    => 'required|integer',
            'tanggalAppointment'=> 'required|date|after_or_equal:today',
            'keluhan'           => 'nullable|string|max:500',
        ], [
            'selectedJadwalId.required' => 'Pilih jadwal dokter terlebih dahulu.',
            'tanggalAppointment.after_or_equal' => 'Tanggal tidak boleh di masa lalu.',
        ]);

        // Pasien lama wajib dipilih, pasien baru wajib isi data
        if ($this->modePasien === 'lama' && ! $this->pasienId) {
            $this->addError('searchPasien', 'Pilih pasien dari daftar terlebih dahulu.');
            return;
        }
        if ($this->modePasien === 'baru') {
            $this->validate([
                'namaInputBaru'     => 'required|string|min:3',
                'tempatLahirBaru'   => 'required|string|min:2',
                'tanggalLahirBaru'  => 'required|date|before_or_equal:today',
                'alamatBaru'        => 'required|string|min:10',
                'hpInputBaru'       => 'required|string|regex:/^(\+62|62|0)[0-9]{8,13}$/',
            ], [
                'hpInputBaru.regex'         => 'Format HP tidak valid.',
                'tempatLahirBaru.required'  => 'Tempat lahir wajib diisi.',
                'tanggalLahirBaru.required' => 'Tanggal lahir wajib diisi.',
                'alamatBaru.min'            => 'Alamat minimal 10 karakter.',
            ]);
        }

        // Untuk pasien baru: buat pasien lengkap
        $pasienId = $this->pasienId;
        if ($this->modePasien === 'baru') {
            $service2 = app(\App\Services\PasienService::class);
            $pasienBaru = $service2->create([
                'nama'           => $this->namaInputBaru,
                'tempat_lahir'   => $this->tempatLahirBaru,
                'tanggal_lahir'  => $this->tanggalLahirBaru,
                'jenis_kelamin'  => $this->jenisKelaminBaru,
                'tipe_pasien'    => $this->tipePasienBaru,
                'nik'            => $this->nikInputBaru ?: null,
                'alamat'         => $this->alamatBaru,
                'telepon'        => $this->hpInputBaru,
                'is_active'      => true,
            ]);
            $pasienId = $pasienBaru->id;
        }

        $appointment = $service->buatAppointment([
            'pasien_id'          => $pasienId,
            'dokter_id'          => $this->selectedDokterId,
            'poli_id'            => $this->selectedPoliId,
            'jadwal_praktek_id'  => $this->selectedJadwalId,
            'tanggal_appointment'=> $this->tanggalAppointment,
            'keluhan'            => $this->keluhan ?: null,
        ]);

        $this->kodeBooking = $appointment->kode_booking;
        $this->showHasil   = true;

        $this->dispatch('appointment-created');
        $this->dispatch('notify', type: 'success',
            message: "Appointment berhasil! Kode: {$appointment->kode_booking}");
    }

    // ── List Appointment ─────────────────────────────────────

    #[Computed]
    public function appointmentList()
    {
        return \App\Models\Appointment::with([
            'pasien:id,nama,nomor_rm',
            'dokter.user:id,nama',
            'poli:id,nama',
        ])
        ->whereDate('tanggal_appointment', $this->tanggalAppointment ?: now()->toDateString())
        ->whereIn('status', ['booked', 'checked_in'])
        ->orderBy('created_at', 'desc')
        ->get();
    }

    public function resetForm(): void
    {
        $this->reset([
            'selectedJadwalId','selectedDokterId','selectedPoliId',
            'sisaKuota','searchPasien','pasienId','namaPasien',
            'namaInputBaru','nikInputBaru','hpInputBaru',
            'tempatLahirBaru','tanggalLahirBaru','jenisKelaminBaru',
            'tipePasienBaru','alamatBaru',
            'keluhan','kodeBooking','showHasil',
        ]);
        unset($this->appointmentList);
    }

    public function render()
    {
        return view('livewire.kunjungan.appointment-tab');
    }
}

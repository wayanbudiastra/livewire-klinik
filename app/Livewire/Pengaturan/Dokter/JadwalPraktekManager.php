<?php

namespace App\Livewire\Pengaturan\Dokter;

use App\Models\Dokter;
use App\Models\JadwalPraktek;
use App\Services\DokterService;
use Livewire\Attributes\Computed;
use Livewire\Component;

class JadwalPraktekManager extends Component
{
    public int  $dokterId    = 0;
    public bool $showForm    = false;
    public ?int $jadwalEditId = null;

    public int    $dokter_poli_id = 0;
    public string $hari           = '';
    public string $jam_mulai      = '';
    public string $jam_selesai    = '';
    public int    $kuota_pasien   = 20;
    public string $keterangan     = '';

    public function mount(int $dokterId): void
    {
        $this->dokterId = $dokterId;
    }

    #[Computed]
    public function dokterPoliList()
    {
        return Dokter::findOrFail($this->dokterId)
            ->dokterPoli()
            ->where('is_aktif', true)
            ->with('poli:id,nama,kode')
            ->get();
    }

    #[Computed]
    public function jadwalPerPoli()
    {
        return $this->dokterPoliList->mapWithKeys(fn ($dp) => [
            $dp->id => [
                'poli'   => $dp->poli,
                'jadwal' => $dp->jadwalPraktek,
            ],
        ]);
    }

    public function openCreate(int $dokterPoliId = 0): void
    {
        $this->authorize('masterdata.edit');
        $this->reset(['jadwalEditId', 'hari', 'jam_mulai', 'jam_selesai', 'keterangan']);
        $this->dokter_poli_id = $dokterPoliId;
        $this->kuota_pasien   = 20;
        $this->showForm       = true;
        $this->resetValidation();
    }

    public function openEdit(int $jadwalId): void
    {
        $this->authorize('masterdata.edit');
        $jadwal = JadwalPraktek::findOrFail($jadwalId);
        $this->jadwalEditId   = $jadwalId;
        $this->dokter_poli_id = $jadwal->dokter_poli_id;
        $this->hari           = $jadwal->hari;
        $this->jam_mulai      = $jadwal->jam_mulai;
        $this->jam_selesai    = $jadwal->jam_selesai;
        $this->kuota_pasien   = $jadwal->kuota_pasien;
        $this->keterangan     = $jadwal->keterangan ?? '';
        $this->showForm       = true;
        $this->resetValidation();
    }

    public function save(DokterService $service): void
    {
        $this->validate([
            'dokter_poli_id' => 'required|integer|exists:dokter_poli,id',
            'hari'           => 'required|in:senin,selasa,rabu,kamis,jumat,sabtu,minggu',
            'jam_mulai'      => 'required|date_format:H:i',
            'jam_selesai'    => 'required|date_format:H:i|after:jam_mulai',
            'kuota_pasien'   => 'required|integer|min:1|max:200',
        ], [
            'jam_selesai.after' => 'Jam selesai harus setelah jam mulai.',
        ]);

        $data = [
            'dokter_poli_id' => $this->dokter_poli_id,
            'hari'           => $this->hari,
            'jam_mulai'      => $this->jam_mulai,
            'jam_selesai'    => $this->jam_selesai,
            'kuota_pasien'   => $this->kuota_pasien,
            'keterangan'     => $this->keterangan ?: null,
        ];

        $this->jadwalEditId
            ? $service->updateJadwal($this->jadwalEditId, $data)
            : $service->createJadwal($data);

        $this->showForm = false;
        unset($this->jadwalPerPoli);
        $msg = $this->jadwalEditId ? 'Jadwal diupdate.' : 'Jadwal ditambahkan.';
        $this->dispatch('notify', type: 'success', message: $msg);
    }

    public function toggle(int $jadwalId, DokterService $service): void
    {
        $this->authorize('masterdata.edit');
        $service->toggleJadwal($jadwalId);
        unset($this->jadwalPerPoli);
        $this->dispatch('notify', type: 'success', message: 'Status jadwal diupdate.');
    }

    public function delete(int $jadwalId, DokterService $service): void
    {
        $this->authorize('masterdata.edit');
        $service->deleteJadwal($jadwalId);
        unset($this->jadwalPerPoli);
        $this->dispatch('notify', type: 'success', message: 'Jadwal dihapus.');
    }

    public function getHariOptionsProperty(): array
    {
        return JadwalPraktek::getHariOptions();
    }

    public function render()
    {
        return view('livewire.pengaturan.dokter.jadwal-praktek-manager');
    }
}

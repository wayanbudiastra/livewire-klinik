<?php

namespace App\Livewire\Pengaturan\Dokter;

use App\Models\Dokter;
use App\Services\DokterService;
use Illuminate\Validation\Rule;
use Livewire\Component;

class DokterProfilForm extends Component
{
    public bool   $showModal = false;
    public ?int   $dokterId  = null;
    public string $namaUser  = '';

    public string $nik             = '';
    public string $no_sip          = '';
    public string $tgl_expired_sip = '';
    public string $spesialisasi    = '';

    public function getRules(): array
    {
        return [
            'nik'             => ['nullable', 'string', 'size:16', 'regex:/^\d{16}$/',
                                  Rule::unique('dokter', 'nik')->ignore($this->dokterId)],
            'no_sip'          => ['nullable', 'string', 'min:5',
                                  Rule::unique('dokter', 'no_sip')->ignore($this->dokterId)],
            'tgl_expired_sip' => ['nullable', 'date'],
            'spesialisasi'    => ['nullable', 'string', 'max:100'],
        ];
    }

    public function getMessages(): array
    {
        return [
            'nik.size'      => 'NIK harus tepat 16 digit.',
            'nik.regex'     => 'NIK hanya boleh berisi angka.',
            'nik.unique'    => 'NIK sudah digunakan dokter lain.',
            'no_sip.unique' => 'Nomor SIP sudah digunakan.',
        ];
    }

    public function open(int $dokterId): void
    {
        $this->authorize('masterdata.edit');
        $dokter = Dokter::with('user')->findOrFail($dokterId);
        $this->dokterId        = $dokterId;
        $this->namaUser        = $dokter->user->nama;
        $this->nik             = $dokter->nik             ?? '';
        $this->no_sip          = $dokter->no_sip          ?? '';
        $this->tgl_expired_sip = $dokter->tgl_expired_sip
            ? $dokter->tgl_expired_sip->format('Y-m-d') : '';
        $this->spesialisasi    = $dokter->spesialisasi    ?? '';
        $this->showModal       = true;
        $this->resetValidation();
    }

    public function save(DokterService $service): void
    {
        $this->validate($this->getRules(), $this->getMessages());

        $service->updateProfil($this->dokterId, [
            'nik'             => $this->nik             ?: null,
            'no_sip'          => $this->no_sip           ?: null,
            'tgl_expired_sip' => $this->tgl_expired_sip ?: null,
            'spesialisasi'    => $this->spesialisasi     ?: null,
        ]);

        $this->showModal = false;
        $this->dispatch('dokter-saved');
        $this->dispatch('notify', type: 'success', message: 'Profil dokter berhasil disimpan.');
    }

    public function render()
    {
        return view('livewire.pengaturan.dokter.dokter-profil-form');
    }
}

<?php

namespace App\Livewire\Pengaturan\Masterdata;

use App\Models\PeralatanMedis;
use App\Services\MasterdataService;
use Illuminate\Validation\Rule;
use Livewire\Component;

class PeralatanForm extends Component
{
    public bool   $showModal   = false;
    public ?int   $peralatanId = null;
    public bool   $isEdit      = false;

    public string $kode       = '';
    public string $nama       = '';
    public string $merk       = '';
    public string $nomor_seri = '';
    public string $deskripsi  = '';

    public function getRules(): array
    {
        $uniqueKode = $this->isEdit
            ? Rule::unique('peralatan_medis', 'kode')->ignore($this->peralatanId)
            : 'unique:peralatan_medis,kode';
        $uniqueSeri = $this->isEdit
            ? Rule::unique('peralatan_medis', 'nomor_seri')->ignore($this->peralatanId)
            : 'nullable|string|unique:peralatan_medis,nomor_seri';

        return [
            'kode'       => ['required', 'string', $uniqueKode],
            'nama'       => ['required', 'string', 'min:3'],
            'merk'       => ['nullable', 'string'],
            'nomor_seri' => $this->isEdit
                ? ['nullable', 'string', $uniqueSeri]
                : ['nullable', 'string', 'unique:peralatan_medis,nomor_seri'],
            'deskripsi'  => ['nullable', 'string'],
        ];
    }

    public function openCreate(): void
    {
        $this->authorize('masterdata.create');
        $this->reset(['peralatanId','kode','nama','merk','nomor_seri','deskripsi']);
        $this->isEdit    = false;
        $this->showModal = true;
        $this->resetValidation();
    }

    public function openEdit(int $id): void
    {
        $this->authorize('masterdata.edit');
        $item = PeralatanMedis::findOrFail($id);
        $this->peralatanId = $id;
        $this->kode        = $item->kode;
        $this->nama        = $item->nama;
        $this->merk        = $item->merk       ?? '';
        $this->nomor_seri  = $item->nomor_seri ?? '';
        $this->deskripsi   = $item->deskripsi  ?? '';
        $this->isEdit      = true;
        $this->showModal   = true;
        $this->resetValidation();
    }

    public function save(MasterdataService $service): void
    {
        $this->validate($this->getRules());

        $data = [
            'kode'       => strtoupper($this->kode),
            'nama'       => $this->nama,
            'merk'       => $this->merk       ?: null,
            'nomor_seri' => $this->nomor_seri ?: null,
            'deskripsi'  => $this->deskripsi  ?: null,
        ];

        $this->isEdit
            ? $service->updatePeralatan($this->peralatanId, $data)
            : $service->createPeralatan($data);

        $this->showModal = false;
        $this->dispatch('peralatan-saved');
        $msg = $this->isEdit ? 'Peralatan diupdate.' : 'Peralatan ditambahkan.';
        $this->dispatch('notify', type: 'success', message: $msg);
    }

    public function render()
    {
        return view('livewire.pengaturan.masterdata.peralatan-form');
    }
}

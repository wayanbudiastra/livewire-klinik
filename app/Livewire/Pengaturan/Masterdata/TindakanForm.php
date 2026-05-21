<?php

namespace App\Livewire\Pengaturan\Masterdata;

use App\Models\MasterTindakan;
use App\Models\Poli;
use App\Services\MasterdataService;
use Illuminate\Validation\Rule;
use Livewire\Component;

class TindakanForm extends Component
{
    public bool  $showModal   = false;
    public ?int  $tindakanId  = null;
    public bool  $isEdit      = false;

    public string $kode       = '';
    public string $nama       = '';
    public string $deskripsi  = '';
    public string $tarif      = '';
    public string $tarif_bpjs = '';
    public bool   $is_active  = true;
    public array  $poli_ids   = [];

    public function getRules(): array
    {
        $uniqueKode = $this->isEdit
            ? Rule::unique('master_tindakan', 'kode')->ignore($this->tindakanId)
            : 'unique:master_tindakan,kode';

        return [
            'kode'       => ['required', 'string', 'min:2', $uniqueKode],
            'nama'       => ['required', 'string', 'min:3'],
            'deskripsi'  => ['nullable', 'string'],
            'tarif'      => ['required', 'numeric', 'min:0'],
            'tarif_bpjs' => ['nullable', 'numeric', 'min:0'],
            'poli_ids'   => ['required', 'array', 'min:1'],
            'poli_ids.*' => ['integer', 'exists:poli,id'],
            'is_active'  => ['boolean'],
        ];
    }

    public function getMessages(): array
    {
        return [
            'poli_ids.required' => 'Pilih minimal satu Poli.',
            'poli_ids.min'      => 'Pilih minimal satu Poli.',
            'kode.unique'       => 'Kode sudah digunakan.',
        ];
    }

    public function openCreate(): void
    {
        $this->authorize('masterdata.create');
        $this->reset(['tindakanId','kode','nama','deskripsi','tarif','tarif_bpjs','poli_ids']);
        $this->is_active = true;
        $this->isEdit    = false;
        $this->showModal = true;
        $this->resetValidation();
    }

    public function openEdit(int $id): void
    {
        $this->authorize('masterdata.edit');
        $item = MasterTindakan::with('poli')->findOrFail($id);
        $this->tindakanId  = $id;
        $this->kode        = $item->kode;
        $this->nama        = $item->nama;
        $this->deskripsi   = $item->deskripsi ?? '';
        $this->tarif       = (string) $item->tarif;
        $this->tarif_bpjs  = $item->tarif_bpjs ? (string) $item->tarif_bpjs : '';
        $this->is_active   = $item->is_active;
        $this->poli_ids    = $item->poli->pluck('id')->toArray();
        $this->isEdit      = true;
        $this->showModal   = true;
        $this->resetValidation();
    }

    public function save(MasterdataService $service): void
    {
        $this->validate($this->getRules(), $this->getMessages());

        $data = [
            'kode'       => strtoupper($this->kode),
            'nama'       => $this->nama,
            'deskripsi'  => $this->deskripsi ?: null,
            'tarif'      => (float) $this->tarif,
            'tarif_bpjs' => $this->tarif_bpjs ? (float) $this->tarif_bpjs : null,
            'is_active'  => $this->is_active,
        ];

        if ($this->isEdit) {
            $service->updateTindakan($this->tindakanId, $data, $this->poli_ids);
            $msg = 'Tindakan berhasil diupdate.';
        } else {
            $service->createTindakan($data, $this->poli_ids);
            $msg = 'Tindakan baru berhasil ditambahkan.';
        }

        $this->showModal = false;
        $this->dispatch('tindakan-saved');
        $this->dispatch('notify', ['type' => 'success', 'message' => $msg]);
    }

    public function getPoliListProperty()
    {
        return Poli::aktif()->orderBy('nama')->get(['id', 'nama', 'kode']);
    }

    public function render()
    {
        return view('livewire.pengaturan.masterdata.tindakan-form');
    }
}

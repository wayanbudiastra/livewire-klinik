<?php

namespace App\Livewire\Pengaturan\Masterdata;

use App\Models\ItemPenunjang;
use App\Services\MasterdataService;
use Illuminate\Validation\Rule;
use Livewire\Component;

class PenunjangForm extends Component
{
    public bool   $showModal   = false;
    public ?int   $penunjangId = null;
    public bool   $isEdit      = false;
    public string $defaultKategori = 'lab';

    public string $kode         = '';
    public string $nama         = '';
    public string $kategori     = 'lab';
    public string $tarif        = '';
    public string $tarif_bpjs   = '';
    public string $deskripsi    = '';
    public string $satuan_waktu = '';
    public bool   $is_active    = true;

    public function getRules(): array
    {
        $uniqueKode = $this->isEdit
            ? Rule::unique('item_penunjang', 'kode')->ignore($this->penunjangId)
            : 'unique:item_penunjang,kode';

        return [
            'kode'         => ['required', 'string', 'min:2', $uniqueKode],
            'nama'         => ['required', 'string', 'min:3'],
            'kategori'     => ['required', 'in:lab,radiologi'],
            'tarif'        => ['required', 'numeric', 'min:0'],
            'tarif_bpjs'   => ['nullable', 'numeric', 'min:0'],
            'deskripsi'    => ['nullable', 'string'],
            'satuan_waktu' => ['nullable', 'string'],
        ];
    }

    public function openCreate(string $kategori = 'lab'): void
    {
        $this->authorize('masterdata.create');
        $this->reset(['penunjangId','kode','nama','tarif','tarif_bpjs','deskripsi','satuan_waktu']);
        $this->kategori  = $kategori;
        $this->is_active = true;
        $this->isEdit    = false;
        $this->showModal = true;
        $this->resetValidation();
    }

    public function openEdit(int $id): void
    {
        $this->authorize('masterdata.edit');
        $item = ItemPenunjang::findOrFail($id);
        $this->penunjangId  = $id;
        $this->kode         = $item->kode;
        $this->nama         = $item->nama;
        $this->kategori     = $item->kategori;
        $this->tarif        = (string) $item->tarif;
        $this->tarif_bpjs   = $item->tarif_bpjs ? (string) $item->tarif_bpjs : '';
        $this->deskripsi    = $item->deskripsi ?? '';
        $this->satuan_waktu = $item->satuan_waktu ?? '';
        $this->is_active    = $item->is_active;
        $this->isEdit       = true;
        $this->showModal    = true;
        $this->resetValidation();
    }

    public function save(MasterdataService $service): void
    {
        $this->validate($this->getRules());

        $data = [
            'kode'         => strtoupper($this->kode),
            'nama'         => $this->nama,
            'kategori'     => $this->kategori,
            'tarif'        => (float) $this->tarif,
            'tarif_bpjs'   => $this->tarif_bpjs ? (float) $this->tarif_bpjs : null,
            'deskripsi'    => $this->deskripsi    ?: null,
            'satuan_waktu' => $this->satuan_waktu ?: null,
            'is_active'    => $this->is_active,
        ];

        $this->isEdit
            ? $service->updatePenunjang($this->penunjangId, $data)
            : $service->createPenunjang($data);

        $this->showModal = false;
        $this->dispatch('penunjang-saved');
        $this->dispatch('notify', ['type' => 'success',
            'message' => $this->isEdit ? 'Item penunjang diupdate.' : 'Item penunjang ditambahkan.']);
    }

    public function render()
    {
        return view('livewire.pengaturan.masterdata.penunjang-form');
    }
}

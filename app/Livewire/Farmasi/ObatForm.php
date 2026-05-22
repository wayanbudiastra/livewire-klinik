<?php

namespace App\Livewire\Farmasi;

use App\Models\Obat;
use App\Models\Satuan;
use App\Services\FarmasiService;
use Illuminate\Validation\Rule;
use Livewire\Component;

class ObatForm extends Component
{
    public bool   $showModal  = false;
    public ?int   $obatId     = null;
    public bool   $isEdit     = false;

    public string $kode            = '';
    public string $barcode         = '';
    public string $nama            = '';
    public string $generik         = '';
    public string $jenis_barang    = 'obat';
    public bool   $is_paten        = false;
    public string $satuan          = '';
    public ?int   $satuan_besar_id = null;
    public ?int   $satuan_kecil_id = null;
    public int    $konversi        = 1;
    public string $stok            = '0';
    public string $harga           = '';
    public string $harga_beli      = '';
    public string $harga_bpjs      = '';
    public string $kategori        = '';
    public string $expired_date    = '';
    public bool   $is_active       = true;

    public function getRules(): array
    {
        $uniqueKode = $this->isEdit
            ? Rule::unique('obat', 'kode')->ignore($this->obatId)
            : 'unique:obat,kode';

        return [
            'kode'           => ['required', 'string', $uniqueKode],
            'barcode'        => ['nullable', 'string'],
            'nama'           => ['required', 'string', 'min:3'],
            'generik'        => ['nullable', 'string'],
            'jenis_barang'   => ['required', 'in:obat,alkes'],
            'satuan'         => ['required', 'string'],
            'satuan_besar_id'=> ['nullable', 'integer', 'exists:satuan,id'],
            'satuan_kecil_id'=> ['nullable', 'integer', 'exists:satuan,id'],
            'konversi'       => ['required', 'integer', 'min:1'],
            'stok'           => ['required', 'integer', 'min:0'],
            'harga'          => ['required', 'numeric', 'min:0'],
            'harga_beli'     => ['nullable', 'numeric', 'min:0'],
            'harga_bpjs'     => ['nullable', 'numeric', 'min:0'],
            'kategori'       => ['nullable', 'string'],
            'expired_date'   => ['nullable', 'date'],
        ];
    }

    public function getMessages(): array
    {
        return [
            'kode.unique' => 'Kode obat sudah digunakan.',
            'nama.min'    => 'Nama minimal 3 karakter.',
        ];
    }

    public function openCreate(): void
    {
        $this->authorize('obat.create');
        $this->reset(['obatId','kode','barcode','nama','generik','harga','harga_beli',
                      'harga_bpjs','kategori','expired_date','satuan_besar_id','satuan_kecil_id']);
        $this->jenis_barang = 'obat';
        $this->is_paten     = false;
        $this->konversi     = 1;
        $this->stok         = '0';
        $this->is_active    = true;
        $this->isEdit       = false;
        $this->showModal    = true;
        $this->resetValidation();
    }

    public function openEdit(int $id): void
    {
        $this->authorize('obat.edit');
        $o = Obat::findOrFail($id);
        $this->obatId          = $id;
        $this->kode            = $o->kode;
        $this->barcode         = $o->barcode         ?? '';
        $this->nama            = $o->nama;
        $this->generik         = $o->generik         ?? '';
        $this->jenis_barang    = $o->jenis_barang    ?? 'obat';
        $this->is_paten        = (bool) $o->is_paten;
        $this->satuan          = $o->satuan;
        $this->satuan_besar_id = $o->satuan_besar_id;
        $this->satuan_kecil_id = $o->satuan_kecil_id;
        $this->konversi        = $o->konversi        ?? 1;
        $this->stok            = (string) $o->stok;
        $this->harga           = (string) $o->harga;
        $this->harga_beli      = $o->harga_beli ? (string) $o->harga_beli : '';
        $this->harga_bpjs      = $o->harga_bpjs ? (string) $o->harga_bpjs : '';
        $this->kategori        = $o->kategori        ?? '';
        $this->expired_date    = $o->expired_date    ? $o->expired_date->format('Y-m-d') : '';
        $this->is_active       = (bool) $o->is_active;
        $this->isEdit          = true;
        $this->showModal       = true;
        $this->resetValidation();
    }

    public function save(FarmasiService $service): void
    {
        $this->validate($this->getRules(), $this->getMessages());

        $data = [
            'kode'            => strtoupper($this->kode),
            'barcode'         => $this->barcode          ?: null,
            'nama'            => $this->nama,
            'generik'         => $this->generik          ?: null,
            'jenis_barang'    => $this->jenis_barang,
            'is_paten'        => $this->is_paten,
            'satuan'          => $this->satuan,
            'satuan_besar_id' => $this->satuan_besar_id,
            'satuan_kecil_id' => $this->satuan_kecil_id,
            'konversi'        => $this->konversi,
            'stok'            => (int) $this->stok,
            'harga'           => (float) $this->harga,
            'harga_beli'      => $this->harga_beli  ? (float) $this->harga_beli  : null,
            'harga_bpjs'      => $this->harga_bpjs  ? (float) $this->harga_bpjs  : null,
            'kategori'        => $this->kategori    ?: null,
            'expired_date'    => $this->expired_date ?: null,
            'is_active'       => $this->is_active,
        ];

        $this->isEdit
            ? $service->updateObat($this->obatId, $data)
            : $service->createObat($data);

        $this->showModal = false;
        $this->dispatch('obat-saved');
        $msg = $this->isEdit ? 'Data obat berhasil diupdate.' : 'Obat/Alkes baru ditambahkan.';
        $this->dispatch('notify', type: 'success', message: $msg);
    }

    public function getSatuanListProperty()
    {
        return Satuan::aktif()->orderBy('nama')->get(['id', 'nama']);
    }

    public function render()
    {
        return view('livewire.farmasi.obat-form');
    }
}

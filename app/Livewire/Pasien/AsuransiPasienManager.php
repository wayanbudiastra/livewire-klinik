<?php

namespace App\Livewire\Pasien;

use App\Models\{Asuransi, Pasien, PasienAsuransi};
use Livewire\Component;

class AsuransiPasienManager extends Component
{
    public Pasien $pasien;
    public bool   $showForm = false;

    public int    $asuransiId    = 0;
    public string $nomorPolis    = '';
    public string $namaPemegang  = '';
    public string $berlakuMulai  = '';
    public string $berlakuSampai = '';
    public bool   $isPrimary     = false;

    public function mount(Pasien $pasien): void
    {
        $this->pasien = $pasien;
    }

    protected function rules(): array
    {
        return [
            'asuransiId'   => ['required', 'exists:asuransi,id'],
            'nomorPolis'   => ['required', 'string', 'max:50'],
            'namaPemegang' => ['nullable', 'string', 'max:100'],
            'berlakuMulai' => ['nullable', 'date'],
            'berlakuSampai'=> ['nullable', 'date', 'after_or_equal:berlakuMulai'],
        ];
    }

    protected function messages(): array
    {
        return [
            'asuransiId.required' => 'Pilih asuransi.',
            'nomorPolis.required' => 'Nomor polis wajib diisi.',
        ];
    }

    public function tambah(): void
    {
        $this->validate();

        $exists = PasienAsuransi::where('pasien_id', $this->pasien->id)
            ->where('asuransi_id', $this->asuransiId)
            ->where('nomor_polis', $this->nomorPolis)
            ->exists();

        if ($exists) {
            $this->addError('nomorPolis', 'Asuransi dengan polis ini sudah terdaftar.');
            return;
        }

        if ($this->isPrimary) {
            PasienAsuransi::where('pasien_id', $this->pasien->id)
                ->update(['is_primary' => false]);
        }

        PasienAsuransi::create([
            'pasien_id'           => $this->pasien->id,
            'asuransi_id'         => $this->asuransiId,
            'nomor_polis'         => $this->nomorPolis,
            'nama_pemegang_polis' => $this->namaPemegang ?: null,
            'berlaku_mulai'       => $this->berlakuMulai ?: null,
            'berlaku_sampai'      => $this->berlakuSampai ?: null,
            'is_primary'          => $this->isPrimary,
        ]);

        $this->reset(['asuransiId', 'nomorPolis', 'namaPemegang', 'berlakuMulai', 'berlakuSampai', 'isPrimary', 'showForm']);
        session()->flash('success', 'Asuransi pasien ditambahkan.');
    }

    public function setPrimary(int $id): void
    {
        PasienAsuransi::where('pasien_id', $this->pasien->id)->update(['is_primary' => false]);
        PasienAsuransi::where('id', $id)->update(['is_primary' => true]);
    }

    public function hapus(int $id): void
    {
        PasienAsuransi::where('id', $id)->update(['is_active' => false]);
    }

    public function render()
    {
        return view('livewire.pasien.asuransi-pasien-manager', [
            'daftarAsuransiPasien' => PasienAsuransi::with('asuransi')
                ->where('pasien_id', $this->pasien->id)
                ->where('is_active', true)
                ->get(),
            'opsiAsuransi' => Asuransi::where('is_active', true)->orderBy('nama')->get(),
        ]);
    }
}

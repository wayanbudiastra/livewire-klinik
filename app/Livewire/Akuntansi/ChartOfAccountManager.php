<?php

namespace App\Livewire\Akuntansi;

use App\Models\Akuntansi\ChartOfAccount;
use Livewire\Attributes\Computed;
use Livewire\Component;

class ChartOfAccountManager extends Component
{
    public bool $showForm = false;
    public ?int $editId   = null;

    public string $kode        = '';
    public string $nama        = '';
    public string $golongan    = 'aset';
    public string $tipe_normal = 'debit';
    public bool   $is_aktif    = true;

    #[Computed]
    public function akunList()
    {
        return ChartOfAccount::orderBy('kode')->get();
    }

    public function bukaForm(?int $id = null): void
    {
        $this->resetValidation();

        if ($id) {
            $akun = ChartOfAccount::findOrFail($id);
            $this->editId      = $akun->id;
            $this->kode        = $akun->kode;
            $this->nama        = $akun->nama;
            $this->golongan    = $akun->golongan;
            $this->tipe_normal = $akun->tipe_normal;
            $this->is_aktif    = $akun->is_aktif;
        } else {
            $this->editId      = null;
            $this->kode        = '';
            $this->nama        = '';
            $this->golongan    = 'aset';
            $this->tipe_normal = 'debit';
            $this->is_aktif    = true;
        }

        $this->showForm = true;
    }

    public function simpan(): void
    {
        $this->validate([
            'kode'        => 'required|string|max:10|regex:/^[0-9]-[0-9]{4}$/' . ($this->editId ? '' : '|unique:chart_of_accounts,kode'),
            'nama'        => 'required|string|max:100',
            'golongan'    => 'required|in:aset,liabilitas,ekuitas,pendapatan,biaya,lainnya',
            'tipe_normal' => 'required|in:debit,kredit',
        ], [
            'kode.regex' => 'Format kode harus seperti 1-1100 (angka-angka).',
        ]);

        ChartOfAccount::updateOrCreate(
            ['id' => $this->editId],
            [
                'kode'        => $this->kode,
                'nama'        => $this->nama,
                'golongan'    => $this->golongan,
                'tipe_normal' => $this->tipe_normal,
                'is_aktif'    => $this->is_aktif,
            ]
        );

        $this->showForm = false;
        $this->dispatch('notify', type: 'success', message: 'Akun berhasil disimpan.');
    }

    public function toggleAktif(int $id): void
    {
        $akun = ChartOfAccount::findOrFail($id);
        $akun->update(['is_aktif' => !$akun->is_aktif]);
    }

    public function render()
    {
        return view('livewire.akuntansi.chart-of-account-manager');
    }
}

<?php

namespace App\Livewire\Pengaturan\Asuransi;

use App\Models\Asuransi;
use App\Services\Asuransi\AsuransiService;
use Illuminate\Validation\Rule;
use Livewire\Component;

class AsuransiForm extends Component
{
    public ?Asuransi $asuransi = null;
    public bool      $isEdit   = false;

    public string $kode            = '';
    public string $nama            = '';
    public string $tipe            = 'swasta';
    public string $periodeMulai    = '';
    public string $periodeBerakhir = '';

    public float $coverProsedur     = 0;
    public float $coverLaboratorium = 0;
    public float $coverRadiologi    = 0;
    public float $coverPeralatan    = 0;

    public string $plafonPerKunjungan = '';
    public string $plafonPerTahun     = '';

    public string $pic                = '';
    public string $telepon            = '';
    public string $email              = '';
    public string $alamat             = '';
    public int    $termPembayaranHari = 30;

    protected function rules(): array
    {
        return [
            'kode' => ['required', 'string', 'max:30',
                       Rule::unique('asuransi', 'kode')->ignore($this->asuransi?->id)],
            'nama' => ['required', 'string', 'max:150'],
            'tipe' => ['required', Rule::in(['swasta', 'bpjs', 'pemerintah', 'corporate'])],
            'periodeMulai'       => ['nullable', 'date'],
            'periodeBerakhir'    => ['nullable', 'date', 'after_or_equal:periodeMulai'],
            'coverProsedur'      => ['numeric', 'min:0', 'max:100'],
            'coverLaboratorium'  => ['numeric', 'min:0', 'max:100'],
            'coverRadiologi'     => ['numeric', 'min:0', 'max:100'],
            'coverPeralatan'     => ['numeric', 'min:0', 'max:100'],
            'termPembayaranHari' => ['integer', 'min:0', 'max:365'],
            'email'              => ['nullable', 'email', 'max:150'],
        ];
    }

    protected function messages(): array
    {
        return [
            'kode.required'  => 'Kode asuransi wajib diisi.',
            'nama.required'  => 'Nama asuransi wajib diisi.',
            'kode.unique'    => 'Kode ini sudah digunakan.',
        ];
    }

    public function mount(?int $id = null): void
    {
        if ($id) {
            $this->asuransi = Asuransi::findOrFail($id);
            $this->isEdit   = true;
            $a = $this->asuransi;
            $this->fill([
                'kode' => $a->kode, 'nama' => $a->nama, 'tipe' => $a->tipe,
                'periodeMulai'      => $a->periode_mulai?->format('Y-m-d') ?? '',
                'periodeBerakhir'   => $a->periode_berakhir?->format('Y-m-d') ?? '',
                'coverProsedur'     => $a->cover_prosedur,
                'coverLaboratorium' => $a->cover_laboratorium,
                'coverRadiologi'    => $a->cover_radiologi,
                'coverPeralatan'    => $a->cover_peralatan,
                'plafonPerKunjungan'=> $a->plafon_per_kunjungan ?? '',
                'plafonPerTahun'    => $a->plafon_per_tahun ?? '',
                'pic'               => $a->pic ?? '',
                'telepon'           => $a->telepon ?? '',
                'email'             => $a->email ?? '',
                'alamat'            => $a->alamat ?? '',
                'termPembayaranHari'=> $a->term_pembayaran_hari,
            ]);
        } else {
            $this->kode = app(AsuransiService::class)->generateKode();
        }
    }

    public function simpan(AsuransiService $service): void
    {
        $this->validate();

        $data = [
            'kode' => $this->kode, 'nama' => $this->nama, 'tipe' => $this->tipe,
            'periode_mulai'       => $this->periodeMulai ?: null,
            'periode_berakhir'    => $this->periodeBerakhir ?: null,
            'cover_prosedur'      => $this->coverProsedur,
            'cover_laboratorium'  => $this->coverLaboratorium,
            'cover_radiologi'     => $this->coverRadiologi,
            'cover_peralatan'     => $this->coverPeralatan,
            'plafon_per_kunjungan'=> $this->plafonPerKunjungan ?: null,
            'plafon_per_tahun'    => $this->plafonPerTahun ?: null,
            'pic'                 => $this->pic ?: null,
            'telepon'             => $this->telepon ?: null,
            'email'               => $this->email ?: null,
            'alamat'              => $this->alamat ?: null,
            'term_pembayaran_hari'=> $this->termPembayaranHari,
        ];

        if ($this->isEdit) {
            $service->update($this->asuransi, $data);
            session()->flash('success', 'Asuransi berhasil diperbarui.');
        } else {
            $service->create($data);
            session()->flash('success', 'Asuransi berhasil ditambahkan.');
        }

        $this->redirectRoute('pengaturan.asuransi.index');
    }

    public function render()
    {
        return view('livewire.pengaturan.asuransi.asuransi-form');
    }
}

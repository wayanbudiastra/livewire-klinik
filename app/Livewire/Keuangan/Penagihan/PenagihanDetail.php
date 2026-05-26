<?php

namespace App\Livewire\Keuangan\Penagihan;

use App\Models\PenagihanAsuransi;
use App\Services\Asuransi\PenagihanService;
use Livewire\Component;

class PenagihanDetail extends Component
{
    public PenagihanAsuransi $penagihan;
    public bool   $showBayarForm  = false;

    public float  $jumlahBayar     = 0;
    public string $metode          = 'transfer';
    public string $tanggalBayar    = '';
    public string $nomorReferensi  = '';
    public string $catatan         = '';

    public function mount(PenagihanAsuransi $penagihan): void
    {
        $this->penagihan   = $penagihan->load(['asuransi', 'items.piutang.pasien', 'pembayaran.pencatat']);
        $this->tanggalBayar = now()->format('Y-m-d');
        $this->jumlahBayar  = $penagihan->sisa_tagihan;
    }

    protected function rules(): array
    {
        return [
            'jumlahBayar'    => ['required', 'numeric', 'min:0.01', 'max:' . $this->penagihan->sisa_tagihan],
            'metode'         => ['required', 'in:transfer,cek,giro,tunai'],
            'tanggalBayar'   => ['required', 'date'],
            'nomorReferensi' => ['nullable', 'string', 'max:100'],
        ];
    }

    protected function messages(): array
    {
        return [
            'jumlahBayar.required' => 'Jumlah bayar wajib diisi.',
            'jumlahBayar.max'      => 'Jumlah tidak boleh melebihi sisa tagihan.',
            'tanggalBayar.required'=> 'Tanggal bayar wajib diisi.',
        ];
    }

    public function catatBayar(PenagihanService $service): void
    {
        $this->validate();

        try {
            $service->catatPembayaran(
                $this->penagihan,
                $this->jumlahBayar,
                $this->metode,
                $this->tanggalBayar,
                $this->nomorReferensi ?: null,
                auth()->id()
            );

            $this->penagihan = $this->penagihan->fresh(['asuransi', 'items.piutang.pasien', 'pembayaran.pencatat']);
            $this->showBayarForm = false;
            $this->reset(['jumlahBayar', 'nomorReferensi', 'catatan']);
            $this->jumlahBayar = $this->penagihan->sisa_tagihan;

            session()->flash('success', 'Pembayaran berhasil dicatat.');
        } catch (\Exception $e) {
            $this->addError('jumlahBayar', $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.keuangan.penagihan.penagihan-detail');
    }
}

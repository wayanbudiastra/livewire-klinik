<?php

namespace App\Livewire\Kasir\Deposit;

use Livewire\Component;
use App\Models\{Pasien, DepositPasien};
use App\Services\Kasir\{DepositService, SesiKasService};

class TopupDepositForm extends Component
{
    public string $searchPasien  = '';
    public array  $hasilSearch   = [];
    public ?int   $pasienId      = null;
    public ?array $pasienDipilih = null;
    public ?array $depositInfo   = null;

    public string $jumlah     = '';
    public string $keterangan = '';

    protected function rules(): array
    {
        return [
            'pasienId'   => ['required', 'exists:pasien,id'],
            'jumlah'     => ['required', 'numeric', 'min:1000', 'max:100000000'],
            'keterangan' => ['nullable', 'string', 'max:200'],
        ];
    }

    protected $messages = [
        'pasienId.required' => 'Pilih pasien terlebih dahulu.',
        'jumlah.min'        => 'Minimal top-up Rp 1.000',
    ];

    public function updatedSearchPasien(): void
    {
        $this->cariPasien();
    }

    public function cariPasien(): void
    {
        if (strlen($this->searchPasien) < 2) {
            $this->hasilSearch = [];
            return;
        }

        $this->hasilSearch = Pasien::where('is_active', true)
            ->where(fn($q) => $q
                ->where('nama',      'like', "%{$this->searchPasien}%")
                ->orWhere('nomor_rm', 'like', "%{$this->searchPasien}%")
                ->orWhere('telepon',  'like', "%{$this->searchPasien}%")
            )
            ->with('depositPasien')
            ->limit(8)
            ->get()
            ->map(fn($p) => [
                'id'       => $p->id,
                'nomor_rm' => $p->nomor_rm,
                'nama'     => $p->nama,
                'telepon'  => $p->telepon ?? '-',
                'saldo'    => $p->depositPasien?->saldo ?? 0,
            ])
            ->toArray();
    }

    public function pilihPasien(int $id): void
    {
        $pasien = Pasien::with('depositPasien')->findOrFail($id);
        $this->pasienId      = $pasien->id;
        $this->pasienDipilih = [
            'id'       => $pasien->id,
            'nama'     => $pasien->nama,
            'nomor_rm' => $pasien->nomor_rm,
        ];
        $this->depositInfo = [
            'saldo'          => $pasien->depositPasien?->saldo ?? 0,
            'total_topup'    => $pasien->depositPasien?->total_topup ?? 0,
            'total_terpakai' => $pasien->depositPasien?->total_terpakai ?? 0,
        ];
        $this->searchPasien = '';
        $this->hasilSearch  = [];
    }

    public function simpan(DepositService $depositService, SesiKasService $sesiKasService): void
    {
        $this->validate();

        $sesiKas = $sesiKasService->getSesiAktif(auth()->id());
        if (!$sesiKas) {
            $this->addError('jumlah', 'Kas belum dibuka. Buka kas terlebih dahulu.');
            return;
        }

        $pasien = Pasien::findOrFail($this->pasienId);

        try {
            $depositService->topup(
                pasien:     $pasien,
                jumlah:     (float) $this->jumlah,
                userId:     auth()->id(),
                sesiKas:    $sesiKas,
                keterangan: $this->keterangan ?: null,
            );

            $this->pilihPasien($this->pasienId);
            $this->jumlah     = '';
            $this->keterangan = '';

            session()->flash('success',
                'Top-up berhasil. Saldo baru: Rp ' . number_format($this->depositInfo['saldo'], 0, ',', '.')
            );
        } catch (\Exception $e) {
            $this->addError('jumlah', $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.kasir.deposit.topup-deposit-form');
    }
}

<?php

namespace App\Livewire\Kasir\Deposit;

use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;
use App\Models\{Pasien, DepositPasien};
use App\Services\Kasir\{DepositService, SesiKasService};

class TopupDepositForm extends Component
{
    use WithPagination;

    // ── Daftar deposit aktif ─────────────────────────────────────────
    public string $searchDeposit = '';

    // ── Top-up ──────────────────────────────────────────────────────
    public bool   $showTopupModal = false;
    public string $searchPasien  = '';
    public array  $hasilSearch   = [];
    public ?int   $pasienId      = null;
    public ?array $pasienDipilih = null;
    public ?array $depositInfo   = null;
    public string $jumlah        = '';
    public string $keterangan    = '';

    // ── Refund ──────────────────────────────────────────────────────
    public bool   $showRefundModal  = false;
    public ?int   $refundPasienId   = null;
    public ?array $refundPasienInfo = null;
    public string $jumlahRefund     = '';
    public string $alasanRefund     = '';
    public string $errorRefund      = '';

    public function updatedSearchDeposit(): void { $this->resetPage(); }

    #[Computed]
    public function daftarDeposit()
    {
        return DepositPasien::where('saldo', '>', 0)
            ->with('pasien')
            ->when($this->searchDeposit, fn ($q) => $q->whereHas('pasien', fn ($p) =>
                $p->where('nama', 'like', "%{$this->searchDeposit}%")
                  ->orWhere('nomor_rm', 'like', "%{$this->searchDeposit}%")
            ))
            ->orderByDesc('saldo')
            ->paginate(15);
    }

    // ── Top-up ──────────────────────────────────────────────────────

    public function openTopup(?int $pasienId = null): void
    {
        $this->resetTopupForm();
        if ($pasienId) {
            $this->pilihPasien($pasienId);
        }
        $this->showTopupModal = true;
    }

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
            ->where(fn ($q) => $q
                ->where('nama',       'like', "%{$this->searchPasien}%")
                ->orWhere('nomor_rm', 'like', "%{$this->searchPasien}%")
                ->orWhere('telepon',  'like', "%{$this->searchPasien}%")
            )
            ->with('depositPasien')
            ->limit(8)
            ->get()
            ->map(fn ($p) => [
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
        $this->validate([
            'pasienId'   => ['required', 'exists:pasien,id'],
            'jumlah'     => ['required', 'numeric', 'min:1000', 'max:100000000'],
            'keterangan' => ['nullable', 'string', 'max:200'],
        ], [
            'pasienId.required' => 'Pilih pasien terlebih dahulu.',
            'jumlah.min'        => 'Minimal top-up Rp 1.000.',
        ]);

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

            $this->showTopupModal = false;
            unset($this->daftarDeposit);
            session()->flash('success',
                'Top-up deposit Rp ' . number_format((float) $this->jumlah, 0, ',', '.') .
                ' untuk ' . $pasien->nama . ' berhasil.'
            );
            $this->resetTopupForm();
        } catch (\Exception $e) {
            $this->addError('jumlah', $e->getMessage());
        }
    }

    // ── Refund ──────────────────────────────────────────────────────

    public function openRefund(int $pasienId): void
    {
        $pasien = Pasien::with('depositPasien')->findOrFail($pasienId);
        $this->refundPasienId   = $pasienId;
        $this->refundPasienInfo = [
            'nama'     => $pasien->nama,
            'nomor_rm' => $pasien->nomor_rm,
            'saldo'    => (float) ($pasien->depositPasien?->saldo ?? 0),
        ];
        $this->jumlahRefund    = '';
        $this->alasanRefund    = '';
        $this->errorRefund     = '';
        $this->showRefundModal = true;
    }

    public function prosesRefund(DepositService $depositService, SesiKasService $sesiKasService): void
    {
        $this->errorRefund = '';

        $this->validate([
            'jumlahRefund' => ['required', 'numeric', 'min:1000'],
            'alasanRefund' => ['required', 'string', 'min:5', 'max:200'],
        ], [
            'jumlahRefund.required' => 'Jumlah refund wajib diisi.',
            'jumlahRefund.min'      => 'Minimal refund Rp 1.000.',
            'alasanRefund.required' => 'Alasan refund wajib diisi.',
            'alasanRefund.min'      => 'Alasan minimal 5 karakter.',
        ]);

        $sesiKas = $sesiKasService->getSesiAktif(auth()->id());
        if (!$sesiKas) {
            $this->errorRefund = 'Kas belum dibuka. Buka kas terlebih dahulu.';
            return;
        }

        $pasien = Pasien::findOrFail($this->refundPasienId);

        try {
            $depositService->refundManual(
                pasien:     $pasien,
                jumlah:     (float) $this->jumlahRefund,
                userId:     auth()->id(),
                sesiKas:    $sesiKas,
                keterangan: $this->alasanRefund,
            );

            $this->showRefundModal = false;
            unset($this->daftarDeposit);
            session()->flash('success',
                'Refund deposit Rp ' . number_format((float) $this->jumlahRefund, 0, ',', '.') .
                ' untuk ' . $pasien->nama . ' berhasil dicatat.'
            );
        } catch (\Exception $e) {
            $this->errorRefund = $e->getMessage();
        }
    }

    private function resetTopupForm(): void
    {
        $this->pasienId      = null;
        $this->pasienDipilih = null;
        $this->depositInfo   = null;
        $this->searchPasien  = '';
        $this->hasilSearch   = [];
        $this->jumlah        = '';
        $this->keterangan    = '';
    }

    public function render()
    {
        return view('livewire.kasir.deposit.topup-deposit-form');
    }
}

<?php

namespace App\Livewire\Kasir\SesiKas;

use Livewire\Attributes\Computed;
use Livewire\Component;
use App\Models\{SesiKas, PembayaranSplit, Invoice};
use App\Services\Kasir\SesiKasService;

class SesiKasPanel extends Component
{
    public ?SesiKas $sesiAktif       = null;
    public bool     $showBuka        = false;
    public bool     $showTutup       = false;
    public bool     $showBukaKembali = false;

    public string $saldoAwal  = '';
    public string $catatan    = '';
    public string $catatanTutup        = '';
    public string $uangFisikAkhir      = '';
    public string $passwordBukaKembali = '';
    public string $alasanBukaKembali   = '';
    public ?int   $sesiIdBukaKembali   = null;
    public string $errorMsg            = '';

    public function mount(): void
    {
        $this->sesiAktif = app(SesiKasService::class)->getSesiAktif(auth()->id());
    }

    #[Computed]
    public function rekapSesi(): array
    {
        if (!$this->sesiAktif) return [
            'per_metode'       => [],
            'total_cash'       => 0.0,
            'total_semua'      => 0.0,
            'total_pembatalan' => 0,
        ];

        $labels = [
            'tunai'    => 'Tunai',
            'debit'    => 'Debit/Kartu',
            'kredit'   => 'Kredit',
            'transfer' => 'Transfer',
            'qris'     => 'QRIS',
            'deposit'  => 'Deposit',
            'bpjs'     => 'BPJS',
            'asuransi' => 'Asuransi',
        ];

        $rows = PembayaranSplit::where('pembayaran_split.sesi_kas_id', $this->sesiAktif->id)
            ->join('billing', 'pembayaran_split.billing_id', '=', 'billing.id')
            ->where('billing.status', 'lunas')
            ->selectRaw('metode, COUNT(*) as jumlah_trx, SUM(pembayaran_split.jumlah) as total')
            ->groupBy('metode')
            ->get();

        $perMetode = $rows->map(fn ($r) => [
            'metode'     => $r->metode,
            'label'      => $labels[$r->metode] ?? ucfirst($r->metode),
            'jumlah_trx' => (int) $r->jumlah_trx,
            'total'      => (float) $r->total,
        ])->values()->toArray();

        $totalCash  = (float) ($rows->where('metode', 'tunai')->first()?->total ?? 0);
        $totalSemua = (float) $rows->sum('total');

        $totalPembatalan = Invoice::where('sesi_kas_id', $this->sesiAktif->id)
            ->where('status', 'dibatalkan')
            ->count();

        return [
            'per_metode'       => $perMetode,
            'total_cash'       => $totalCash,
            'total_semua'      => $totalSemua,
            'total_pembatalan' => $totalPembatalan,
        ];
    }

    public function bukaKas(SesiKasService $service): void
    {
        $this->validate([
            'saldoAwal' => ['required', 'numeric', 'min:0'],
        ]);

        try {
            $this->sesiAktif = $service->bukaKas(
                auth()->id(),
                (float) $this->saldoAwal,
                $this->catatan ?: null
            );
            $this->showBuka = false;
            $this->reset(['saldoAwal', 'catatan']);
            $this->dispatch('sesi-kas-changed');
            session()->flash('success', 'Kas berhasil dibuka.');
        } catch (\Exception $e) {
            $this->addError('saldoAwal', $e->getMessage());
        }
    }

    public function tutupKas(SesiKasService $service): void
    {
        if (!$this->sesiAktif) return;

        $this->validate([
            'uangFisikAkhir' => ['required', 'numeric', 'min:0'],
        ], [
            'uangFisikAkhir.required' => 'Masukkan jumlah uang fisik yang dihitung.',
            'uangFisikAkhir.numeric'  => 'Jumlah harus berupa angka.',
        ]);

        try {
            $service->tutupKas(
                $this->sesiAktif,
                auth()->id(),
                (float) $this->uangFisikAkhir,
                $this->catatanTutup ?: null
            );
            $this->sesiAktif      = null;
            $this->showTutup      = false;
            $this->uangFisikAkhir = '';
            $this->catatanTutup   = '';
            unset($this->rekapSesi);
            $this->dispatch('sesi-kas-changed');
            session()->flash('success', 'Kas berhasil ditutup. Laporan kas tersimpan.');
        } catch (\Exception $e) {
            $this->addError('catatanTutup', $e->getMessage());
        }
    }

    public function bukaKasKembali(SesiKasService $service): void
    {
        $this->validate([
            'passwordBukaKembali' => ['required', 'string'],
            'alasanBukaKembali'   => ['required', 'string', 'min:10'],
            'sesiIdBukaKembali'   => ['required', 'exists:sesi_kas,id'],
        ]);

        try {
            $sesi = SesiKas::findOrFail($this->sesiIdBukaKembali);
            $this->sesiAktif = $service->bukaKasKembali(
                $sesi,
                $this->passwordBukaKembali,
                $this->alasanBukaKembali,
                auth()->id()
            );
            $this->showBukaKembali     = false;
            $this->errorMsg            = '';
            $this->passwordBukaKembali = '';
            $this->alasanBukaKembali   = '';
            unset($this->rekapSesi);
            $this->dispatch('sesi-kas-changed');
            session()->flash('success', 'Kas berhasil dibuka kembali.');
        } catch (\Exception $e) {
            $this->errorMsg = $e->getMessage();
        }
    }

    public function render()
    {
        $sesiTutupHariIni = SesiKas::where('status', 'tutup')
            ->whereDate('tanggal', today())
            ->with('user')
            ->latest()
            ->get();

        return view('livewire.kasir.sesi-kas.sesi-kas-panel', [
            'sesiTutupHariIni' => $sesiTutupHariIni,
        ]);
    }
}

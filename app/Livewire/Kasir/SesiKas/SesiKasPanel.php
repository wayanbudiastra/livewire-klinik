<?php

namespace App\Livewire\Kasir\SesiKas;

use Livewire\Component;
use App\Models\SesiKas;
use App\Services\Kasir\SesiKasService;

class SesiKasPanel extends Component
{
    public ?SesiKas $sesiAktif       = null;
    public bool     $showBuka        = false;
    public bool     $showTutup       = false;
    public bool     $showBukaKembali = false;

    public string $saldoAwal  = '';
    public string $catatan    = '';
    public string $catatanTutup      = '';
    public string $passwordBukaKembali = '';
    public string $alasanBukaKembali   = '';
    public ?int   $sesiIdBukaKembali   = null;
    public string $errorMsg            = '';

    public function mount(): void
    {
        $this->sesiAktif = app(SesiKasService::class)->getSesiAktif(auth()->id());
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

        try {
            $service->tutupKas(
                $this->sesiAktif,
                auth()->id(),
                $this->catatanTutup ?: null
            );
            $this->sesiAktif = null;
            $this->showTutup = false;
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

<?php

namespace App\Livewire\Keuangan\Penagihan;

use App\Models\{Asuransi, PiutangAsuransi};
use App\Services\Asuransi\PenagihanService;
use Livewire\Component;

class PenagihanForm extends Component
{
    public int    $asuransiId  = 0;
    public array  $piutangIds  = [];
    public string $catatan     = '';

    public function getPiutangTertagihProperty()
    {
        if (!$this->asuransiId) return collect();

        return PiutangAsuransi::with('pasien')
            ->where('asuransi_id', $this->asuransiId)
            ->where('status', 'tertagih')
            ->orderByDesc('tanggal_piutang')
            ->get();
    }

    public function getTotalDipilihProperty(): float
    {
        if (empty($this->piutangIds)) return 0;
        return PiutangAsuransi::whereIn('id', $this->piutangIds)->sum('sisa_piutang');
    }

    public function updatedAsuransiId(): void
    {
        $this->piutangIds = [];
    }

    public function togglePilih(int $id): void
    {
        if (in_array($id, $this->piutangIds)) {
            $this->piutangIds = array_values(array_diff($this->piutangIds, [$id]));
        } else {
            $this->piutangIds[] = $id;
        }
    }

    public function pilihSemua(): void
    {
        $this->piutangIds = $this->piutangTertagih->pluck('id')->toArray();
    }

    public function batalPilih(): void
    {
        $this->piutangIds = [];
    }

    public function buat(PenagihanService $service): void
    {
        $this->validate([
            'asuransiId' => ['required', 'exists:asuransi,id'],
            'piutangIds' => ['required', 'array', 'min:1'],
        ], [
            'asuransiId.required' => 'Pilih asuransi terlebih dahulu.',
            'piutangIds.required' => 'Pilih minimal 1 piutang.',
            'piutangIds.min'      => 'Pilih minimal 1 piutang.',
        ]);

        try {
            $penagihan = $service->buatPenagihan($this->asuransiId, $this->piutangIds, auth()->id());
            session()->flash('success', "Penagihan {$penagihan->nomor_penagihan} berhasil dibuat.");
            $this->redirectRoute('keuangan.penagihan.show', $penagihan->id);
        } catch (\RuntimeException $e) {
            $this->addError('piutangIds', $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.keuangan.penagihan.penagihan-form', [
            'opsiAsuransi'    => Asuransi::where('is_active', true)->orderBy('nama')->get(),
            'piutangTertagih' => $this->piutangTertagih,
            'totalDipilih'    => $this->totalDipilih,
        ]);
    }
}

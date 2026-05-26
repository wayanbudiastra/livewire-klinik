<?php

namespace App\Livewire\Keuangan\Piutang;

use App\Models\{Asuransi, PiutangAsuransi};
use Livewire\Component;
use Livewire\WithPagination;

class PiutangTable extends Component
{
    use WithPagination;

    public string $search         = '';
    public int    $filterAsuransi = 0;
    public string $filterStatus   = '';
    public array  $selected       = [];

    public function getPiutangProperty()
    {
        return PiutangAsuransi::query()
            ->with(['asuransi', 'pasien', 'billing'])
            ->when($this->filterAsuransi, fn($q) => $q->where('asuransi_id', $this->filterAsuransi))
            ->when($this->filterStatus, fn($q) => $q->where('status', $this->filterStatus))
            ->when($this->search, fn($q) => $q
                ->where('nomor_piutang', 'like', "%{$this->search}%")
                ->orWhereHas('pasien', fn($q2) => $q2->where('nama', 'like', "%{$this->search}%"))
            )
            ->orderByDesc('tanggal_piutang')
            ->paginate(20);
    }

    public function getSummaryProperty(): array
    {
        return [
            'total_outstanding' => PiutangAsuransi::whereIn('status', ['tertagih', 'diajukan', 'dibayar_sebagian'])->sum('sisa_piutang'),
            'total_tertagih'    => PiutangAsuransi::where('status', 'tertagih')->sum('sisa_piutang'),
            'jatuh_tempo'       => PiutangAsuransi::whereIn('status', ['tertagih', 'diajukan'])
                                    ->whereDate('tanggal_jatuh_tempo', '<', today())->count(),
        ];
    }

    public function toggleSelect(int $id): void
    {
        if (in_array($id, $this->selected)) {
            $this->selected = array_values(array_diff($this->selected, [$id]));
        } else {
            $this->selected[] = $id;
        }
    }

    public function updatedSearch(): void  { $this->resetPage(); }
    public function updatedFilterAsuransi(): void { $this->resetPage(); }
    public function updatedFilterStatus(): void   { $this->resetPage(); }

    public function render()
    {
        return view('livewire.keuangan.piutang.piutang-table', [
            'piutang'      => $this->piutang,
            'summary'      => $this->summary,
            'opsiAsuransi' => Asuransi::where('is_active', true)->orderBy('nama')->get(),
        ]);
    }
}

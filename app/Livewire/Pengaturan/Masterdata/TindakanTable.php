<?php

namespace App\Livewire\Pengaturan\Masterdata;

use App\Models\MasterTindakan;
use App\Services\MasterdataService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class TindakanTable extends Component
{
    use WithPagination, WithFileUploads;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $filterPoli = '';

    // ── Impor dari template XLS ─────────────────────────────
    // importMode 'baru': hanya membuat baris baru (kode yg sudah ada
    // dilewati). 'update': hanya mengubah baris yg kodenya sudah ada
    // (kode belum terdaftar dilewati). Sengaja 2 proses terpisah, bukan
    // upsert gabungan -- lihat MasterdataService::importTindakan().
    public bool   $showImportModal = false;
    public string $importMode      = 'baru'; // 'baru' | 'update'
    public $importFile             = null;
    public string $importState     = 'idle'; // idle | preview | done
    public int    $previewRowCount = 0;
    public array  $importResult    = [];
    public string $importError     = '';

    public function updatingSearch(): void    { $this->resetPage(); }
    public function updatingFilterPoli(): void { $this->resetPage(); }

    #[Computed]
    public function tindakan()
    {
        return MasterTindakan::with('poli:id,nama,kode')
            ->where('kategori', 'tindakan')
            ->when($this->search, fn ($q, $s) => $q->where('nama', 'like', "%{$s}%")
                ->orWhere('kode', 'like', "%{$s}%"))
            ->when($this->filterPoli, fn ($q, $p) =>
                $q->whereHas('poli', fn ($sq) => $sq->where('poli.id', $p))
            )
            ->orderBy('nama')
            ->paginate(10);
    }

    public function toggleAktif(int $id): void
    {
        $this->authorize('masterdata.edit');
        app(MasterdataService::class)->toggleAktifTindakan($id);
        unset($this->tindakan);
        $this->dispatch('notify', type: 'success', message: 'Status tindakan diupdate.');
    }

    #[On('tindakan-saved')]
    public function refresh(): void { unset($this->tindakan); }

    // ── Impor dari template XLS ─────────────────────────────

    public function openImportModal(string $mode = 'baru'): void
    {
        $this->authorize('masterdata.create');
        $this->resetImport();
        $this->importMode      = in_array($mode, ['baru', 'update'], true) ? $mode : 'baru';
        $this->showImportModal = true;
    }

    public function updatedImportFile(): void
    {
        $this->importError = '';
        $this->importState = 'idle';

        if (! $this->importFile) return;

        try {
            $rows = $this->parseRows();
            $this->previewRowCount = count($rows);

            if ($this->previewRowCount === 0) {
                $this->importError = 'File tidak berisi baris data (selain header).';
                return;
            }

            $this->importState = 'preview';
        } catch (\Throwable $e) {
            $this->importError = 'Gagal membaca file: ' . $e->getMessage();
        }
    }

    public function doImport(MasterdataService $service): void
    {
        $this->authorize('masterdata.create');

        if ($this->importState !== 'preview' || ! $this->importFile) return;

        try {
            $rows = $this->parseRows();
            $this->importResult = $service->importTindakan($rows, $this->importMode);
            $this->importState  = 'done';
            $this->importFile   = null;
            unset($this->tindakan);
            $this->dispatch('notify', type: 'success', message:
                "Impor selesai — {$this->importResult['imported']} baru, {$this->importResult['updated']} diupdate.");
        } catch (\Throwable $e) {
            $this->importError = 'Gagal impor: ' . $e->getMessage();
        }
    }

    private function parseRows(): array
    {
        $collection = \Maatwebsite\Excel\Facades\Excel::toCollection(null, $this->importFile->getRealPath())->first();
        if (! $collection || $collection->isEmpty()) {
            throw new \RuntimeException('Sheet kosong.');
        }

        return $collection->skip(1) // lewati baris header
            ->map(fn ($r) => array_values($r->toArray()))
            ->filter(fn ($r) => trim((string) ($r[0] ?? '')) !== '') // lewati baris kosong total
            ->values()
            ->toArray();
    }

    public function resetImport(): void
    {
        $this->importFile       = null;
        $this->importState      = 'idle';
        $this->previewRowCount  = 0;
        $this->importResult     = [];
        $this->importError      = '';
    }

    public function render()
    {
        return view('livewire.pengaturan.masterdata.tindakan-table');
    }
}

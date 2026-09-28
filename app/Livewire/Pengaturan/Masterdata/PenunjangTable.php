<?php

namespace App\Livewire\Pengaturan\Masterdata;

use App\Models\ItemPenunjang;
use App\Services\MasterdataService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class PenunjangTable extends Component
{
    use WithPagination, WithFileUploads;

    public string $kategori = 'lab'; // 'lab' | 'radiologi'

    #[Url(as: 'q')]
    public string $search = '';

    // ── Impor dari template XLS ─────────────────────────────
    // importMode 'baru'/'update' -- lihat catatan di TindakanTable.
    public bool   $showImportModal = false;
    public string $importMode      = 'baru'; // 'baru' | 'update'
    public $importFile             = null;
    public string $importState     = 'idle'; // idle | preview | done
    public int    $previewRowCount = 0;
    public array  $importResult    = [];
    public string $importError     = '';

    public function updatingSearch(): void { $this->resetPage(); }

    #[Computed]
    public function items()
    {
        return ItemPenunjang::where('kategori', $this->kategori)
            ->when($this->search, fn ($q, $s) =>
                $q->where('nama', 'like', "%{$s}%")
                  ->orWhere('kode', 'like', "%{$s}%"))
            ->orderBy('nama')
            ->paginate(10);
    }

    public function toggleAktif(int $id): void
    {
        $this->authorize('masterdata.edit');
        app(MasterdataService::class)->toggleAktifPenunjang($id);
        unset($this->items);
        $this->dispatch('notify', type: 'success', message: 'Status diupdate.');
    }

    #[On('penunjang-saved')]
    public function refresh(): void { unset($this->items); }

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
            $this->importResult = $service->importPenunjang($rows, $this->kategori, $this->importMode);
            $this->importState  = 'done';
            $this->importFile   = null;
            unset($this->items);
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

        return $collection->skip(1)
            ->map(fn ($r) => array_values($r->toArray()))
            ->filter(fn ($r) => trim((string) ($r[0] ?? '')) !== '')
            ->values()
            ->toArray();
    }

    public function resetImport(): void
    {
        $this->importFile      = null;
        $this->importState     = 'idle';
        $this->previewRowCount = 0;
        $this->importResult    = [];
        $this->importError     = '';
    }

    public function render()
    {
        return view('livewire.pengaturan.masterdata.penunjang-table');
    }
}

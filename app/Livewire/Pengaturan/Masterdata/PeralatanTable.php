<?php

namespace App\Livewire\Pengaturan\Masterdata;

use App\Models\PeralatanMedis;
use App\Services\MasterdataService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class PeralatanTable extends Component
{
    use WithPagination, WithFileUploads;

    #[Url(as: 'q')]
    public string $search        = '';

    #[Url]
    public string $filterStatus  = '';

    #[Url]
    public string $filterAktif   = '';   // '' | '1' | '0'

    // ── Impor dari template XLS ─────────────────────────────
    // importMode 'baru'/'update' -- lihat catatan di TindakanTable.
    public bool   $showImportModal = false;
    public string $importMode      = 'baru'; // 'baru' | 'update'
    public $importFile             = null;
    public string $importState     = 'idle'; // idle | preview | done
    public int    $previewRowCount = 0;
    public array  $importResult    = [];
    public string $importError     = '';

    public function updatingSearch(): void       { $this->resetPage(); }
    public function updatingFilterStatus(): void  { $this->resetPage(); }
    public function updatingFilterAktif(): void   { $this->resetPage(); }

    #[Computed]
    public function peralatan()
    {
        return PeralatanMedis::with('poliTerakhir:id,nama')
            ->when($this->search, fn ($q, $s) =>
                $q->where('nama', 'like', "%{$s}%")
                  ->orWhere('kode', 'like', "%{$s}%"))
            ->when($this->filterStatus, fn ($q, $s) => $q->where('status', $s))
            ->when($this->filterAktif !== '',
                fn ($q) => $q->where('is_active', $this->filterAktif === '1'))
            ->orderBy('nama')
            ->paginate(10);
    }

    public function updateStatus(int $id, string $status): void
    {
        $this->authorize('masterdata.edit');
        PeralatanMedis::findOrFail($id)->update(['status' => $status]);
        unset($this->peralatan);
        $this->dispatch('notify', type: 'success', message: 'Status peralatan diupdate.');
    }

    public function toggleAktif(int $id): void
    {
        $this->authorize('masterdata.edit');
        app(MasterdataService::class)->toggleAktifPeralatan($id);
        unset($this->peralatan);
        $this->dispatch('notify', type: 'success', message: 'Status aktif peralatan diupdate.');
    }

    #[On('peralatan-saved')]
    public function refresh(): void { unset($this->peralatan); }

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
            $this->importResult = $service->importPeralatan($rows, $this->importMode);
            $this->importState  = 'done';
            $this->importFile   = null;
            unset($this->peralatan);
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
        return view('livewire.pengaturan.masterdata.peralatan-table');
    }
}

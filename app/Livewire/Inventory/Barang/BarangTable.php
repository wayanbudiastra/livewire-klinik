<?php
namespace App\Livewire\Inventory\Barang;
use App\Models\Barang;
use App\Services\Inventory\BarangImportService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class BarangTable extends Component
{
    use WithPagination, WithFileUploads;
    #[Url(as:'q')] public string $search='';
    #[Url] public string $filterJenis='';
    #[Url] public string $filterStatus='aktif';
    public int $perPage=10;
    public function updatingSearch(): void { $this->resetPage(); }

    // ── Impor dari template XLS ─────────────────────────────
    // importMode 'baru': hanya membuat baris baru (kode yg sudah ada
    // dilewati). 'update': hanya mengubah baris yg kodenya sudah ada
    // (kode belum terdaftar dilewati). 2 proses terpisah, bukan upsert
    // gabungan -- lihat BarangImportService::importBarang().
    public bool   $showImportModal = false;
    public string $importMode      = 'baru'; // 'baru' | 'update'
    public $importFile             = null;
    public string $importState     = 'idle'; // idle | preview | done
    public int    $previewRowCount = 0;
    public array  $importResult    = [];
    public string $importError     = '';

    #[Computed]
    public function barang() {
        return Barang::with('supplierUtama:id,nama')
            ->when($this->search, fn($q,$s) => $q->where('nama','like',"%$s%")->orWhere('kode','like',"%$s%"))
            ->when($this->filterJenis, fn($q,$j) => $q->where('jenis',$j))
            ->when($this->filterStatus==='aktif', fn($q) => $q->where('is_active',true))
            ->when($this->filterStatus==='nonaktif', fn($q) => $q->where('is_active',false))
            ->orderBy('nama')->paginate($this->perPage);
    }

    public function toggleAktif(int $id): void {
        $b = Barang::findOrFail($id); $b->update(['is_active'=>!$b->is_active]);
        unset($this->barang);
        $this->dispatch('notify', type:'success', message:'Status barang diupdate.');
    }

    #[On('barang-saved')] public function refresh(): void { unset($this->barang); }

    // ── Impor dari template XLS ─────────────────────────────

    public function openImportModal(string $mode = 'baru'): void
    {
        $this->authorize('obat.create');
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

    public function doImport(BarangImportService $service): void
    {
        $this->authorize('obat.create');

        if ($this->importState !== 'preview' || ! $this->importFile) return;

        try {
            $rows = $this->parseRows();
            $this->importResult = $service->importBarang($rows, $this->importMode);
            $this->importState  = 'done';
            $this->importFile   = null;
            unset($this->barang);
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
        $this->importFile      = null;
        $this->importState     = 'idle';
        $this->previewRowCount = 0;
        $this->importResult    = [];
        $this->importError     = '';
    }

    public function render() { return view('livewire.inventory.barang.barang-table'); }
}

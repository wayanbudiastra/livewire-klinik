<?php
namespace App\Livewire\Inventory\Barang;
use App\Models\Barang;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class BarangTable extends Component
{
    use WithPagination;
    #[Url(as:'q')] public string $search='';
    #[Url] public string $filterJenis='';
    #[Url] public string $filterStatus='aktif';
    public int $perPage=10;
    public function updatingSearch(): void { $this->resetPage(); }

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
    public function render() { return view('livewire.inventory.barang.barang-table'); }
}

<?php
namespace App\Livewire\Inventory\Supplier;
use App\Models\Supplier;
use App\Services\Inventory\SupplierService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class SupplierTable extends Component
{
    use WithPagination;
    #[Url(as:'q')] public string $search = '';
    #[Url] public string $tipe = '';
    public int $perPage = 10;
    public function updatingSearch(): void { $this->resetPage(); }

    #[Computed]
    public function suppliers() {
        return Supplier::query()
            ->when($this->search, fn($q,$s) => $q->where('nama','like',"%$s%")->orWhere('kode','like',"%$s%"))
            ->when($this->tipe, fn($q,$t) => $q->where('tipe',$t))
            ->withCount('barang')
            ->orderBy('nama')
            ->paginate($this->perPage);
    }

    public function toggleAktif(int $id): void {
        app(SupplierService::class)->toggleAktif($id);
        unset($this->suppliers);
        $this->dispatch('notify', type:'success', message:'Status supplier diupdate.');
    }

    #[On('supplier-saved')] public function refresh(): void { unset($this->suppliers); }

    public function render() { return view('livewire.inventory.supplier.supplier-table'); }
}

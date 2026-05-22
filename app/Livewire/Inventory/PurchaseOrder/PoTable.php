<?php
namespace App\Livewire\Inventory\PurchaseOrder;
use App\Models\PurchaseOrder;
use App\Services\Inventory\PembelianService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class PoTable extends Component
{
    use WithPagination;
    #[Url(as:'q')] public string $search='';
    #[Url] public string $filterStatus='';
    public int $perPage=10;
    public function updatingSearch(): void { $this->resetPage(); }

    #[Computed]
    public function po() {
        return PurchaseOrder::with('supplier:id,nama,kode','dibuatOleh:id,nama')
            ->when($this->search, fn($q,$s) => $q->where('nomor_po','like',"%$s%")->orWhereHas('supplier',fn($sq) => $sq->where('nama','like',"%$s%")))
            ->when($this->filterStatus, fn($q,$st) => $q->where('status',$st))
            ->orderByDesc('tanggal_po')->paginate($this->perPage);
    }

    public function approve(int $id, PembelianService $service): void {
        try { $service->approvePo(PurchaseOrder::findOrFail($id), auth()->id()); unset($this->po); $this->dispatch('notify', type:'success', message:'PO disetujui.'); }
        catch(\Exception $e) { $this->dispatch('notify', type:'error', message:$e->getMessage()); }
    }

    public function batalkan(int $id, PembelianService $service): void {
        try { $service->batalkanPo(PurchaseOrder::findOrFail($id)); unset($this->po); $this->dispatch('notify', type:'success', message:'PO dibatalkan.'); }
        catch(\Exception $e) { $this->dispatch('notify', type:'error', message:$e->getMessage()); }
    }

    public function render() { return view('livewire.inventory.purchase-order.po-table'); }
}

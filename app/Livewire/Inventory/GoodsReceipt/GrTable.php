<?php
namespace App\Livewire\Inventory\GoodsReceipt;
use App\Models\GoodsReceipt;
use App\Services\Inventory\PenerimaanService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class GrTable extends Component
{
    use WithPagination;
    #[Url(as:'q')] public string $search='';
    #[Url] public string $filterStatus='';
    public int $perPage=10;

    #[Computed]
    public function gr() {
        return GoodsReceipt::with('supplier:id,nama','diterimaOleh:id,nama')
            ->when($this->search, fn($q,$s) => $q->where('nomor_gr','like',"%$s%"))
            ->when($this->filterStatus, fn($q,$st) => $q->where('status',$st))
            ->orderByDesc('tanggal_terima')->paginate($this->perPage);
    }

    public function verifikasi(int $id, PenerimaanService $service): void {
        try { $service->verifikasiGr(GoodsReceipt::with('items.barang')->findOrFail($id), auth()->id()); unset($this->gr); $this->dispatch('notify', type:'success', message:'GR diverifikasi. Stok & HPR diperbarui.'); }
        catch(\Exception $e) { $this->dispatch('notify', type:'error', message:$e->getMessage()); }
    }

    public function batalkan(int $id, PenerimaanService $service): void {
        try { $service->batalkanGr(GoodsReceipt::findOrFail($id)); unset($this->gr); $this->dispatch('notify', type:'success', message:'GR dibatalkan.'); }
        catch(\Exception $e) { $this->dispatch('notify', type:'error', message:$e->getMessage()); }
    }

    public function render() { return view('livewire.inventory.goods-receipt.gr-table'); }
}

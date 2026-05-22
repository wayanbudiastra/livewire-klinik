<?php
namespace App\Livewire\Inventory\AlertStok;
use App\Models\Barang;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class AlertStokTable extends Component
{
    use WithPagination;
    #[Url(as:'q')] public string $search='';
    #[Url] public string $filterJenis='';
    #[Url] public string $filterLevel='';
    public array $selected=[];
    public int $perPage=20;

    #[Computed]
    public function barangKritis() {
        return Barang::where('is_active',true)
            ->where(fn($q) => $q->whereRaw('stok <= stok_minimum * 1.5')->orWhere('stok',0))
            ->when($this->search, fn($q,$s) => $q->where('nama','like',"%$s%")->orWhere('kode','like',"%$s%"))
            ->when($this->filterJenis, fn($q,$j) => $q->where('jenis',$j))
            ->when($this->filterLevel==='habis', fn($q) => $q->where('stok',0))
            ->when($this->filterLevel==='kritis', fn($q) => $q->where('stok','>',0)->whereRaw('stok<=stok_minimum'))
            ->when($this->filterLevel==='hampir_habis', fn($q) => $q->whereRaw('stok>stok_minimum')->whereRaw('stok<=stok_minimum*1.5'))
            ->with('supplierUtama:id,nama')
            ->orderByRaw('CASE WHEN stok=0 THEN 0 WHEN stok<=stok_minimum THEN 1 ELSE 2 END')
            ->orderByRaw('stok/NULLIF(stok_minimum,0) ASC')
            ->paginate($this->perPage);
    }

    #[Computed]
    public function summary(): array {
        return [
            'total_habis' => Barang::where('is_active',true)->where('stok',0)->count(),
            'total_kritis' => Barang::where('is_active',true)->where('stok','>',0)->whereRaw('stok<=stok_minimum')->count(),
            'total_hampir_habis' => Barang::where('is_active',true)->whereRaw('stok>stok_minimum')->whereRaw('stok<=stok_minimum*1.5')->count(),
        ];
    }

    public function toggleSelect(int $barangId): void {
        if(in_array($barangId,$this->selected)) $this->selected=array_values(array_filter($this->selected,fn($id)=>$id!==$barangId));
        else $this->selected[]=$barangId;
    }

    public function render() { return view('livewire.inventory.alert-stok.alert-stok-table'); }
}

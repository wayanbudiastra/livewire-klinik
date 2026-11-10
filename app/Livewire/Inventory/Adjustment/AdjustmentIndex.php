<?php
namespace App\Livewire\Inventory\Adjustment;
use App\Models\AdjustmentStok;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
class AdjustmentIndex extends Component
{
    use WithPagination;
    #[Url] public string $tanggalMulai = '';
    #[Url] public string $tanggalAkhir = '';
    #[Url] public string $tipe = '';
    #[Url] public string $search = '';
    public function mount(): void { $this->tanggalMulai=now()->subMonths(3)->format('Y-m-d'); $this->tanggalAkhir=now()->format('Y-m-d'); }
    public function updated($property): void { if (in_array($property,['tanggalMulai','tanggalAkhir','tipe','search'])) $this->resetPage(); }
    public function render()
    {
        $term=trim($this->search);
        $adjustments=AdjustmentStok::with(['barang','user'])->when($this->tanggalMulai,fn($q)=>$q->where('created_at','>=',$this->tanggalMulai.' 00:00:00'))->when($this->tanggalAkhir,fn($q)=>$q->where('created_at','<=',$this->tanggalAkhir.' 23:59:59'))->when($this->tipe,fn($q)=>$q->where('tipe',$this->tipe))->when($term,fn($q)=>$q->where(function($query)use($term){$query->where('nomor_adjustment','like',"%{$term}%")->orWhereHas('barang',fn($barang)=>$barang->where('nama','like',"%{$term}%")->orWhere('kode','like',"%{$term}%"));}))->latest()->paginate(25);
        return view('livewire.inventory.adjustment.adjustment-index',compact('adjustments'));
    }
}

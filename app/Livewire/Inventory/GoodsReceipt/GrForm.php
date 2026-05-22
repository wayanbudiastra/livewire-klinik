<?php
namespace App\Livewire\Inventory\GoodsReceipt;
use App\Models\{PurchaseOrder, Supplier};
use App\Services\Inventory\PenerimaanService;
use Livewire\Component;

class GrForm extends Component
{
    public int $supplierId=0, $poId=0;
    public string $tanggalTerima='', $nomorFaktur='', $tanggalFaktur='', $nomorSuratJalan='', $catatan='';
    public array $items=[], $poTersedia=[];

    public function mount(): void { $this->tanggalTerima=now()->format('Y-m-d'); }

    public function updatedSupplierId(): void {
        $this->poTersedia=PurchaseOrder::where('supplier_id',$this->supplierId)->whereIn('status',['dikirim','sebagian'])->with('items.barang')->get()->toArray();
        $this->items=[]; $this->poId=0;
    }

    public function loadDariPo(int $poId): void {
        $po=PurchaseOrder::with('items.barang')->findOrFail($poId);
        $this->poId=$poId;
        $this->items=$po->items->filter(fn($i)=>$i->jumlah_pesan>$i->jumlah_diterima)
            ->map(fn($i)=>['barang_id'=>$i->barang_id,'po_item_id'=>$i->id,'nama_barang'=>$i->barang->nama,'satuan'=>$i->barang->satuan,'sisa_pesan'=>$i->jumlah_pesan-$i->jumlah_diterima,'jumlah_terima'=>$i->jumlah_pesan-$i->jumlah_diterima,'harga_satuan'=>(string)$i->harga_satuan,'diskon_persen'=>(string)$i->diskon_persen,'nomor_batch'=>'','expired_date'=>''])
            ->values()->toArray();
    }

    public function getTotalNilaiProperty(): float {
        return collect($this->items)->sum(fn($i)=>($i['jumlah_terima']??0)*($i['harga_satuan']??0)*(1-($i['diskon_persen']??0)/100));
    }

    public function simpan(PenerimaanService $service): void {
        $this->validate(['supplierId'=>'required|exists:supplier,id','tanggalTerima'=>'required|date|before_or_equal:today','items'=>'required|array|min:1','items.*.jumlah_terima'=>'required|integer|min:1','items.*.harga_satuan'=>'required|numeric|min:0']);
        $gr=$service->buatGr(['supplier_id'=>$this->supplierId,'purchase_order_id'=>$this->poId?:null,'tanggal_terima'=>$this->tanggalTerima,'nomor_faktur_supplier'=>$this->nomorFaktur?:null,'tanggal_faktur'=>$this->tanggalFaktur?:null,'nomor_surat_jalan'=>$this->nomorSuratJalan?:null,'catatan'=>$this->catatan?:null,'diterima_oleh'=>auth()->id(),'items'=>$this->items]);
        $this->dispatch('notify', type:'success', message:"GR {$gr->nomor_gr} berhasil disimpan.");
        $this->redirect(route('inventory.gr.index'));
    }

    public function simpanDanVerifikasi(PenerimaanService $service): void {
        $this->validate(['supplierId'=>'required|exists:supplier,id','tanggalTerima'=>'required|date|before_or_equal:today','items'=>'required|array|min:1','items.*.jumlah_terima'=>'required|integer|min:1','items.*.harga_satuan'=>'required|numeric|min:0']);
        $gr=$service->buatGr(['supplier_id'=>$this->supplierId,'purchase_order_id'=>$this->poId?:null,'tanggal_terima'=>$this->tanggalTerima,'nomor_faktur_supplier'=>$this->nomorFaktur?:null,'tanggal_faktur'=>$this->tanggalFaktur?:null,'nomor_surat_jalan'=>$this->nomorSuratJalan?:null,'catatan'=>$this->catatan?:null,'diterima_oleh'=>auth()->id(),'items'=>$this->items]);
        $service->verifikasiGr($gr, auth()->id());
        $this->dispatch('notify', type:'success', message:"GR {$gr->nomor_gr} diverifikasi. Stok & HPR diperbarui.");
        $this->redirect(route('inventory.gr.index'));
    }

    public function render() { return view('livewire.inventory.goods-receipt.gr-form',['suppliers'=>Supplier::active()->orderBy('nama')->get(),'totalNilai'=>$this->totalNilai]); }
}

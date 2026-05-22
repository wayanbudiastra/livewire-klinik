<?php
namespace App\Livewire\Inventory\Barang;
use App\Models\Barang;
use App\Models\Supplier;
use Illuminate\Validation\Rule;
use Livewire\Component;

class BarangForm extends Component
{
    public bool $showModal=false; public ?int $barangId=null; public bool $isEdit=false;
    public string $kode='', $nama='', $nama_generik='', $jenis='obat', $kategori='';
    public string $satuan='', $satuan_besar='', $kemasan='', $golongan='';
    public ?int $isi_satuan_besar=null, $stok_minimum=10, $stok_maksimum=null, $supplier_utama_id=null;
    public string $harga_pokok='0', $harga_jual='0';
    public bool $butuh_resep=false, $is_active=true;

    public function getRules(): array {
        $unique = $this->isEdit ? Rule::unique('barang','kode')->ignore($this->barangId) : 'unique:barang,kode';
        return [
            'kode'          => ['required','string','max:20',$unique],
            'nama'          => ['required','string','min:3'],
            'jenis'         => ['required','in:obat,alkes,bahan_habis_pakai,lainnya'],
            'satuan'        => ['required','string'],
            'stok_minimum'  => ['required','integer','min:0'],
            'harga_jual'    => ['required','numeric','min:0'],
        ];
    }

    public function openCreate(): void {
        $this->reset(['barangId','nama','nama_generik','kategori','satuan_besar','kemasan','golongan','supplier_utama_id']);
        $this->kode=Barang::generateKode(); $this->jenis='obat'; $this->satuan='';
        $this->stok_minimum=10; $this->stok_maksimum=null; $this->harga_pokok='0'; $this->harga_jual='0';
        $this->butuh_resep=false; $this->is_active=true; $this->isEdit=false; $this->showModal=true; $this->resetValidation();
    }

    public function openEdit(int $id): void {
        $b=Barang::findOrFail($id);
        $this->barangId=$id; $this->kode=$b->kode; $this->nama=$b->nama; $this->nama_generik=$b->nama_generik??'';
        $this->jenis=$b->jenis; $this->kategori=$b->kategori??''; $this->satuan=$b->satuan;
        $this->satuan_besar=$b->satuan_besar??''; $this->isi_satuan_besar=$b->isi_satuan_besar;
        $this->kemasan=$b->kemasan??''; $this->stok_minimum=$b->stok_minimum; $this->stok_maksimum=$b->stok_maksimum;
        $this->harga_pokok=(string)$b->harga_pokok; $this->harga_jual=(string)$b->harga_jual;
        $this->golongan=$b->golongan??''; $this->butuh_resep=(bool)$b->butuh_resep;
        $this->is_active=(bool)$b->is_active; $this->supplier_utama_id=$b->supplier_utama_id;
        $this->isEdit=true; $this->showModal=true; $this->resetValidation();
    }

    public function save(): void {
        $this->validate($this->getRules());
        $data=['kode'=>strtoupper($this->kode),'nama'=>$this->nama,'nama_generik'=>$this->nama_generik?:null,
               'jenis'=>$this->jenis,'kategori'=>$this->kategori?:null,'satuan'=>$this->satuan,
               'satuan_besar'=>$this->satuan_besar?:null,'isi_satuan_besar'=>$this->isi_satuan_besar,
               'kemasan'=>$this->kemasan?:null,'stok_minimum'=>$this->stok_minimum,'stok_maksimum'=>$this->stok_maksimum,
               'harga_pokok'=>(float)$this->harga_pokok,'harga_jual'=>(float)$this->harga_jual,
               'golongan'=>$this->golongan?:null,'butuh_resep'=>$this->butuh_resep,
               'is_active'=>$this->is_active,'supplier_utama_id'=>$this->supplier_utama_id];
        $this->isEdit ? Barang::findOrFail($this->barangId)->update($data) : Barang::create($data);
        $this->showModal=false;
        $this->dispatch('barang-saved');
        $this->dispatch('notify', type:'success', message:$this->isEdit?'Barang diupdate.':'Barang ditambahkan.');
    }

    public function getSupplierListProperty() { return Supplier::active()->orderBy('nama')->get(['id','nama','kode']); }
    public function render() { return view('livewire.inventory.barang.barang-form'); }
}

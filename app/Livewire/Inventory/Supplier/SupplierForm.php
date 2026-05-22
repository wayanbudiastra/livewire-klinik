<?php
namespace App\Livewire\Inventory\Supplier;
use App\Models\Supplier;
use App\Services\Inventory\SupplierService;
use Illuminate\Validation\Rule;
use Livewire\Component;

class SupplierForm extends Component
{
    public bool $showModal = false;
    public ?int $supplierId = null;
    public bool $isEdit = false;
    public string $kode='', $nama='', $tipe='distributor', $pic='';
    public string $telepon='', $email='', $alamat='', $npwp='';
    public int $lead_time_hari=3, $top_hari=30;

    public function getRules(): array {
        return [
            'kode'    => ['required','string','max:20', $this->isEdit ? Rule::unique('supplier','kode')->ignore($this->supplierId) : 'unique:supplier,kode'],
            'nama'    => ['required','string','max:150'],
            'tipe'    => ['required','in:distributor,prinsipal,apotek,lainnya'],
            'telepon' => ['nullable','string','max:20'],
            'email'   => ['nullable','email'],
            'lead_time_hari' => ['required','integer','min:0'],
            'top_hari' => ['required','integer','min:0'],
        ];
    }

    public function openCreate(SupplierService $service): void {
        $this->reset(['supplierId','nama','tipe','pic','telepon','email','alamat','npwp']);
        $this->kode = $service->generateKode();
        $this->lead_time_hari = 3; $this->top_hari = 30;
        $this->isEdit = false; $this->showModal = true; $this->resetValidation();
    }

    public function openEdit(int $id): void {
        $s = Supplier::findOrFail($id);
        $this->supplierId=$id; $this->kode=$s->kode; $this->nama=$s->nama;
        $this->tipe=$s->tipe; $this->pic=$s->pic??''; $this->telepon=$s->telepon??'';
        $this->email=$s->email??''; $this->alamat=$s->alamat??''; $this->npwp=$s->npwp??'';
        $this->lead_time_hari=$s->lead_time_hari; $this->top_hari=$s->top_hari;
        $this->isEdit=true; $this->showModal=true; $this->resetValidation();
    }

    public function save(SupplierService $service): void {
        $this->validate($this->getRules());
        $data = ['kode'=>strtoupper($this->kode),'nama'=>$this->nama,'tipe'=>$this->tipe,
                 'pic'=>$this->pic?:null,'telepon'=>$this->telepon?:null,'email'=>$this->email?:null,
                 'alamat'=>$this->alamat?:null,'npwp'=>$this->npwp?:null,
                 'lead_time_hari'=>$this->lead_time_hari,'top_hari'=>$this->top_hari];
        $this->isEdit ? $service->update(Supplier::findOrFail($this->supplierId), $data) : $service->create($data);
        $this->showModal = false;
        $this->dispatch('supplier-saved');
        $this->dispatch('notify', type:'success', message:$this->isEdit?'Supplier diupdate.':'Supplier ditambahkan.');
    }

    public function render() { return view('livewire.inventory.supplier.supplier-form'); }
}

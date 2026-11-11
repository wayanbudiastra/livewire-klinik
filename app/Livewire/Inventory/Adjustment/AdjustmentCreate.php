<?php
namespace App\Livewire\Inventory\Adjustment;
use App\Models\Barang;
use App\Services\Inventory\AdjustmentStokService;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;
class AdjustmentCreate extends Component
{
    use WithFileUploads;
    public ?int $barangId = null;
    public string $tipe = 'penyesuaian_masuk';
    public ?int $jumlah = null;
    public string $catatan = '';
    public string $buktiReferensi = '';
    public $fileAttachment;
    public string $searchBarang = '';
    public ?Barang $barangPreview = null;
    public ?int $stokSebelum = null;
    public ?int $stokSesudah = null;
    #[Computed] public function barangSuggestions()
    {
        if (strlen(trim($this->searchBarang)) < 2 || $this->barangId) return collect();
        return Barang::active()->search(trim($this->searchBarang))->select('id','kode','nama','satuan','stok','harga_pokok')->limit(10)->get();
    }
    public function updatedTipe(): void { $this->hitung(); }
    public function updatedJumlah(): void { $this->hitung(); }
    public function selectBarang(int $id): void
    {
        $barang = Barang::active()->findOrFail($id);
        $this->barangId = $barang->id; $this->searchBarang = $barang->nama; $this->barangPreview = $barang;
        $this->stokSebelum = (int) $barang->stok; $this->hitung();
    }
    public function resetBarang(): void { $this->reset(['barangId','searchBarang','barangPreview','stokSebelum','stokSesudah']); }
    private function hitung(): void
    {
        if ($this->stokSebelum === null || !$this->jumlah) { $this->stokSesudah = null; return; }
        $this->stokSesudah = $this->tipe === 'penyesuaian_masuk' ? $this->stokSebelum + $this->jumlah : $this->stokSebelum - $this->jumlah;
    }
    public function submit(AdjustmentStokService $service): void
    {
        abort_unless(auth()->user()?->can('adjustment.create'), 403);
        $this->validate(['barangId'=>'required|integer|exists:barang,id','tipe'=>'required|in:penyesuaian_masuk,penyesuaian_keluar','jumlah'=>'required|integer|min:1|max:999999','catatan'=>'required|string|min:10|max:2000','buktiReferensi'=>'nullable|string|max:255','fileAttachment'=>'nullable|file|max:5120|mimes:pdf,jpg,jpeg,png'], ['jumlah.min'=>'Jumlah harus minimal 1 unit.','catatan.min'=>'Catatan minimal 10 karakter.']);
        try { $service->buat(['barang_id'=>$this->barangId,'tipe'=>$this->tipe,'jumlah'=>$this->jumlah,'catatan'=>trim($this->catatan),'bukti_referensi'=>$this->buktiReferensi ?: null], auth()->id(), $this->fileAttachment); }
        catch (\DomainException $exception) { throw ValidationException::withMessages(['jumlah'=>$exception->getMessage()]); }
        session()->flash('success','Adjustment berhasil dibuat.'); $this->redirectRoute('inventory.adjustment.index');
    }
    public function render() { return view('livewire.inventory.adjustment.adjustment-create'); }
}

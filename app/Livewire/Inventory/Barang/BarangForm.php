<?php
namespace App\Livewire\Inventory\Barang;
use App\Models\Barang;
use App\Models\Supplier;
use App\Services\Harga\MarkupHargaService;
use Illuminate\Validation\Rule;
use Livewire\Component;

class BarangForm extends Component
{
    public bool $showModal=false; public ?int $barangId=null; public bool $isEdit=false;
    public string $kode='', $nama='', $nama_generik='', $jenis='obat', $kategori='';
    public string $satuan='', $satuan_besar='', $kemasan='', $golongan='';
    public ?int $isi_satuan_besar=null, $stok_minimum=10, $stok_maksimum=null, $supplier_utama_id=null;
    public string $harga_pokok='0', $harga_jual='0', $harga_wna='0';
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

    /** Obat & BHP ikut markup otomatis berbasis modal; Alkes/lainnya tetap manual. */
    public function getIkutMarkupOtomatisProperty(): bool {
        return in_array($this->jenis, ['obat', 'bahan_habis_pakai'], true);
    }

    public function openCreate(): void {
        $this->reset(['barangId','nama','nama_generik','kategori','satuan_besar','kemasan','golongan','supplier_utama_id']);
        $this->kode=Barang::generateKode(); $this->jenis='obat'; $this->satuan='';
        $this->stok_minimum=10; $this->stok_maksimum=null; $this->harga_pokok='0'; $this->harga_jual='0'; $this->harga_wna='0';
        $this->butuh_resep=false; $this->is_active=true; $this->isEdit=false; $this->showModal=true; $this->resetValidation();
    }

    public function openEdit(int $id): void {
        $b=Barang::findOrFail($id);
        $this->barangId=$id; $this->kode=$b->kode; $this->nama=$b->nama; $this->nama_generik=$b->nama_generik??'';
        $this->jenis=$b->jenis; $this->kategori=$b->kategori??''; $this->satuan=$b->satuan;
        $this->satuan_besar=$b->satuan_besar??''; $this->isi_satuan_besar=$b->isi_satuan_besar;
        $this->kemasan=$b->kemasan??''; $this->stok_minimum=$b->stok_minimum; $this->stok_maksimum=$b->stok_maksimum;
        $this->harga_pokok=(string)$b->harga_pokok; $this->harga_jual=(string)$b->harga_jual; $this->harga_wna=(string)($b->harga_wna ?? '0');
        $this->golongan=$b->golongan??''; $this->butuh_resep=(bool)$b->butuh_resep;
        $this->is_active=(bool)$b->is_active; $this->supplier_utama_id=$b->supplier_utama_id;
        $this->isEdit=true; $this->showModal=true; $this->resetValidation();
    }

    /**
     * Harga jual & WNA dihitung ULANG otomatis setiap kali harga modal
     * diketik ulang (utk jenis obat/BHP) -- masih bisa ditimpa manual
     * sesudahnya, tapi kalau modal diubah lagi, hasilnya dihitung ulang
     * lagi (bukan mempertahankan angka manual sebelumnya).
     */
    public function updatedHargaPokok(MarkupHargaService $service): void {
        if (! $this->ikutMarkupOtomatis) return;
        if ($this->harga_pokok === '' || ! is_numeric($this->harga_pokok)) return;

        $hasil = $service->hitung('obat_bhp', (float) $this->harga_pokok);
        if ($hasil['ktp'] === null) return;

        $this->harga_jual = (string) $hasil['ktp'];
        $this->harga_wna  = (string) $hasil['wna'];
    }

    public function updatedJenis(MarkupHargaService $service): void {
        // Pindah ke jenis obat/BHP dgn modal sudah terisi -> langsung hitungkan.
        $this->updatedHargaPokok($service);
    }

    public function save(): void {
        $this->validate($this->getRules());
        $data=['kode'=>strtoupper($this->kode),'nama'=>$this->nama,'nama_generik'=>$this->nama_generik?:null,
               'jenis'=>$this->jenis,'kategori'=>$this->kategori?:null,'satuan'=>$this->satuan,
               'satuan_besar'=>$this->satuan_besar?:null,'isi_satuan_besar'=>$this->isi_satuan_besar,
               'kemasan'=>$this->kemasan?:null,'stok_minimum'=>$this->stok_minimum,'stok_maksimum'=>$this->stok_maksimum,
               'harga_pokok'=>(float)$this->harga_pokok,'harga_jual'=>(float)$this->harga_jual,
               'harga_wna'=>$this->harga_wna!=='' && is_numeric($this->harga_wna) ? (float)$this->harga_wna : null,
               'golongan'=>$this->golongan?:null,'butuh_resep'=>$this->butuh_resep,
               'is_active'=>$this->is_active,'supplier_utama_id'=>$this->supplier_utama_id];

        $barang = $this->isEdit ? tap(Barang::findOrFail($this->barangId))->update($data) : Barang::create($data);

        $this->showModal=false;
        $this->dispatch('barang-saved');

        // Validasi harga (peringatan saja, TETAP disimpan -- lihat pola yg
        // sama di PenunjangForm) -- kalau harga jual di bawah harga modal,
        // berpotensi rugi. Digabung jadi 1 notify (bukan 2 dispatch
        // terpisah) supaya pesan sukses tidak langsung menimpa peringatan.
        if ((float) $this->harga_jual < (float) $this->harga_pokok) {
            activity('masterdata')
                ->performedOn($barang)
                ->causedBy(auth()->user())
                ->withProperties(['harga_jual' => $this->harga_jual, 'harga_pokok' => $this->harga_pokok])
                ->log("Harga jual \"{$barang->nama}\" disimpan DI BAWAH harga modal (berpotensi rugi)");

            $this->dispatch('notify', type: 'warning', message:
                ($this->isEdit ? 'Barang diupdate. ' : 'Barang ditambahkan. ')
                . "Perhatian: harga jual (Rp " . number_format((float) $this->harga_jual, 0, ',', '.')
                . ") di bawah harga modal (Rp " . number_format((float) $this->harga_pokok, 0, ',', '.') . ").");
            return;
        }

        $this->dispatch('notify', type:'success', message:$this->isEdit?'Barang diupdate.':'Barang ditambahkan.');
    }

    public function getSupplierListProperty() { return Supplier::active()->orderBy('nama')->get(['id','nama','kode']); }
    public function render() { return view('livewire.inventory.barang.barang-form'); }
}

<?php

namespace App\Livewire\Harga;

use App\Models\MasterTindakan;
use App\Services\Harga\ProposalHargaService;
use Livewire\Component;

class ProposalHargaForm extends Component
{
    public string $judul           = '';
    public int    $tahun;
    public string $tanggalEfektif  = '';
    public string $cakupan         = 'semua';
    public string $catatan         = '';
    public bool   $ikutBpjs        = false;

    /** Persen per jenis barang: obat / alkes / bahan_habis_pakai */
    public array $konfigBarang = [
        'obat'              => 0,
        'alkes'             => 0,
        'bahan_habis_pakai' => 0,
    ];

    /** Persen per kategori tindakan (diisi dinamis dari master) */
    public array $konfigTindakan = [];

    public function mount(): void
    {
        $this->tahun          = now()->addYear()->year;
        $this->tanggalEfektif = now()->addYear()->startOfYear()->format('Y-m-d');
        $this->loadKategoriTindakan();
    }

    public function updatedCakupan(): void
    {
        // no-op: Alpine handles visibility; PHP data stays intact
    }

    private function loadKategoriTindakan(): void
    {
        $kategori = MasterTindakan::aktif()
            ->distinct()
            ->orderBy('kategori')
            ->pluck('kategori')
            ->filter()
            ->values();

        $existing = $this->konfigTindakan;
        $this->konfigTindakan = [];
        foreach ($kategori as $k) {
            $this->konfigTindakan[$k] = $existing[$k] ?? 0;
        }
    }

    public function simpan(ProposalHargaService $service): void
    {
        $this->validate([
            'judul'          => 'required|string|max:200',
            'tahun'          => 'required|integer|min:2020|max:2099',
            'tanggalEfektif' => 'required|date|after:today',
            'cakupan'        => 'required|in:semua,tindakan,barang',
        ], [
            'tanggalEfektif.after' => 'Tanggal efektif harus lebih dari hari ini.',
        ]);

        $konfigKenaikan = $this->buildKonfig();

        try {
            $proposal = $service->buat([
                'judul'                => $this->judul,
                'tahun'                => $this->tahun,
                'tanggal_efektif'      => $this->tanggalEfektif,
                'cakupan'              => $this->cakupan,
                'catatan'              => $this->catatan ?: null,
                'konfigurasi_kenaikan' => $konfigKenaikan,
                'ikut_bpjs'            => $this->ikutBpjs,
            ], auth()->user());

            session()->flash('success', "Proposal \"{$proposal->judul}\" berhasil dibuat. " . $proposal->items()->count() . " item dimasukkan.");
            $this->redirect(route('harga.proposal.show', $proposal->id));
        } catch (\DomainException $e) {
            $this->addError('judul', $e->getMessage());
        }
    }

    private function buildKonfig(): array
    {
        $konfig = [];

        if (in_array($this->cakupan, ['semua', 'barang'])) {
            foreach ($this->konfigBarang as $jenis => $persen) {
                $konfig[$jenis] = (float) $persen;
            }
        }

        if (in_array($this->cakupan, ['semua', 'tindakan'])) {
            foreach ($this->konfigTindakan as $kategori => $persen) {
                $konfig[$kategori] = (float) $persen;
            }
        }

        return $konfig;
    }

    public function render()
    {
        return view('livewire.harga.proposal-harga-form');
    }
}

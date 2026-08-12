<?php

namespace App\Livewire\Pengaturan;

use App\Models\Barang;
use App\Models\ItemPenunjang;
use App\Models\KonfigurasiHargaWna;
use App\Models\MasterTindakan;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class HargaWna extends Component
{
    public string $markupPersen = '50';

    public function mount(): void
    {
        // Halaman ini khusus super_admin — bukan permission masterdata biasa,
        // supaya role lain (mis. admin/staff) yang punya masterdata.view/edit
        // tetap tidak bisa mengubah markup harga WNA.
        abort_unless(auth()->user()?->hasRole('super_admin'), 403);

        $this->markupPersen = (string) KonfigurasiHargaWna::config()->markup_persen;
    }

    /** Ringkasan berapa item yang sudah/belum punya harga WNA sendiri. */
    public function getRingkasanProperty(): array
    {
        return [
            'tindakan' => [
                'total'    => MasterTindakan::count(),
                'terisi'   => MasterTindakan::whereNotNull('tarif_wna')->count(),
            ],
            'penunjang' => [
                'total'    => ItemPenunjang::count(),
                'terisi'   => ItemPenunjang::whereNotNull('tarif_wna')->count(),
            ],
            'obat' => [
                'total'    => Barang::count(),
                'terisi'   => Barang::whereNotNull('harga_wna')->count(),
            ],
        ];
    }

    public function simpanMarkup(): void
    {
        abort_unless(auth()->user()?->hasRole('super_admin'), 403);

        $this->validate([
            'markupPersen' => ['required', 'numeric', 'min:0', 'max:1000'],
        ]);

        $config = KonfigurasiHargaWna::config();
        $config->markup_persen = (float) $this->markupPersen;
        $config->updated_by    = Auth::id();
        $config->save();

        KonfigurasiHargaWna::clearCache();

        $this->dispatch('notify', type: 'success', message: 'Markup harga WNA berhasil disimpan.');
    }

    /**
     * Isi tarif_wna/harga_wna HANYA untuk item yang masih kosong (null),
     * memakai markup yang sedang disimpan. Item yang sudah pernah diedit
     * manual (nilainya sudah terisi) TIDAK disentuh sama sekali.
     */
    public function terapkanKeSemua(): void
    {
        abort_unless(auth()->user()?->hasRole('super_admin'), 403);

        $markup = (float) KonfigurasiHargaWna::config()->markup_persen;
        $faktor = 1 + ($markup / 100);
        $total  = 0;

        // Ambil dulu baru update satu-satu (bukan query-builder each/chunk)
        // supaya aman dari efek "hasil menyusut saat di-update di tengah
        // chunking" — wajar untuk ukuran master data klinik yang tidak besar.
        foreach (MasterTindakan::whereNotNull('tarif')->whereNull('tarif_wna')->get() as $t) {
            $t->update(['tarif_wna' => round($t->tarif * $faktor)]);
            $total++;
        }

        foreach (ItemPenunjang::whereNotNull('tarif')->whereNull('tarif_wna')->get() as $p) {
            $p->update(['tarif_wna' => round($p->tarif * $faktor)]);
            $total++;
        }

        foreach (Barang::whereNotNull('harga_jual')->whereNull('harga_wna')->get() as $b) {
            $b->update(['harga_wna' => round($b->harga_jual * $faktor)]);
            $total++;
        }

        unset($this->ringkasan);

        $this->dispatch('notify', type: 'success',
            message: "{$total} item berhasil diisi harga WNA otomatis. Item yang sudah pernah diisi manual tidak diubah.");
    }

    public function render()
    {
        return view('livewire.pengaturan.harga-wna');
    }
}

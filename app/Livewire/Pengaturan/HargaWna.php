<?php

namespace App\Livewire\Pengaturan;

use App\Models\Barang;
use App\Models\ItemPenunjang;
use App\Models\KonfigurasiHargaWna;
use App\Models\KonfigurasiMarkupHarga;
use App\Models\MasterTindakan;
use App\Services\Harga\MarkupHargaService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class HargaWna extends Component
{
    // ── Markup manual (Tindakan, Radiologi, Alkes, lainnya) ──
    // 1 persen global, basis harga_jual/tarif saat ini, generator manual.
    public string $markupPersen = '50';

    // ── Markup otomatis (Obat & BHP, Lab) ─────────────────────
    // Basis harga_pokok/harga_modal, per kategori, diterapkan otomatis
    // begitu modal berubah (lihat MarkupHargaService).
    public string $markupObatBhpWna = '2.4';
    public string $markupObatBhpKtp = '1.6';
    public string $markupLabWna     = '2.0';
    public string $markupLabKtp     = '1.3';

    public function mount(): void
    {
        // Sebelumnya khusus super_admin (hasRole hardcode) -- sekarang
        // permission spt yang lain, supaya bisa dibagikan ke user tertentu
        // lewat "Hak Akses Tambahan" tanpa perlu jadi super_admin. Default
        // masih cuma super_admin (Gate::before) sampai dibagikan manual.
        $this->authorize('harga.markup.manage');

        $this->markupPersen = (string) KonfigurasiHargaWna::config()->markup_persen;

        $obatBhp = KonfigurasiMarkupHarga::untuk('obat_bhp');
        if ($obatBhp) {
            $this->markupObatBhpWna = (string) $obatBhp->multiplier_wna;
            $this->markupObatBhpKtp = (string) $obatBhp->multiplier_ktp;
        }

        $lab = KonfigurasiMarkupHarga::untuk('lab');
        if ($lab) {
            $this->markupLabWna = (string) $lab->multiplier_wna;
            $this->markupLabKtp = (string) $lab->multiplier_ktp;
        }
    }

    /** Ringkasan berapa item yang sudah/belum punya harga WNA sendiri -- KHUSUS jalur manual (Tindakan/Radiologi/Alkes/lainnya). */
    public function getRingkasanProperty(): array
    {
        return [
            'tindakan' => [
                'total'  => MasterTindakan::count(),
                'terisi' => MasterTindakan::whereNotNull('tarif_wna')->count(),
            ],
            'radiologi' => [
                'total'  => ItemPenunjang::radiologi()->count(),
                'terisi' => ItemPenunjang::radiologi()->whereNotNull('tarif_wna')->count(),
            ],
            'obat_manual' => [
                'total'  => Barang::whereNotIn('jenis', ['obat', 'bahan_habis_pakai'])->count(),
                'terisi' => Barang::whereNotIn('jenis', ['obat', 'bahan_habis_pakai'])->whereNotNull('harga_wna')->count(),
            ],
        ];
    }

    /** Ringkasan cakupan markup OTOMATIS (Obat & BHP, Lab). */
    public function getRingkasanOtomatisProperty(): array
    {
        return [
            'obat_bhp' => [
                'total'          => Barang::whereIn('jenis', ['obat', 'bahan_habis_pakai'])->count(),
                'punya_modal'    => Barang::whereIn('jenis', ['obat', 'bahan_habis_pakai'])
                                        ->where('harga_pokok', '>', 0)->count(),
            ],
            'lab' => [
                'total'       => ItemPenunjang::lab()->count(),
                'punya_modal' => ItemPenunjang::lab()->whereNotNull('harga_modal')->where('harga_modal', '>', 0)->count(),
            ],
        ];
    }

    /**
     * Isi tarif_wna/harga_wna HANYA untuk item yang masih kosong (null),
     * memakai markup yang sedang disimpan. Item yang sudah pernah diedit
     * manual (nilainya sudah terisi) TIDAK disentuh sama sekali.
     *
     * Obat & BHP dan Lab TIDAK ikut di sini -- keduanya sudah dihitung
     * otomatis lewat sistem markup berbasis modal (lihat
     * terapkanMarkupOtomatis()), supaya tidak dobel-governance.
     */
    public function terapkanKeSemua(): void
    {
        $this->authorize('harga.markup.manage');

        $markup = (float) KonfigurasiHargaWna::config()->markup_persen;
        $faktor = 1 + ($markup / 100);
        $total  = 0;

        foreach (MasterTindakan::whereNotNull('tarif')->whereNull('tarif_wna')->get() as $t) {
            $t->update(['tarif_wna' => round($t->tarif * $faktor)]);
            $total++;
        }

        foreach (ItemPenunjang::radiologi()->whereNotNull('tarif')->whereNull('tarif_wna')->get() as $p) {
            $p->update(['tarif_wna' => round($p->tarif * $faktor)]);
            $total++;
        }

        foreach (Barang::whereNotIn('jenis', ['obat', 'bahan_habis_pakai'])
            ->whereNotNull('harga_jual')->whereNull('harga_wna')->get() as $b) {
            $b->update(['harga_wna' => round($b->harga_jual * $faktor)]);
            $total++;
        }

        unset($this->ringkasan);

        $this->dispatch('notify', type: 'success',
            message: "{$total} item berhasil diisi harga WNA otomatis. Item yang sudah pernah diisi manual tidak diubah.");
    }

    public function simpanMarkup(): void
    {
        $this->authorize('harga.markup.manage');

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

    /** Simpan multiplier markup otomatis (Obat & BHP + Lab). TIDAK otomatis hitung ulang data existing -- pakai tombol terpisah. */
    public function simpanMarkupOtomatis(): void
    {
        $this->authorize('harga.markup.manage');

        $this->validate([
            'markupObatBhpWna' => ['required', 'numeric', 'min:1', 'max:50'],
            'markupObatBhpKtp' => ['required', 'numeric', 'min:1', 'max:50'],
            'markupLabWna'     => ['required', 'numeric', 'min:1', 'max:50'],
            'markupLabKtp'     => ['required', 'numeric', 'min:1', 'max:50'],
        ], [
            'markupObatBhpWna.min' => 'Multiplier minimal 1 (harga jual tidak boleh di bawah modal).',
            'markupObatBhpKtp.min' => 'Multiplier minimal 1 (harga jual tidak boleh di bawah modal).',
            'markupLabWna.min'     => 'Multiplier minimal 1 (tarif tidak boleh di bawah modal).',
            'markupLabKtp.min'     => 'Multiplier minimal 1 (tarif tidak boleh di bawah modal).',
        ]);

        KonfigurasiMarkupHarga::where('kategori', 'obat_bhp')->update([
            'multiplier_wna' => (float) $this->markupObatBhpWna,
            'multiplier_ktp' => (float) $this->markupObatBhpKtp,
            'updated_by'     => Auth::id(),
        ]);
        KonfigurasiMarkupHarga::where('kategori', 'lab')->update([
            'multiplier_wna' => (float) $this->markupLabWna,
            'multiplier_ktp' => (float) $this->markupLabKtp,
            'updated_by'     => Auth::id(),
        ]);

        KonfigurasiMarkupHarga::clearCache('obat_bhp');
        KonfigurasiMarkupHarga::clearCache('lab');

        $this->dispatch('notify', type: 'success',
            message: 'Multiplier markup otomatis disimpan. Klik "Hitung Ulang Semua Data" agar harga yang sudah ada ikut mengikuti angka baru.');
    }

    /**
     * Hitung ULANG harga jual & WNA SEMUA Obat/BHP (berbasis harga_pokok)
     * dan Lab (berbasis harga_modal) yang punya modal terisi, pakai
     * multiplier yang sedang tersimpan -- MENIMPA harga yang sudah ada
     * (beda dari terapkanKeSemua() yg cuma isi yang kosong), karena disini
     * memang tujuannya menyamakan semua data ke rumus terbaru.
     */
    public function hitungUlangSemua(MarkupHargaService $service): void
    {
        $this->authorize('harga.markup.manage');

        $hasil = $service->hitungUlangSemua();

        unset($this->ringkasanOtomatis);

        $this->dispatch('notify', type: 'success', message:
            "Selesai — {$hasil['obat_bhp']} item Obat/BHP dan {$hasil['lab']} item Lab dihitung ulang.");
    }

    public function render()
    {
        return view('livewire.pengaturan.harga-wna');
    }
}

<?php

namespace App\Livewire\Pemeriksaan;

use App\Models\Barang;
use App\Models\Kunjungan;
use App\Models\MasterTindakan;
use App\Models\PemakaianAlkes;
use App\Models\Tindakan;
use App\Models\User;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Prosedur extends Component
{
    public int $kunjunganId;

    // ── Form Tindakan ────────────────────────────────────────
    public string  $searchTindakan   = '';
    public ?int    $tindakanId       = null;
    public string  $tindakanNama     = '';
    public string  $tindakanTarif    = '0';
    public ?int    $pelaksanaId      = null;
    public int     $jumlahTindakan   = 1;
    public string  $waktuTindakan    = '';
    public string  $catatanTindakan  = '';

    // ── Form Alkes/BMHP ─────────────────────────────────────
    public string  $searchAlkes  = '';
    public ?int    $alkesId      = null;
    public string  $alkesNama    = '';
    public string  $alkesSatuan  = '';
    public int     $jumlahAlkes  = 1;
    public string  $catatanAlkes = '';

    // ────────────────────────────────────────────────────────
    public function mount(): void
    {
        $this->waktuTindakan = now()->format('Y-m-d\TH:i');
    }

    #[Computed]
    public function kunjungan()
    {
        return Kunjungan::with(['poli', 'pasien'])->find($this->kunjunganId);
    }

    #[Computed]
    public function suggestionsTindakan()
    {
        if (strlen($this->searchTindakan) < 2) return collect();

        $poliId = $this->kunjungan?->poli_id;
        $isWna  = $this->kunjungan?->pasien?->tipe_pasien === 'WNA';

        $q = MasterTindakan::aktif()
            ->where(function ($q) {
                $q->where('nama', 'like', '%'.$this->searchTindakan.'%')
                  ->orWhere('kode', 'like', '%'.$this->searchTindakan.'%');
            });

        // Filter by poli mapping jika poli tersedia
        if ($poliId) {
            $q->whereHas('poli', fn ($p) => $p->where('poli.id', $poliId));
        }

        $items = $q->limit(10)->get(['id', 'kode', 'nama', 'tarif', 'tarif_bpjs', 'tarif_wna', 'kategori']);

        return $items->map(function ($t) use ($isWna) {
            $t->tarif_aktif = \App\Services\Harga\TarifResolver::pilih(
                (float) $t->tarif,
                $t->tarif_bpjs !== null ? (float) $t->tarif_bpjs : null,
                $t->tarif_wna !== null ? (float) $t->tarif_wna : null,
                false,
                $isWna
            );
            return $t;
        });
    }

    #[Computed]
    public function suggestionsAlkes()
    {
        if (strlen($this->searchAlkes) < 2) return collect();

        // Tabel & model 'obat' sudah dihapus total sejak migrasi penggabungan
        // ke 'barang' (2026_05_27_300002) -- query ini masih menunjuk ke
        // Obat::class/tabel 'obat' yang sudah tidak ada sama sekali, jadi
        // SELALU gagal dgn SQLSTATE[42S02] begitu diketik >= 2 karakter
        // (laporan dokter: muncul Internal Server Error). Label field ini
        // "Alkes / BMHP" -- disamakan dgn jenis yg benar2 ada di Barang
        // (alkes & bahan_habis_pakai), bukan cuma 'alkes' spt kode lama
        // (peninggalan skema tabel 'obat' yg dulu tidak punya kategori BHP
        // terpisah).
        return Barang::aktif()
            ->whereIn('jenis', ['alkes', 'bahan_habis_pakai'])
            ->where(function ($q) {
                $q->where('nama',  'like', '%'.$this->searchAlkes.'%')
                  ->orWhere('kode', 'like', '%'.$this->searchAlkes.'%');
            })
            ->limit(10)
            ->get(['id', 'kode', 'nama', 'satuan', 'stok']);
    }

    #[Computed]
    public function pelaksanaOptions()
    {
        return User::active()
            ->orderBy('nama')
            ->get(['id', 'nama']);
    }

    #[Computed]
    public function riwayatTindakan()
    {
        return Tindakan::with(['masterTindakan', 'pelaksana:id,nama'])
            ->where('kunjungan_id', $this->kunjunganId)
            ->latest()
            ->get();
    }

    #[Computed]
    public function riwayatAlkes()
    {
        return PemakaianAlkes::with('barang')
            ->where('kunjungan_id', $this->kunjunganId)
            ->latest()
            ->get();
    }

    // ── Tindakan actions ─────────────────────────────────────
    public function pilihTindakan(int $id, string $nama, string $tarif): void
    {
        $this->tindakanId    = $id;
        $this->tindakanNama  = $nama;
        $this->tindakanTarif = $tarif;
        $this->searchTindakan = '';
        unset($this->suggestionsTindakan);
    }

    public function simpanTindakan(): void
    {
        if (! $this->tindakanId) {
            $this->dispatch('notify', type: 'error', message: 'Pilih tindakan terlebih dahulu.');
            return;
        }

        Tindakan::create([
            'kunjungan_id'       => $this->kunjunganId,
            'master_tindakan_id' => $this->tindakanId,
            'pelaksana_id'       => $this->pelaksanaId ?: null,
            'jumlah'             => $this->jumlahTindakan,
            'waktu_tindakan'     => $this->waktuTindakan
                                        ? \Carbon\Carbon::createFromFormat('Y-m-d\TH:i', $this->waktuTindakan)
                                        : now(),
            'catatan'            => $this->catatanTindakan ?: null,
        ]);

        $this->resetTindakanForm();
        unset($this->riwayatTindakan);
        $this->dispatch('notify', type: 'success', message: "Tindakan {$this->tindakanNama} berhasil ditambahkan.");
        $this->tindakanNama = '';
    }

    public function hapusTindakan(int $id): void
    {
        $row = Tindakan::where('id', $id)
            ->where('kunjungan_id', $this->kunjunganId)
            ->first();

        if (! $row) return;

        $row->delete();
        unset($this->riwayatTindakan);
        $this->dispatch('notify', type: 'success', message: 'Tindakan dihapus.');
    }

    // ── Alkes actions ────────────────────────────────────────
    public function pilihAlkes(int $id, string $nama, string $satuan): void
    {
        $this->alkesId     = $id;
        $this->alkesNama   = $nama;
        $this->alkesSatuan = $satuan;
        $this->searchAlkes = '';
        unset($this->suggestionsAlkes);
    }

    public function simpanAlkes(): void
    {
        if (! $this->alkesId) {
            $this->dispatch('notify', type: 'error', message: 'Pilih alat/BMHP terlebih dahulu.');
            return;
        }

        $barang = Barang::find($this->alkesId);
        if ($barang && $barang->stok > 0 && $barang->stok < $this->jumlahAlkes) {
            $this->dispatch('notify', type: 'warning',
                message: "Peringatan: stok {$barang->nama} hanya tersisa {$barang->stok}.");
        }

        // Kolom 'obat_id' di tabel pemakaian_alkes sudah DIHAPUS & diganti
        // 'barang_id' (NOT NULL) sejak migrasi yg sama -- sebelumnya create()
        // di sini masih kirim key 'obat_id' yg bukan lagi kolom asli maupun
        // $fillable PemakaianAlkes (lihat app/Models/PemakaianAlkes.php),
        // jadi diam2 DIABAIKAN Eloquent & insert selalu gagal krn barang_id
        // NOT NULL tidak pernah terisi.
        PemakaianAlkes::create([
            'kunjungan_id' => $this->kunjunganId,
            'barang_id'    => $this->alkesId,
            'jumlah'       => $this->jumlahAlkes,
            'catatan'      => $this->catatanAlkes ?: null,
        ]);

        $nama = $this->alkesNama;
        $this->resetAlkesForm();
        unset($this->riwayatAlkes);
        $this->dispatch('notify', type: 'success', message: "Alkes {$nama} berhasil dicatat.");
    }

    public function hapusAlkes(int $id): void
    {
        $row = PemakaianAlkes::where('id', $id)
            ->where('kunjungan_id', $this->kunjunganId)
            ->first();

        if (! $row) return;

        $row->delete();
        unset($this->riwayatAlkes);
        $this->dispatch('notify', type: 'success', message: 'Pemakaian alkes dihapus.');
    }

    // ── Helpers ──────────────────────────────────────────────
    private function resetTindakanForm(): void
    {
        $this->tindakanId      = null;
        $this->tindakanTarif   = '0';
        $this->jumlahTindakan  = 1;
        $this->catatanTindakan = '';
        $this->waktuTindakan   = now()->format('Y-m-d\TH:i');
        $this->searchTindakan  = '';
    }

    private function resetAlkesForm(): void
    {
        $this->alkesId      = null;
        $this->alkesNama    = '';
        $this->alkesSatuan  = '';
        $this->jumlahAlkes  = 1;
        $this->catatanAlkes = '';
        $this->searchAlkes  = '';
    }

    public function render()
    {
        return view('livewire.pemeriksaan.prosedur');
    }
}

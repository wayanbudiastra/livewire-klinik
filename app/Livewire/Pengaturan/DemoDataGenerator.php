<?php

namespace App\Livewire\Pengaturan;

use App\Services\Demo\DemoDataGeneratorService;
use Carbon\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Component;

class DemoDataGenerator extends Component
{
    // ── Form Generate ─────────────────────────────────────────────
    public string $dari            = '';
    public string $sampai          = '';
    public bool   $generatePoGrn   = true;
    public bool   $generateRitel   = true;
    public bool   $generateJurnal  = true;
    public int    $targetPoHarian  = 10_000_000;
    public int    $targetRitelHarian = 5_000_000;

    // ── State ─────────────────────────────────────────────────────
    public bool   $konfirmasiGanti = false;
    public ?array $konflik         = null;
    public ?array $hasil           = null;
    public array  $logs            = [];
    public ?string $errorMsg       = null;

    // ── Form Reset ────────────────────────────────────────────────
    public string $resetDari       = '';
    public string $resetSampai     = '';
    public string $konfirmasiHapus = '';
    public ?array $hasilHapus      = null;

    public function mount(): void
    {
        $this->dari   = now()->subMonth()->startOfMonth()->toDateString();
        $this->sampai = now()->subMonth()->startOfMonth()->addDays(9)->toDateString();
    }

    #[Computed]
    public function estimasi(): array
    {
        if (!$this->dari || !$this->sampai) return [];

        try {
            $d = Carbon::parse($this->dari);
            $s = Carbon::parse($this->sampai);
            if ($s->lt($d)) return [];

            $hari = $d->diffInDays($s) + 1;

            return [
                'hari'         => $hari,
                'jumlah_po'    => $this->generatePoGrn  ? $hari * 2 : 0,
                'jumlah_gr'    => $this->generatePoGrn  ? $hari * 2 : 0,
                'total_po'     => $this->generatePoGrn  ? $hari * $this->targetPoHarian : 0,
                'jumlah_trx'   => $this->generateRitel  ? $hari * 26 : 0,
                'total_ritel'  => $this->generateRitel  ? $hari * $this->targetRitelHarian : 0,
                'valid'        => $hari <= DemoDataGeneratorService::MAX_HARI,
            ];
        } catch (\Throwable) {
            return [];
        }
    }

    public function updatedDari(): void
    {
        $this->konflik  = null;
        $this->konfirmasiGanti = false;
        $this->hasil = null;
        $this->cekKonflik();
    }

    public function updatedSampai(): void
    {
        $this->konflik  = null;
        $this->konfirmasiGanti = false;
        $this->hasil = null;
        $this->cekKonflik();
    }

    private function cekKonflik(): void
    {
        if (!$this->dari || !$this->sampai) return;

        try {
            $d = Carbon::parse($this->dari);
            $s = Carbon::parse($this->sampai);
            if ($s->lt($d)) return;

            $svc = app(DemoDataGeneratorService::class);
            $this->konflik = $svc->cekKonflik($d, $s);
        } catch (\Throwable) {
            $this->konflik = null;
        }
    }

    public function generate(): void
    {
        $this->errorMsg = null;
        $this->hasil    = null;
        $this->logs     = [];

        // Validasi dasar
        if (!$this->generatePoGrn && !$this->generateRitel) {
            $this->errorMsg = 'Pilih minimal satu jenis data yang akan di-generate.';
            return;
        }

        try {
            $dari   = Carbon::parse($this->dari);
            $sampai = Carbon::parse($this->sampai);
        } catch (\Throwable) {
            $this->errorMsg = 'Format tanggal tidak valid.';
            return;
        }

        $logs = [];
        try {
            $svc = app(DemoDataGeneratorService::class);
            $hasil = $svc->generate(
                $dari,
                $sampai,
                [
                    'generate_po_grn'      => $this->generatePoGrn,
                    'generate_ritel'       => $this->generateRitel,
                    'generate_jurnal'      => $this->generateJurnal,
                    'target_po_harian'     => $this->targetPoHarian,
                    'target_ritel_harian'  => $this->targetRitelHarian,
                ],
                auth()->id(),
                function (array $log) use (&$logs) {
                    $logs[] = $log;
                }
            );

            $this->hasil          = $hasil;
            $this->logs           = $logs;
            $this->konfirmasiGanti = false;
            $this->konflik        = null;

            $this->dispatch('notify', type: 'success', message: 'Generate data demo berhasil!');
        } catch (\InvalidArgumentException $e) {
            $this->errorMsg = $e->getMessage();
        } catch (\Throwable $e) {
            $this->errorMsg = 'Terjadi kesalahan: ' . $e->getMessage();
        }
    }

    public function hapus(): void
    {
        $this->hasilHapus = null;

        if (strtoupper(trim($this->konfirmasiHapus)) !== 'HAPUS') {
            $this->dispatch('notify', type: 'error', message: 'Ketik HAPUS untuk konfirmasi.');
            return;
        }

        try {
            $dari   = Carbon::parse($this->resetDari);
            $sampai = Carbon::parse($this->resetSampai);
        } catch (\Throwable) {
            $this->dispatch('notify', type: 'error', message: 'Format tanggal reset tidak valid.');
            return;
        }

        try {
            $svc = app(DemoDataGeneratorService::class);
            $this->hasilHapus     = $svc->hapus($dari, $sampai);
            $this->konfirmasiHapus = '';
            $this->dispatch('notify', type: 'success', message: 'Data demo berhasil dihapus.');
        } catch (\Throwable $e) {
            $this->dispatch('notify', type: 'error', message: 'Gagal menghapus: ' . $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.pengaturan.demo-data-generator');
    }
}

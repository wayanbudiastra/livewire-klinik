<?php

namespace App\Livewire\Pemeriksaan;

use App\Models\Kunjungan;
use App\Services\KunjunganService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

class DetailPemeriksaan extends Component
{
    #[Url]
    public ?int $kunjunganId = null;

    public string $activeSection = 'identitas';

    // Vitals & asesmen
    public string $beratBadan    = '';
    public string $tinggiBadan   = '';
    public string $tekananDarah  = '';
    public string $nadi          = '';
    public string $suhu          = '';
    public string $saturasi      = '';
    public string $gds           = '';
    public string $anamnesisAwal = '';

    // Kunjungan fields
    public string $asalKedatangan = '';
    public string $catatanPenting = '';

    public function mount(): void
    {
        if ($this->kunjunganId) {
            $this->loadData();
        }
    }

    public function updatedKunjunganId(): void
    {
        $this->loadData();
        $this->activeSection = 'identitas';
    }

    private function loadData(): void
    {
        $k = Kunjungan::with('asesmenPerawat')->find($this->kunjunganId);
        if (! $k) return;

        $this->asalKedatangan = $k->asal_kedatangan ?? '';
        $this->catatanPenting = $k->catatan_penting ?? '';

        if ($k->asesmenPerawat) {
            $a = $k->asesmenPerawat;
            $this->beratBadan   = $a->berat_badan   ? (string) $a->berat_badan   : '';
            $this->tinggiBadan  = $a->tinggi_badan  ? (string) $a->tinggi_badan  : '';
            $this->tekananDarah = $a->tekanan_darah ?? '';
            $this->nadi         = $a->nadi          ? (string) $a->nadi          : '';
            $this->suhu         = $a->suhu          ? (string) $a->suhu          : '';
            $this->saturasi     = $a->saturasi      ? (string) $a->saturasi      : '';
            $this->gds          = $a->gds           ? (string) $a->gds           : '';
            $this->anamnesisAwal = $a->anamnesis_awal ?? '';
        }
    }

    #[Computed]
    public function kunjungan()
    {
        if (! $this->kunjunganId) return null;
        return Kunjungan::with([
            'pasien',
            'dokter.user:id,nama',
            'poli:id,nama',
            'asesmenPerawat',
            'appointment:id,kode_booking',
        ])->find($this->kunjunganId);
    }

    #[Computed]
    public function riwayatKunjungan()
    {
        if (! $this->kunjungan) return collect();
        return Kunjungan::with(['dokter.user:id,nama', 'poli:id,nama', 'asesmenPerawat'])
            ->where('pasien_id', $this->kunjungan->pasien_id)
            ->where('id', '!=', $this->kunjunganId)
            ->whereIn('status', ['selesai', 'dibatalkan'])
            ->latest('tanggal')
            ->limit(20)
            ->get();
    }

    public function getBmi(): ?float
    {
        $bb = (float) $this->beratBadan;
        $tb = (float) $this->tinggiBadan;
        if ($bb > 0 && $tb > 0) {
            $tbMeter = $tb / 100;
            return round($bb / ($tbMeter * $tbMeter), 1);
        }
        return null;
    }

    public function getBmiLabel(): string
    {
        $bmi = $this->getBmi();
        if ($bmi === null) return '';
        if ($bmi < 18.5) return 'Kurus';
        if ($bmi < 25.0) return 'Normal';
        if ($bmi < 30.0) return 'Gemuk';
        return 'Obesitas';
    }

    public function simpanVitals(KunjunganService $service): void
    {
        $this->validate([
            'beratBadan'  => 'nullable|numeric|min:1|max:500',
            'tinggiBadan' => 'nullable|numeric|min:1|max:300',
            'nadi'        => 'nullable|integer|min:1|max:300',
            'suhu'        => 'nullable|numeric|min:30|max:45',
            'saturasi'    => 'nullable|integer|min:1|max:100',
            'gds'         => 'nullable|numeric|min:0|max:9999',
        ]);

        $service->simpanAsesmen($this->kunjunganId, [
            'berat_badan'    => $this->beratBadan   ?: null,
            'tinggi_badan'   => $this->tinggiBadan  ?: null,
            'tekanan_darah'  => $this->tekananDarah ?: null,
            'nadi'           => $this->nadi         ?: null,
            'suhu'           => $this->suhu         ?: null,
            'saturasi'       => $this->saturasi     ?: null,
            'gds'            => $this->gds          ?: null,
            'anamnesis_awal' => $this->anamnesisAwal ?: null,
        ]);

        Kunjungan::where('id', $this->kunjunganId)->update([
            'asal_kedatangan' => $this->asalKedatangan ?: null,
            'catatan_penting' => $this->catatanPenting ?: null,
        ]);

        unset($this->kunjungan);
        $this->dispatch('notify', type: 'success', message: 'Data vital & asesmen berhasil disimpan.');
    }

    public function batalkanRegistrasi(KunjunganService $service): void
    {
        $service->cancelKunjungan($this->kunjunganId);
        unset($this->kunjungan);
        $this->dispatch('notify', type: 'success', message: 'Registrasi berhasil dibatalkan.');
    }

    public function selesaiPemeriksaan(KunjunganService $service): void
    {
        $service->selesaiPemeriksaan($this->kunjunganId);
        unset($this->kunjungan);
        $this->dispatch('notify', type: 'success', message: 'Pemeriksaan selesai. Pasien siap diperiksa dokter.');
    }

    public function render()
    {
        return view('livewire.pemeriksaan.detail-pemeriksaan');
    }
}

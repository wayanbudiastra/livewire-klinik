<?php

namespace App\Livewire\Pemeriksaan;

use App\Models\Dokter;
use App\Models\Kunjungan;
use App\Models\SuratKeterangan;
use App\Services\Pemeriksaan\SuratKeteranganService;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CetakSurat extends Component
{
    public int $kunjunganId;
    public bool $showModal = false;

    public string $tipe     = '';
    public ?int   $dokterId = null;

    // Keterangan Sehat
    public string $keperluan = '';

    // Keterangan Sakit
    public string $tanggalMulai      = '';
    public int    $lamaHari          = 1;
    public bool   $tampilkanDiagnosa = false;

    // Rujukan
    public string $tujuanFasilitas   = '';
    public string $tujuanDokter      = '';
    public string $indikasi          = '';
    public bool   $sertakanPenunjang = false;

    // Kontrol
    public string $tanggalKontrol = '';
    public string $instruksi      = '';

    public ?string $errorMsg = null;

    public function mount(int $kunjunganId): void
    {
        $this->kunjunganId    = $kunjunganId;
        $this->tanggalMulai   = now()->toDateString();
        $this->tanggalKontrol = now()->addDays(7)->toDateString();
    }

    #[Computed]
    public function kunjungan(): Kunjungan
    {
        return Kunjungan::with([
            'pasien', 'dokter', 'soapNote', 'asesmenPerawat', 'permintaanPenunjang',
        ])->findOrFail($this->kunjunganId);
    }

    #[Computed]
    public function dokterList()
    {
        return Dokter::aktifDanSipValid()->with('user:id,nama')->get();
    }

    #[Computed]
    public function riwayatSurat()
    {
        return SuratKeterangan::where('kunjungan_id', $this->kunjunganId)
            ->with(['dokter.user', 'dicetakOleh'])
            ->orderByDesc('dicetak_pada')
            ->get();
    }

    public function buka(string $tipe): void
    {
        $this->reset(['errorMsg', 'keperluan', 'tujuanFasilitas', 'tujuanDokter',
                      'indikasi', 'instruksi', 'tampilkanDiagnosa', 'sertakanPenunjang']);

        $this->tipe           = $tipe;
        $this->lamaHari       = 1;
        $this->tanggalMulai   = now()->toDateString();
        $this->tanggalKontrol = now()->addDays(7)->toDateString();
        $this->dokterId       = $this->kunjungan->dokter_id;

        $soap = $this->kunjungan->soapNote;
        if ($soap) {
            $this->indikasi  = $soap->a_problems ?? '';
            $this->instruksi = $soap->p_advice ?? '';
        }

        $this->showModal = true;
    }

    public function cetak(): StreamedResponse
    {
        $this->errorMsg = null;

        $rules = ['dokterId' => 'required|exists:dokter,id'];

        match ($this->tipe) {
            'keterangan_sakit' => $rules += [
                'tanggalMulai' => 'required|date',
                'lamaHari'     => 'required|integer|min:1|max:365',
            ],
            'rujukan' => $rules += [
                'tujuanFasilitas' => 'required|string|max:200',
                'indikasi'        => 'required|string|max:1000',
            ],
            'kontrol' => $rules += [
                'tanggalKontrol' => 'required|date|after:today',
            ],
            default => null,
        };

        $this->validate($rules, [
            'dokterId.required'        => 'Dokter penandatangan wajib dipilih.',
            'tanggalMulai.required'    => 'Tanggal mulai istirahat wajib diisi.',
            'lamaHari.min'             => 'Minimal 1 hari.',
            'tujuanFasilitas.required' => 'Tujuan fasilitas/RS wajib diisi.',
            'indikasi.required'        => 'Indikasi/alasan rujukan wajib diisi.',
            'tanggalKontrol.required'  => 'Tanggal kontrol wajib diisi.',
            'tanggalKontrol.after'     => 'Tanggal kontrol harus di masa mendatang.',
        ]);

        $service   = app(SuratKeteranganService::class);
        $kunjungan = $this->kunjungan;

        $input = [
            'dokter_id'           => $this->dokterId,
            'keperluan'           => $this->keperluan,
            'tanggal_mulai'       => $this->tanggalMulai,
            'lama_hari'           => $this->lamaHari,
            'tampilkan_diagnosa'  => $this->tampilkanDiagnosa,
            'tujuan_fasilitas'    => $this->tujuanFasilitas,
            'tujuan_dokter'       => $this->tujuanDokter,
            'indikasi'            => $this->indikasi,
            'sertakan_penunjang'  => $this->sertakanPenunjang,
            'tanggal_kontrol'     => $this->tanggalKontrol,
            'instruksi'           => $this->instruksi,
        ];

        $surat = match ($this->tipe) {
            'keterangan_sehat' => $service->simpanSehat($kunjungan, $input, auth()->id()),
            'keterangan_sakit' => $service->simpanSakit($kunjungan, $input, auth()->id()),
            'rujukan'          => $service->simpanRujukan($kunjungan, $input, auth()->id()),
            'kontrol'          => $service->simpanKontrol($kunjungan, $input, auth()->id()),
        };

        $pdfOutput = $service->pdfOutput($surat);
        $filename  = $surat->nomor_surat . '.pdf';

        unset($this->kunjungan, $this->riwayatSurat, $this->dokterList);
        $this->showModal = false;

        return response()->streamDownload(
            fn () => print($pdfOutput),
            $filename,
            ['Content-Type' => 'application/pdf']
        );
    }

    public function render()
    {
        return view('livewire.pemeriksaan.cetak-surat');
    }
}

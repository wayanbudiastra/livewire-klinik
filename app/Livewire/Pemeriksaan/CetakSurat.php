<?php

namespace App\Livewire\Pemeriksaan;

use App\Models\Dokter;
use App\Models\Kunjungan;
use App\Models\SuratKeterangan;
use App\Models\TujuanRujukan;
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

    /** Bahasa dokumen -- dipakai keterangan_sehat, keterangan_sakit, resume_medis. */
    public string $bahasa = 'id';

    // Keterangan Sehat
    public string $keperluan  = '';
    public string $butaWarna  = ''; // '', normal, parsial, total

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

    // Resume Medis
    public string $escorted         = '';
    public string $flight           = '';
    public string $recommendation   = '';
    public string $fasilitasBandara = '';

    public ?string $errorMsg = null;

    // ── Revisi surat yang sudah terbit (khusus dokter, wajib alasan) ─
    public ?int $editingSuratId  = null;
    public bool $showRevisiPrompt = false;
    public string $alasanRevisi   = '';

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
            ->with(['dokter.user', 'dicetakOleh', 'revisedBy'])
            ->orderByDesc('dicetak_pada')
            ->get();
    }

    /** Saran tujuan rujukan (combobox create-on-the-fly) berdasarkan ketikan user. */
    #[Computed]
    public function tujuanRujukanSuggestions()
    {
        if (strlen($this->tujuanFasilitas) < 2) return collect();
        return TujuanRujukan::aktif()
            ->where('nama', 'like', "%{$this->tujuanFasilitas}%")
            ->orderBy('nama')
            ->limit(10)
            ->get();
    }

    /**
     * Tipe surat yang sudah "terkunci" -- sudah pernah diterbitkan dan
     * kunjungan sudah Selesai, jadi tidak boleh diterbitkan ulang (lihat
     * SuratKeteranganService::assertBelumDiterbitkanJikaSelesai). Dipakai
     * untuk grey-out item dropdown supaya user tidak perlu buka modal dulu
     * baru tahu ditolak.
     */
    #[Computed]
    public function tipeTerkunci(): array
    {
        if ($this->kunjungan->status !== 'selesai') return [];

        // resume_medis sengaja dikecualikan -- SuratKeteranganService tidak
        // menggerbang tipe ini dgn aturan "sekali per tipe" (pasien/keluarga
        // sering perlu cetak ulang belakangan, lihat assertBelumDiterbitkanJikaSelesai()).
        return $this->riwayatSurat->pluck('tipe')->unique()
            ->reject(fn ($tipe) => $tipe === 'resume_medis')
            ->values()->all();
    }

    public function buka(string $tipe): void
    {
        $this->reset(['errorMsg', 'keperluan', 'butaWarna', 'tujuanFasilitas', 'tujuanDokter',
                      'indikasi', 'instruksi', 'tampilkanDiagnosa', 'sertakanPenunjang',
                      'escorted', 'flight', 'recommendation', 'fasilitasBandara',
                      'editingSuratId']);

        $this->bahasa         = 'id';
        $this->tipe           = $tipe;
        $this->lamaHari       = 1;
        $this->tanggalMulai   = now()->toDateString();
        $this->tanggalKontrol = now()->addDays(7)->toDateString();
        $this->dokterId       = $this->kunjungan->dokter_id;

        $soap = $this->kunjungan->soapNote;
        if ($soap) {
            // a_problems kolom lama (Assessment sudah direstrukturisasi jadi
            // Primary/Differential Diagnosis) -- fallback ke a_primary_diagnosis
            // supaya auto-fill indikasi rujukan tetap jalan utk SOAP baru.
            $this->indikasi  = $soap->a_problems ?? $soap->a_primary_diagnosis ?? '';
            $this->instruksi = $soap->p_advice ?? '';

            // Resume Medis: auto-isi Escorted/Recommendation dari pilihan
            // Escort/Transportation di SOAP Planning -- tetap bisa diedit manual di sini.
            if ($tipe === 'resume_medis') {
                $this->escorted       = $soap->label_escort ?? '';
                $this->recommendation = $soap->label_transportation ?? '';
            }
        }

        $this->showModal = true;
    }

    // ── Revisi surat yang sudah terbit ────────────────────────────

    public function mulaiEdit(int $suratId): void
    {
        $this->authorize('surat.revisi');

        $surat = SuratKeterangan::findOrFail($suratId);
        abort_unless($surat->kunjungan_id === $this->kunjunganId, 403);

        $this->editingSuratId  = $surat->id;
        $this->alasanRevisi    = '';
        $this->showRevisiPrompt = true;
    }

    public function batalPromptRevisi(): void
    {
        $this->showRevisiPrompt = false;
        $this->editingSuratId   = null;
        $this->alasanRevisi     = '';
    }

    /** Alasan sudah diisi -> muat data surat ke form yang sama dengan alur cetak baru. */
    public function konfirmasiRevisi(): void
    {
        $this->authorize('surat.revisi');

        $this->validate([
            'alasanRevisi' => 'required|string|min:5|max:500',
        ], [
            'alasanRevisi.required' => 'Alasan revisi wajib diisi.',
            'alasanRevisi.min'      => 'Alasan revisi minimal 5 karakter, tuliskan yang jelas.',
        ]);

        $surat = SuratKeterangan::findOrFail($this->editingSuratId);
        $d     = $surat->data ?? [];

        $this->reset(['errorMsg', 'keperluan', 'butaWarna', 'tujuanFasilitas', 'tujuanDokter',
                      'indikasi', 'instruksi', 'tampilkanDiagnosa', 'sertakanPenunjang',
                      'escorted', 'flight', 'recommendation', 'fasilitasBandara']);

        $this->tipe     = $surat->tipe;
        $this->dokterId = $surat->dokter_id;
        $this->bahasa   = $d['bahasa'] ?? 'id';

        match ($surat->tipe) {
            'keterangan_sehat' => $this->fill([
                'keperluan' => $d['keperluan'] ?? '', 'butaWarna' => $d['buta_warna'] ?? '',
            ]),
            'keterangan_sakit' => $this->fill([
                'tanggalMulai' => $d['tanggal_mulai'] ?? now()->toDateString(),
                'lamaHari'     => $d['lama_hari'] ?? 1,
                'tampilkanDiagnosa' => $d['tampilkan_diagnosa'] ?? false,
            ]),
            'rujukan' => $this->fill([
                'tujuanFasilitas'   => $d['tujuan_fasilitas'] ?? '',
                'tujuanDokter'      => $d['tujuan_dokter'] ?? '',
                'indikasi'          => $d['indikasi'] ?? '',
                'sertakanPenunjang' => !empty($d['penunjang_snapshot']),
            ]),
            'kontrol' => $this->fill([
                'tanggalKontrol' => $d['tanggal_kontrol'] ?? now()->addDays(7)->toDateString(),
                'instruksi'      => $d['instruksi'] ?? '',
            ]),
            'resume_medis' => $this->fill([
                'escorted' => $d['escorted'] ?? '', 'flight' => $d['flight'] ?? '',
                'recommendation' => $d['recommendation'] ?? '', 'fasilitasBandara' => $d['fasilitas_bandara'] ?? '',
            ]),
            default => null,
        };

        $this->showRevisiPrompt = false;
        $this->showModal        = true;
    }

    public function batalEdit(): void
    {
        $this->showModal      = false;
        $this->editingSuratId = null;
        $this->alasanRevisi   = '';
    }

    public function cetak(): ?StreamedResponse
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
            'buta_warna'          => $this->butaWarna,
            'tanggal_mulai'       => $this->tanggalMulai,
            'lama_hari'           => $this->lamaHari,
            'tampilkan_diagnosa'  => $this->tampilkanDiagnosa,
            'tujuan_fasilitas'    => $this->tujuanFasilitas,
            'tujuan_dokter'       => $this->tujuanDokter,
            'indikasi'            => $this->indikasi,
            'sertakan_penunjang'  => $this->sertakanPenunjang,
            'tanggal_kontrol'     => $this->tanggalKontrol,
            'instruksi'           => $this->instruksi,
            'bahasa'              => $this->bahasa,
            'escorted'            => $this->escorted,
            'flight'              => $this->flight,
            'recommendation'      => $this->recommendation,
            'fasilitas_bandara'   => $this->fasilitasBandara,
        ];

        try {
            if ($this->editingSuratId) {
                $surat = SuratKeterangan::findOrFail($this->editingSuratId);
                $surat = $service->editSurat($surat, $input, auth()->id(), $this->alasanRevisi);
            } else {
                $surat = match ($this->tipe) {
                    'keterangan_sehat' => $service->simpanSehat($kunjungan, $input, auth()->id()),
                    'keterangan_sakit' => $service->simpanSakit($kunjungan, $input, auth()->id()),
                    'rujukan'          => $service->simpanRujukan($kunjungan, $input, auth()->id()),
                    'kontrol'          => $service->simpanKontrol($kunjungan, $input, auth()->id()),
                    'resume_medis'     => $service->simpanResumeMedis($kunjungan, $input, auth()->id()),
                };
            }
        } catch (\RuntimeException $e) {
            $this->errorMsg = $e->getMessage();
            return null;
        }

        $pdfOutput = $service->pdfOutput($surat);
        $filename  = $surat->nomor_surat . '.pdf';

        unset($this->kunjungan, $this->riwayatSurat, $this->dokterList, $this->tujuanRujukanSuggestions);
        $this->showModal      = false;
        $this->editingSuratId = null;
        $this->alasanRevisi   = '';

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

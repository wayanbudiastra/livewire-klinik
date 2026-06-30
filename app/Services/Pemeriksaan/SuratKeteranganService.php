<?php

namespace App\Services\Pemeriksaan;

use App\Models\Dokter;
use App\Models\Kunjungan;
use App\Models\SuratKeterangan;
use Barryvdh\DomPDF\Facade\Pdf;

class SuratKeteranganService
{
    // ── Public: simpan record & return model ─────────────────

    public function simpanSehat(Kunjungan $kunjungan, array $input, int $userId): SuratKeterangan
    {
        $this->assertSoapFinal($kunjungan);

        $asesmen = $kunjungan->asesmenPerawat;
        $data    = [
            'keperluan'       => trim($input['keperluan'] ?? ''),
            'vitals_snapshot' => $asesmen ? [
                'tekanan_darah' => $asesmen->tekanan_darah,
                'nadi'          => $asesmen->nadi,
                'suhu'          => $asesmen->suhu,
                'berat_badan'   => $asesmen->berat_badan,
                'tinggi_badan'  => $asesmen->tinggi_badan,
                'bmi'           => $asesmen->bmi,
            ] : null,
        ];

        return $this->simpan($kunjungan, 'keterangan_sehat', (int) $input['dokter_id'], $data, $userId);
    }

    public function simpanSakit(Kunjungan $kunjungan, array $input, int $userId): SuratKeterangan
    {
        $this->assertSoapFinal($kunjungan);

        $mulai   = \Carbon\Carbon::parse($input['tanggal_mulai']);
        $lama    = (int) $input['lama_hari'];
        $selesai = $mulai->copy()->addDays($lama - 1);
        $soap    = $kunjungan->soapNote;

        $data = [
            'tanggal_mulai'      => $mulai->toDateString(),
            'lama_hari'          => $lama,
            'tanggal_selesai'    => $selesai->toDateString(),
            'tampilkan_diagnosa' => (bool) ($input['tampilkan_diagnosa'] ?? false),
            'diagnosa_snapshot'  => $soap?->icd_codes ?? [],
        ];

        return $this->simpan($kunjungan, 'keterangan_sakit', (int) $input['dokter_id'], $data, $userId);
    }

    public function simpanRujukan(Kunjungan $kunjungan, array $input, int $userId): SuratKeterangan
    {
        $this->assertSoapFinal($kunjungan);

        $soap = $kunjungan->soapNote;

        $penunjangSnapshot = [];
        if (!empty($input['sertakan_penunjang'])) {
            $penunjangSnapshot = $kunjungan->permintaanPenunjang()
                ->where('status', 'selesai')
                ->get()
                ->map(fn ($p) => [
                    'nama'    => $p->nama_pemeriksaan,
                    'tanggal' => $p->created_at?->toDateString(),
                ])
                ->toArray();
        }

        $data = [
            'tujuan_fasilitas'   => trim($input['tujuan_fasilitas']),
            'tujuan_dokter'      => trim($input['tujuan_dokter'] ?? ''),
            'indikasi'           => trim($input['indikasi']),
            'diagnosa_snapshot'  => $soap?->icd_codes ?? [],
            'penunjang_snapshot' => $penunjangSnapshot,
        ];

        return $this->simpan($kunjungan, 'rujukan', (int) $input['dokter_id'], $data, $userId);
    }

    public function simpanKontrol(Kunjungan $kunjungan, array $input, int $userId): SuratKeterangan
    {
        $this->assertSoapFinal($kunjungan);

        $soap            = $kunjungan->soapNote;
        $diagnosaPrimary = $soap?->icd_codes
            ? collect($soap->icd_codes)->firstWhere('is_primary', true)
            : null;

        $data = [
            'tanggal_kontrol'   => $input['tanggal_kontrol'],
            'instruksi'         => trim($input['instruksi'] ?? $soap?->p_advice ?? ''),
            'diagnosa_snapshot' => $diagnosaPrimary ? [$diagnosaPrimary] : [],
        ];

        return $this->simpan($kunjungan, 'kontrol', (int) $input['dokter_id'], $data, $userId);
    }

    // ── Public: render PDF dari surat yang sudah tersimpan ───

    public function pdfOutput(SuratKeterangan $surat): string
    {
        $surat->loadMissing(['kunjungan.pasien', 'kunjungan.asesmenPerawat',
                             'kunjungan.soapNote', 'dokter.user']);

        $view = 'surat.' . str_replace('_', '-', $surat->tipe);

        return Pdf::loadView($view, [
            'surat'     => $surat,
            'kunjungan' => $surat->kunjungan,
            'pasien'    => $surat->kunjungan->pasien,
            'dokter'    => $surat->dokter,
            'klinik'    => \App\Models\Klinik::profil(),
            'isCopy'    => false,
        ])->setPaper('a4', 'portrait')->output();
    }

    public function pdfOutputCopy(SuratKeterangan $surat): string
    {
        $surat->loadMissing(['kunjungan.pasien', 'kunjungan.asesmenPerawat',
                             'kunjungan.soapNote', 'dokter.user']);

        $view = 'surat.' . str_replace('_', '-', $surat->tipe);

        return Pdf::loadView($view, [
            'surat'     => $surat,
            'kunjungan' => $surat->kunjungan,
            'pasien'    => $surat->kunjungan->pasien,
            'dokter'    => $surat->dokter,
            'klinik'    => \App\Models\Klinik::profil(),
            'isCopy'    => true,
        ])->setPaper('a4', 'portrait')->output();
    }

    public function riwayat(Kunjungan $kunjungan)
    {
        return SuratKeterangan::where('kunjungan_id', $kunjungan->id)
            ->with(['dokter.user', 'dicetakOleh'])
            ->orderByDesc('dicetak_pada')
            ->get();
    }

    // ── Private helpers ──────────────────────────────────────

    private function assertSoapFinal(Kunjungan $kunjungan): void
    {
        $soap = $kunjungan->soapNote;
        if (!$soap || !$soap->is_final) {
            throw new \RuntimeException(
                'SOAP Note belum final. Finalisasi pemeriksaan terlebih dahulu sebelum menerbitkan surat.'
            );
        }
    }

    private function simpan(
        Kunjungan $kunjungan,
        string $tipe,
        int $dokterId,
        array $data,
        int $userId
    ): SuratKeterangan {
        return SuratKeterangan::create([
            'nomor_surat'  => SuratKeterangan::generateNomor($tipe),
            'kunjungan_id' => $kunjungan->id,
            'tipe'         => $tipe,
            'dokter_id'    => $dokterId,
            'data'         => $data,
            'dicetak_oleh' => $userId,
            'dicetak_pada' => now(),
        ]);
    }
}

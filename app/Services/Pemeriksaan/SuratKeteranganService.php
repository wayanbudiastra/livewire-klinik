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

    public function simpanResumeMedis(Kunjungan $kunjungan, array $input, int $userId): SuratKeterangan
    {
        $this->assertSoapFinal($kunjungan);

        $kunjungan->loadMissing([
            'asesmenPerawat',
            'soapNote',
            'tindakan.masterTindakan',
            'permintaanPenunjang.itemPenunjang',
            'resep.itemResep.barang',
            'resep.racikan.bahanRacikan.barang',
        ]);

        $asesmen = $kunjungan->asesmenPerawat;
        $soap    = $kunjungan->soapNote;

        $tindakanSnapshot = $kunjungan->tindakan->map(fn ($t) => [
            'nama'   => $t->masterTindakan->nama ?? '-',
            'jumlah' => $t->jumlah,
        ])->toArray();

        $penunjangSnapshot = $kunjungan->permintaanPenunjang->map(fn ($p) => [
            'nama'   => $p->itemPenunjang->nama ?? '-',
            'status' => $p->status,
        ])->toArray();

        $resepSnapshot = [];
        foreach ($kunjungan->resep->where('is_locked', true) as $resep) {
            foreach ($resep->itemResep as $ir) {
                $resepSnapshot[] = [
                    'kode'         => $ir->barang->kode ?? '-',
                    'nama'         => $ir->barang->nama ?? '-',
                    'aturan_pakai' => $ir->aturan_pakai ?? '-',
                    'jumlah'       => $ir->jumlah,
                    'satuan'       => $ir->barang->satuan ?? '-',
                ];
            }
            foreach ($resep->racikan as $racikan) {
                $resepSnapshot[] = [
                    'kode'         => '-',
                    'nama'         => $racikan->nama_racikan . ' (racikan)',
                    'aturan_pakai' => $racikan->aturan_pakai ?? '-',
                    'jumlah'       => 1,
                    'satuan'       => 'racikan',
                ];
            }
        }

        $data = [
            'bahasa'                    => in_array($input['bahasa'] ?? 'id', ['id', 'en']) ? $input['bahasa'] : 'id',
            'vitals_snapshot'           => $asesmen ? [
                'tekanan_darah' => $asesmen->tekanan_darah,
                'nadi'          => $asesmen->nadi,
                'suhu'          => $asesmen->suhu,
                'saturasi'      => $asesmen->saturasi,
                'berat_badan'   => $asesmen->berat_badan,
                'tinggi_badan'  => $asesmen->tinggi_badan,
                'bmi'           => $asesmen->bmi,
            ] : null,
            'anamnesis_snapshot'        => $asesmen?->anamnesis_awal,
            'subjektif_snapshot'        => $soap->subjektif ?? $soap->s_cc_hpi ?? null,
            'objektif_snapshot'         => $soap->objektif ?? $soap->o_physical_exam ?? null,
            'plan_snapshot'             => $soap->plan ?? $soap->p_advice ?? null,
            'diagnosa_snapshot'         => $soap->icd_codes ?? [],
            'tindakan_snapshot'         => $tindakanSnapshot,
            'penunjang_snapshot'        => $penunjangSnapshot,
            'resep_snapshot'            => $resepSnapshot,
            'escorted'                  => trim($input['escorted'] ?? ''),
            'flight'                    => trim($input['flight'] ?? ''),
            'recommendation'            => trim($input['recommendation'] ?? ''),
            'fasilitas_bandara'         => trim($input['fasilitas_bandara'] ?? ''),
        ];

        return $this->simpan($kunjungan, 'resume_medis', (int) $input['dokter_id'], $data, $userId);
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

    /**
     * Kunjungan yang sudah "selesai" statusnya dianggap final/terkunci -- tiap
     * tipe surat cuma boleh diterbitkan sekali per kunjungan supaya tidak ada
     * dokumen resmi bernomor ganda untuk hal yang sama. Selama kunjungan masih
     * "dalam_pemeriksaan", dokter tetap boleh menerbitkan ulang (mis. salah
     * tanggal/ralat) sebelum kunjungan ditutup.
     */
    private function assertBelumDiterbitkanJikaSelesai(Kunjungan $kunjungan, string $tipe): void
    {
        if ($kunjungan->status !== 'selesai') return;

        $sudahAda = SuratKeterangan::where('kunjungan_id', $kunjungan->id)
            ->where('tipe', $tipe)
            ->exists();

        if ($sudahAda) {
            throw new \RuntimeException(
                SuratKeterangan::labelTipe($tipe) . ' untuk kunjungan ini sudah pernah diterbitkan dan '
                . 'kunjungan sudah berstatus Selesai. Gunakan "Unduh Ulang" di Riwayat Surat Diterbitkan, '
                . 'bukan menerbitkan surat baru.'
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
        $this->assertBelumDiterbitkanJikaSelesai($kunjungan, $tipe);

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

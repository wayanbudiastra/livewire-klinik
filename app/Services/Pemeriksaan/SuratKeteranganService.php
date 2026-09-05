<?php

namespace App\Services\Pemeriksaan;

use App\Models\Dokter;
use App\Models\Kunjungan;
use App\Models\SuratKeterangan;
use App\Models\TujuanRujukan;
use Barryvdh\DomPDF\Facade\Pdf;

class SuratKeteranganService
{
    /** Kolom `data` yang boleh diedit lewat editSurat() -- sama untuk tiap tipe, lihat buildDataXxx(). */
    private const KOLOM_DATA_PER_TIPE = [
        'keterangan_sehat' => ['keperluan', 'buta_warna', 'bahasa'],
        'keterangan_sakit' => ['tanggal_mulai', 'lama_hari', 'tanggal_selesai', 'tampilkan_diagnosa', 'bahasa'],
        'rujukan'           => ['tujuan_fasilitas', 'tujuan_dokter', 'indikasi', 'penunjang_snapshot'],
        'kontrol'           => ['tanggal_kontrol', 'instruksi'],
        'resume_medis'      => ['bahasa', 'escorted', 'flight', 'recommendation', 'fasilitas_bandara'],
    ];

    // ── Public: simpan record & return model ─────────────────

    public function simpanSehat(Kunjungan $kunjungan, array $input, int $userId): SuratKeterangan
    {
        $this->assertSoapFinal($kunjungan);

        return $this->simpan($kunjungan, 'keterangan_sehat', (int) $input['dokter_id'], $this->buildDataSehat($kunjungan, $input), $userId);
    }

    public function simpanSakit(Kunjungan $kunjungan, array $input, int $userId): SuratKeterangan
    {
        $this->assertSoapFinal($kunjungan);

        return $this->simpan($kunjungan, 'keterangan_sakit', (int) $input['dokter_id'], $this->buildDataSakit($kunjungan, $input), $userId);
    }

    public function simpanRujukan(Kunjungan $kunjungan, array $input, int $userId): SuratKeterangan
    {
        $this->assertSoapFinal($kunjungan);

        $tujuan = $this->resolveTujuanRujukan($input['tujuan_fasilitas'] ?? '');

        return $this->simpan(
            $kunjungan, 'rujukan', (int) $input['dokter_id'],
            $this->buildDataRujukan($kunjungan, $input, $tujuan), $userId,
            $tujuan?->id
        );
    }

    public function simpanKontrol(Kunjungan $kunjungan, array $input, int $userId): SuratKeterangan
    {
        $this->assertSoapFinal($kunjungan);

        return $this->simpan($kunjungan, 'kontrol', (int) $input['dokter_id'], $this->buildDataKontrol($kunjungan, $input), $userId);
    }

    public function simpanResumeMedis(Kunjungan $kunjungan, array $input, int $userId): SuratKeterangan
    {
        $this->assertSoapFinal($kunjungan);

        return $this->simpan($kunjungan, 'resume_medis', (int) $input['dokter_id'], $this->buildDataResumeMedis($kunjungan, $input), $userId);
    }

    // ── Public: revisi surat yang sudah terbit ───────────────

    /**
     * Edit surat yang sudah terbit -- nomor_surat, dicetak_pada, dicetak_oleh
     * TIDAK berubah (identitas dokumen asli dipertahankan). Cuma field yang
     * memang diinput ulang dokter (lihat KOLOM_DATA_PER_TIPE) yang diperbarui;
     * snapshot lain (diagnosa_snapshot, vitals_snapshot, dst) TIDAK disentuh
     * supaya tetap merepresentasikan kondisi pasien saat kunjungan itu, bukan
     * kondisi terkini. Aturan otorisasi (dokter-only, wajib alasan) dicek di
     * Livewire component, bukan di sini -- service ini cuma eksekusi.
     */
    public function editSurat(SuratKeterangan $surat, array $input, int $userId, string $alasan): SuratKeterangan
    {
        $kunjungan = $surat->kunjungan;
        $lama      = $surat->data ?? [];

        $tujuan = null;
        $kolomBaru = match ($surat->tipe) {
            'keterangan_sehat' => $this->buildDataSehat($kunjungan, $input),
            'keterangan_sakit' => $this->buildDataSakit($kunjungan, $input),
            'rujukan'          => (function () use (&$tujuan, $kunjungan, $input) {
                $tujuan = $this->resolveTujuanRujukan($input['tujuan_fasilitas'] ?? '');
                return $this->buildDataRujukan($kunjungan, $input, $tujuan);
            })(),
            'kontrol'          => $this->buildDataKontrol($kunjungan, $input),
            'resume_medis'     => $this->buildDataResumeMedis($kunjungan, $input),
            default            => [],
        };

        // Cuma timpa kolom yang memang termasuk "editable" utk tipe ini --
        // sisanya (snapshot) dari data lama tetap dipertahankan apa adanya.
        $kolomEditable = self::KOLOM_DATA_PER_TIPE[$surat->tipe] ?? [];
        $dataBaru = $lama;
        foreach ($kolomEditable as $kolom) {
            if (array_key_exists($kolom, $kolomBaru)) {
                $dataBaru[$kolom] = $kolomBaru[$kolom];
            }
        }

        $surat->update([
            'dokter_id'         => (int) $input['dokter_id'],
            'tujuan_rujukan_id' => $surat->tipe === 'rujukan' ? $tujuan?->id : $surat->tujuan_rujukan_id,
            'data'              => $dataBaru,
            'revised_at'        => now(),
            'revised_by'        => $userId,
            'revision_count'    => $surat->revision_count + 1,
            'revision_reason'   => $alasan,
        ]);

        activity('surat_keterangan')
            ->performedOn($surat)
            ->causedBy(\App\Models\User::find($userId))
            ->withProperties(['sebelum' => $lama, 'sesudah' => $dataBaru, 'alasan' => $alasan])
            ->log('Surat Keterangan direvisi setelah terbit');

        return $surat->fresh();
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
            ->with(['dokter.user', 'dicetakOleh', 'revisedBy'])
            ->orderByDesc('dicetak_pada')
            ->get();
    }

    // ── Private: builder data per tipe (dipakai simpanXxx() & editSurat()) ──

    private function buildDataSehat(Kunjungan $kunjungan, array $input): array
    {
        $asesmen  = $kunjungan->asesmenPerawat;
        $bahasa   = $input['bahasa'] ?? 'id';
        $butaWarna = $input['buta_warna'] ?? '';

        return [
            'keperluan'       => trim($input['keperluan'] ?? ''),
            'buta_warna'      => in_array($butaWarna, ['normal', 'parsial', 'total'], true) ? $butaWarna : null,
            'bahasa'          => in_array($bahasa, ['id', 'en'], true) ? $bahasa : 'id',
            'vitals_snapshot' => $asesmen ? [
                'tekanan_darah' => $asesmen->tekanan_darah,
                'nadi'          => $asesmen->nadi,
                'suhu'          => $asesmen->suhu,
                'berat_badan'   => $asesmen->berat_badan,
                'tinggi_badan'  => $asesmen->tinggi_badan,
                'bmi'           => $asesmen->bmi,
            ] : null,
        ];
    }

    private function buildDataSakit(Kunjungan $kunjungan, array $input): array
    {
        $mulai   = \Carbon\Carbon::parse($input['tanggal_mulai']);
        $lama    = (int) $input['lama_hari'];
        $selesai = $mulai->copy()->addDays($lama - 1);
        $soap    = $kunjungan->soapNote;
        $bahasa  = $input['bahasa'] ?? 'id';

        return [
            'tanggal_mulai'      => $mulai->toDateString(),
            'lama_hari'          => $lama,
            'tanggal_selesai'    => $selesai->toDateString(),
            'tampilkan_diagnosa' => (bool) ($input['tampilkan_diagnosa'] ?? false),
            'bahasa'             => in_array($bahasa, ['id', 'en'], true) ? $bahasa : 'id',
            'diagnosa_snapshot'  => $soap?->icd_codes ?? [],
        ];
    }

    private function buildDataRujukan(Kunjungan $kunjungan, array $input, ?TujuanRujukan $tujuan): array
    {
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

        return [
            'tujuan_fasilitas'   => $tujuan?->nama ?? trim($input['tujuan_fasilitas'] ?? ''),
            'tujuan_dokter'      => trim($input['tujuan_dokter'] ?? ''),
            'indikasi'           => trim($input['indikasi'] ?? ''),
            'diagnosa_snapshot'  => $soap?->icd_codes ?? [],
            'penunjang_snapshot' => $penunjangSnapshot,
        ];
    }

    private function buildDataKontrol(Kunjungan $kunjungan, array $input): array
    {
        $soap            = $kunjungan->soapNote;
        $diagnosaPrimary = $soap?->icd_codes
            ? collect($soap->icd_codes)->firstWhere('is_primary', true)
            : null;

        return [
            'tanggal_kontrol'   => $input['tanggal_kontrol'],
            'instruksi'         => trim($input['instruksi'] ?? $soap?->p_advice ?? ''),
            'diagnosa_snapshot' => $diagnosaPrimary ? [$diagnosaPrimary] : [],
        ];
    }

    private function buildDataResumeMedis(Kunjungan $kunjungan, array $input): array
    {
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

        $bahasa = $input['bahasa'] ?? 'id';

        return [
            'bahasa'                    => in_array($bahasa, ['id', 'en'], true) ? $bahasa : 'id',
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
            // s_hpi (History of Present Illness) -- fallback ke kolom lama
            // s_cc_hpi (gabungan CC+HPI, sebelum dipisah) & subjektif utk
            // rekam medis lama.
            'subjektif_snapshot'        => $soap->s_hpi ?? $soap->subjektif ?? $soap->s_cc_hpi ?? null,
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
    }

    /**
     * Cari-atau-buat tujuan rujukan dari nama yang diketik user (combobox
     * create-on-the-fly) -- lihat prd/... fitur Surat Rujukan. Nama di-trim
     * & dibandingkan case-insensitive di level DB (kolom unique 'nama') supaya
     * tidak muncul duplikat "RSUP Sanglah" vs "rsup sanglah".
     */
    private function resolveTujuanRujukan(string $namaInput): ?TujuanRujukan
    {
        $nama = trim($namaInput);
        if ($nama === '') return null;

        $existing = TujuanRujukan::whereRaw('LOWER(nama) = ?', [mb_strtolower($nama)])->first();
        if ($existing) return $existing;

        return TujuanRujukan::create(['nama' => $nama, 'is_aktif' => true]);
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
     * tanggal/ralat) sebelum kunjungan ditutup. Setelah terbit, kalau memang
     * ada yang salah, pakai editSurat() (revisi), bukan terbitkan baru.
     */
    private function assertBelumDiterbitkanJikaSelesai(Kunjungan $kunjungan, string $tipe): void
    {
        if ($kunjungan->status !== 'selesai') return;
        if ($tipe === 'resume_medis') return;

        $sudahAda = SuratKeterangan::where('kunjungan_id', $kunjungan->id)
            ->where('tipe', $tipe)
            ->exists();

        if ($sudahAda) {
            throw new \RuntimeException(
                SuratKeterangan::labelTipe($tipe) . ' untuk kunjungan ini sudah pernah diterbitkan dan '
                . 'kunjungan sudah berstatus Selesai. Gunakan "Unduh Ulang" atau "Edit" di Riwayat Surat '
                . 'Diterbitkan, bukan menerbitkan surat baru.'
            );
        }
    }

    private function simpan(
        Kunjungan $kunjungan,
        string $tipe,
        int $dokterId,
        array $data,
        int $userId,
        ?int $tujuanRujukanId = null
    ): SuratKeterangan {
        $this->assertBelumDiterbitkanJikaSelesai($kunjungan, $tipe);

        return SuratKeterangan::create([
            'nomor_surat'       => SuratKeterangan::generateNomor($tipe),
            'kunjungan_id'      => $kunjungan->id,
            'tipe'              => $tipe,
            'dokter_id'         => $dokterId,
            'tujuan_rujukan_id' => $tujuanRujukanId,
            'data'              => $data,
            'dicetak_oleh'      => $userId,
            'dicetak_pada'      => now(),
        ]);
    }
}

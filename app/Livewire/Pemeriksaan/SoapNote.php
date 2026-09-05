<?php

namespace App\Livewire\Pemeriksaan;

use App\Models\AsesmenPerawat;
use App\Models\IcdDiagnosis;
use App\Models\Kunjungan;
use App\Models\SoapNote as SoapNoteModel;
use Livewire\Attributes\Computed;
use Livewire\Component;

class SoapNote extends Component
{
    /** Kolom isi SOAP yang dicatat before/after tiap revisi ke activity_log. */
    private const KOLOM_ISI = [
        's_chief_complaint', 's_hpi', 's_past_medical', 's_past_surgical', 's_allergies', 's_other',
        'o_physical_exam', 'o_supporting_examination',
        'icd_codes', 'a_primary_diagnosis', 'a_differential_diagnosis',
        'p_treatment', 'p_advice', 'p_transportation', 'p_escort', 'p_notes',
    ];

    public int $kunjunganId;

    public string $activeSection = 's';

    // ── Subjective ────────────────────────────────────────────
    /** Keluhan singkat -- default terisi dari asesmen_perawat.anamnesis_awal / kunjungan.keluhan, bisa diedit. */
    public string $sChiefComplaint = '';
    public string $sHpi           = '';
    public string $sPastMedical   = '';
    public string $sPastSurgical  = '';
    public string $sAllergies     = '';
    public string $sOther         = '';

    // ── Objective (vitals auto dari asesmen) ─────────────────
    public string $oPhysicalExam  = '';
    public string $oSupportingExamination = '';

    // ── Assessment ────────────────────────────────────────────
    public string $searchIcd      = '';
    public array  $diagnoses      = [];
    public string $aPrimaryDiagnosis      = '';
    public string $aDifferentialDiagnosis = '';

    // ── Planning ──────────────────────────────────────────────
    public string $pTreatment      = '';
    public string $pAdvice         = '';
    public string $pTransportation = ''; // '', fit_to_fly, not_fit_to_fly
    public string $pEscort         = ''; // '', medical_escort, non_medical_escort, no_escort
    public string $pNotes          = '';

    // ── State ─────────────────────────────────────────────────
    public bool   $isFinal        = false;
    public ?int   $soapId         = null;

    // ── Revisi (setelah finalisasi, khusus role dokter) ───────
    public bool   $sedangRevisi     = false;
    public bool   $showRevisiPrompt = false;
    public string $alasanRevisi     = '';

    public function mount(): void
    {
        $this->loadExisting();
        $this->autoFillAllergies();
        $this->autoFillChiefComplaint();
    }

    private function loadExisting(): void
    {
        $soap = SoapNoteModel::where('kunjungan_id', $this->kunjunganId)->first();
        if (! $soap) return;

        $this->soapId       = $soap->id;
        $this->isFinal      = $soap->is_final;

        $this->sChiefComplaint = $soap->s_chief_complaint ?? '';
        // s_hpi baru -- fallback ke s_cc_hpi (kolom lama, gabungan) utk
        // rekam medis yang dibuat sebelum field ini dipisah.
        $this->sHpi           = $soap->s_hpi ?? $soap->s_cc_hpi ?? '';
        $this->sPastMedical  = $soap->s_past_medical  ?? '';
        $this->sPastSurgical = $soap->s_past_surgical ?? '';
        $this->sAllergies    = $soap->s_allergies     ?? '';
        $this->sOther        = $soap->s_other         ?? '';

        $this->oPhysicalExam          = $soap->o_physical_exam         ?? '';
        $this->oSupportingExamination = $soap->o_supporting_examination ?? '';

        $this->diagnoses             = $soap->icd_codes              ?? [];
        $this->aPrimaryDiagnosis     = $soap->a_primary_diagnosis    ?? '';
        $this->aDifferentialDiagnosis = $soap->a_differential_diagnosis ?? '';

        $this->pTreatment       = $soap->p_treatment      ?? '';
        $this->pAdvice          = $soap->p_advice         ?? '';
        $this->pTransportation  = $soap->p_transportation ?? '';
        $this->pEscort          = $soap->p_escort         ?? '';
        $this->pNotes           = $soap->p_notes          ?? '';
    }

    private function autoFillAllergies(): void
    {
        if ($this->sAllergies) return;
        $kunjungan = Kunjungan::with('pasien:id,alergi')->find($this->kunjunganId);
        if ($kunjungan?->pasien?->alergi) {
            $this->sAllergies = $kunjungan->pasien->alergi;
        }
    }

    /**
     * Kalau dokter belum isi Chief Complaint, pre-fill dari rantai yang
     * sudah ada: Anamnesis/Keluhan Utama perawat (asesmen_perawat.
     * anamnesis_awal, yang sendiri sudah auto-terisi dari kunjungan.keluhan
     * saat pendaftaran -- lihat DetailPemeriksaan::loadData()) atau kalau
     * itu juga kosong, langsung dari kunjungan.keluhan. Tetap bisa diedit,
     * bukan field read-only.
     */
    private function autoFillChiefComplaint(): void
    {
        if ($this->sChiefComplaint) return;

        $kunjungan = Kunjungan::with('asesmenPerawat')->find($this->kunjunganId);
        $this->sChiefComplaint = $kunjungan?->asesmenPerawat?->anamnesis_awal
            ?: $kunjungan?->keluhan
            ?: '';
    }

    #[Computed]
    public function kunjungan()
    {
        return Kunjungan::with(['pasien:id,nama,alergi', 'asesmenPerawat'])->find($this->kunjunganId);
    }

    /** Model SoapNote tersimpan saat ini -- dipakai buat tampilkan info revisi (revised_by, revision_count, dst) di header. */
    #[Computed]
    public function soapModel(): ?SoapNoteModel
    {
        if (! $this->soapId) return null;
        return SoapNoteModel::with('revisedBy:id,nama')->find($this->soapId);
    }

    #[Computed]
    public function icdSuggestions()
    {
        if (strlen($this->searchIcd) < 2) return collect();
        return IcdDiagnosis::search($this->searchIcd);
    }

    #[Computed]
    public function vitals(): array
    {
        $a = $this->kunjungan?->asesmenPerawat;
        if (! $a) return [];

        $bmi = null;
        if ($a->berat_badan && $a->tinggi_badan) {
            $tb  = $a->tinggi_badan / 100;
            $bmi = round($a->berat_badan / ($tb * $tb), 1);
        }

        return array_filter([
            'BB'    => $a->berat_badan  ? $a->berat_badan.' kg'   : null,
            'TB'    => $a->tinggi_badan ? $a->tinggi_badan.' cm'  : null,
            'BMI'   => $bmi             ? (string) $bmi            : null,
            'TD'    => $a->tekanan_darah ?: null,
            'Nadi'  => $a->nadi         ? $a->nadi.' bpm'         : null,
            'Suhu'  => $a->suhu         ? $a->suhu.'°C'           : null,
            'SpO2'  => $a->saturasi     ? $a->saturasi.'%'        : null,
            'GDS'   => $a->gds          ? $a->gds.' mg/dL'        : null,
        ]);
    }

    /**
     * Ringkasan obat yang sudah diresepkan (dari modul Resep/Medication) --
     * read-only di sini, sesuai keputusan user: SOAP Planning cuma
     * menampilkan, bukan input ulang. Hanya resep yang sudah is_locked
     * (dikunci/di-charge) yang dihitung, sama seperti pola di
     * SuratKeteranganService::buildDataResumeMedis().
     */
    #[Computed]
    public function resepSummary()
    {
        $kunjungan = Kunjungan::with(['resep.itemResep.barang', 'resep.racikan.bahanRacikan.barang'])
            ->find($this->kunjunganId);

        $ringkasan = collect();
        foreach ($kunjungan?->resep->where('is_locked', true) ?? [] as $resep) {
            foreach ($resep->itemResep as $ir) {
                $ringkasan->push([
                    'nama'         => $ir->barang->nama ?? '-',
                    'aturan_pakai' => $ir->aturan_pakai ?? '-',
                    'jumlah'       => $ir->jumlah,
                    'satuan'       => $ir->barang->satuan ?? '-',
                ]);
            }
            foreach ($resep->racikan as $racikan) {
                $ringkasan->push([
                    'nama'         => $racikan->nama_racikan . ' (racikan)',
                    'aturan_pakai' => $racikan->aturan_pakai ?? '-',
                    'jumlah'       => 1,
                    'satuan'       => 'racikan',
                ]);
            }
        }

        return $ringkasan;
    }

    /** Pindah ke tab Medication di DetailPemeriksaan -- dipicu dari link "Kelola Resep" di Planning. */
    public function keTabMedication(): void
    {
        $this->dispatch('switch-section', section: 'obat');
    }

    /** Field sedang bisa diedit sekarang? (belum final, ATAU final tapi lagi mode revisi). */
    public function isLocked(): bool
    {
        return $this->isFinal && ! $this->sedangRevisi;
    }

    public function addDiagnosis(string $kode, string $nama): void
    {
        if ($this->isLocked()) return;

        // Cegah duplikat
        foreach ($this->diagnoses as $d) {
            if ($d['kode'] === $kode) {
                $this->searchIcd = '';
                return;
            }
        }

        $this->diagnoses[] = [
            'kode'       => $kode,
            'nama'       => $nama,
            'is_primary' => count($this->diagnoses) === 0,
        ];

        $this->searchIcd = '';
    }

    public function removeDiagnosis(int $index): void
    {
        if ($this->isLocked()) return;

        array_splice($this->diagnoses, $index, 1);

        // Pastikan selalu ada primary jika masih ada diagnosa
        if (count($this->diagnoses) > 0) {
            $hasPrimary = collect($this->diagnoses)->contains('is_primary', true);
            if (! $hasPrimary) {
                $this->diagnoses[0]['is_primary'] = true;
            }
        }
    }

    public function setPrimary(int $index): void
    {
        if ($this->isLocked()) return;

        foreach ($this->diagnoses as $i => $d) {
            $this->diagnoses[$i]['is_primary'] = ($i === $index);
        }
    }

    public function simpan(): void
    {
        if ($this->isFinal) return;
        $this->doSimpan();
        $this->dispatch('notify', type: 'success', message: 'SOAP Note berhasil disimpan.');
    }

    public function finalisasi(): void
    {
        if ($this->isFinal) return;
        $this->doSimpan();

        SoapNoteModel::where('id', $this->soapId)->update([
            'is_final'     => true,
            'finalized_at' => now(),
            'finalized_by' => auth()->id(),
        ]);

        $this->isFinal = true;
        $this->dispatch('notify', type: 'success', message: 'SOAP Note telah difinalisasi dan dikunci.');
    }

    /** Buka prompt alasan revisi. Cuma dokter (permission soap.revisi) yang bisa. */
    public function mulaiRevisi(): void
    {
        if (! $this->isFinal) return;
        $this->authorize('soap.revisi');

        $this->alasanRevisi     = '';
        $this->showRevisiPrompt = true;
    }

    public function batalPromptRevisi(): void
    {
        $this->showRevisiPrompt = false;
        $this->alasanRevisi     = '';
    }

    /** Alasan sudah diisi -> buka kunci field untuk diedit. Belum benar-benar disimpan. */
    public function konfirmasiRevisi(): void
    {
        if (! $this->isFinal) return;
        $this->authorize('soap.revisi');

        $this->validate([
            'alasanRevisi' => 'required|string|min:5|max:500',
        ], [
            'alasanRevisi.required' => 'Alasan revisi wajib diisi.',
            'alasanRevisi.min'      => 'Alasan revisi minimal 5 karakter, tuliskan yang jelas.',
        ]);

        $this->sedangRevisi     = true;
        $this->showRevisiPrompt = false;
    }

    /** Batalkan mode revisi -- buang perubahan yang belum disimpan, kembali ke data tersimpan. */
    public function batalRevisi(): void
    {
        $this->sedangRevisi = false;
        $this->alasanRevisi = '';
        $this->loadExisting();
        unset($this->kunjungan, $this->soapModel);
    }

    /** Simpan hasil revisi. Field is_final/finalized_at/finalized_by tidak berubah -- yang di-update cuma isi & jejak revisi. */
    public function simpanRevisi(): void
    {
        if (! $this->isFinal || ! $this->sedangRevisi) return;
        $this->authorize('soap.revisi');

        $sebelum = SoapNoteModel::find($this->soapId)?->only(self::KOLOM_ISI);

        $this->doSimpan();

        $soap = SoapNoteModel::find($this->soapId);
        $soap->update([
            'revised_at'       => now(),
            'revised_by'       => auth()->id(),
            'revision_count'   => $soap->revision_count + 1,
            'revision_reason'  => $this->alasanRevisi,
        ]);

        activity('soap_note')
            ->performedOn($soap)
            ->causedBy(auth()->user())
            ->withProperties([
                'sebelum' => $sebelum,
                'sesudah' => $soap->only(self::KOLOM_ISI),
                'alasan'  => $this->alasanRevisi,
            ])
            ->log('SOAP Note direvisi setelah finalisasi');

        $this->sedangRevisi = false;
        $this->alasanRevisi = '';
        $this->dispatch('notify', type: 'success', message: 'Revisi SOAP Note berhasil disimpan.');
    }

    private function doSimpan(): void
    {
        $this->validate([
            'diagnoses' => 'required|array|min:1',
        ], [
            'diagnoses.required' => 'Minimal satu diagnosa ICD-10 wajib diisi.',
            'diagnoses.min'      => 'Minimal satu diagnosa ICD-10 wajib diisi.',
        ]);

        $soap = SoapNoteModel::updateOrCreate(
            ['kunjungan_id' => $this->kunjunganId],
            [
                's_chief_complaint'        => $this->sChiefComplaint        ?: null,
                's_hpi'                    => $this->sHpi                   ?: null,
                's_past_medical'           => $this->sPastMedical           ?: null,
                's_past_surgical'          => $this->sPastSurgical          ?: null,
                's_allergies'              => $this->sAllergies             ?: null,
                's_other'                  => $this->sOther                 ?: null,
                'o_physical_exam'          => $this->oPhysicalExam          ?: null,
                'o_supporting_examination' => $this->oSupportingExamination ?: null,
                'icd_codes'                => $this->diagnoses,
                'a_primary_diagnosis'      => $this->aPrimaryDiagnosis      ?: null,
                'a_differential_diagnosis' => $this->aDifferentialDiagnosis ?: null,
                'p_treatment'              => $this->pTreatment             ?: null,
                'p_advice'                 => $this->pAdvice                ?: null,
                'p_transportation'         => $this->pTransportation        ?: null,
                'p_escort'                 => $this->pEscort                ?: null,
                'p_notes'                  => $this->pNotes                 ?: null,
            ]
        );

        $this->soapId = $soap->id;
        unset($this->kunjungan, $this->soapModel);
    }

    public function render()
    {
        return view('livewire.pemeriksaan.soap-note');
    }
}

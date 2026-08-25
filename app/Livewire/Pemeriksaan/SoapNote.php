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
        's_cc_hpi', 's_past_medical', 's_past_surgical', 's_allergies', 's_other',
        'o_physical_exam', 'o_systemic_exam', 'o_observation', 'o_other',
        'icd_codes', 'a_problems', 'a_progress_note', 'a_other',
        'p_advice', 'p_other',
    ];

    public int $kunjunganId;

    public string $activeSection = 's';

    // ── Subjective ────────────────────────────────────────────
    public string $sCcHpi         = '';
    public string $sPastMedical   = '';
    public string $sPastSurgical  = '';
    public string $sAllergies     = '';
    public string $sOther         = '';

    // ── Objective (vitals auto dari asesmen) ─────────────────
    public string $oPhysicalExam  = '';
    public string $oSystemicExam  = '';
    public string $oObservation   = '';
    public string $oOther         = '';

    // ── Assessment ────────────────────────────────────────────
    public string $searchIcd      = '';
    public array  $diagnoses      = [];
    public string $aProblems      = '';
    public string $aProgressNote  = '';
    public string $aOther         = '';

    // ── Planning ──────────────────────────────────────────────
    public string $pAdvice        = '';
    public string $pOther         = '';

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
    }

    private function loadExisting(): void
    {
        $soap = SoapNoteModel::where('kunjungan_id', $this->kunjunganId)->first();
        if (! $soap) return;

        $this->soapId       = $soap->id;
        $this->isFinal      = $soap->is_final;

        $this->sCcHpi        = $soap->s_cc_hpi       ?? '';
        $this->sPastMedical  = $soap->s_past_medical  ?? '';
        $this->sPastSurgical = $soap->s_past_surgical ?? '';
        $this->sAllergies    = $soap->s_allergies     ?? '';
        $this->sOther        = $soap->s_other         ?? '';

        $this->oPhysicalExam = $soap->o_physical_exam ?? '';
        $this->oSystemicExam = $soap->o_systemic_exam ?? '';
        $this->oObservation  = $soap->o_observation   ?? '';
        $this->oOther        = $soap->o_other         ?? '';

        $this->diagnoses     = $soap->icd_codes       ?? [];
        $this->aProblems     = $soap->a_problems      ?? '';
        $this->aProgressNote = $soap->a_progress_note ?? '';
        $this->aOther        = $soap->a_other         ?? '';

        $this->pAdvice       = $soap->p_advice        ?? '';
        $this->pOther        = $soap->p_other         ?? '';
    }

    private function autoFillAllergies(): void
    {
        if ($this->sAllergies) return;
        $kunjungan = Kunjungan::with('pasien:id,alergi')->find($this->kunjunganId);
        if ($kunjungan?->pasien?->alergi) {
            $this->sAllergies = $kunjungan->pasien->alergi;
        }
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
                's_cc_hpi'       => $this->sCcHpi        ?: null,
                's_past_medical'  => $this->sPastMedical  ?: null,
                's_past_surgical' => $this->sPastSurgical ?: null,
                's_allergies'     => $this->sAllergies    ?: null,
                's_other'         => $this->sOther        ?: null,
                'o_physical_exam' => $this->oPhysicalExam ?: null,
                'o_systemic_exam' => $this->oSystemicExam ?: null,
                'o_observation'   => $this->oObservation  ?: null,
                'o_other'         => $this->oOther        ?: null,
                'icd_codes'       => $this->diagnoses,
                'a_problems'      => $this->aProblems     ?: null,
                'a_progress_note' => $this->aProgressNote ?: null,
                'a_other'         => $this->aOther        ?: null,
                'p_advice'        => $this->pAdvice       ?: null,
                'p_other'         => $this->pOther        ?: null,
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

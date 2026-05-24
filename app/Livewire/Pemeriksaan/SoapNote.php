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

    public function addDiagnosis(string $kode, string $nama): void
    {
        if ($this->isFinal) return;

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
        if ($this->isFinal) return;

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
        if ($this->isFinal) return;

        foreach ($this->diagnoses as $i => $d) {
            $this->diagnoses[$i]['is_primary'] = ($i === $index);
        }
    }

    public function simpan(): void
    {
        if ($this->isFinal) return;

        $this->validate([
            'diagnoses' => 'required|array|min:1',
        ], [
            'diagnoses.required' => 'Minimal satu diagnosa ICD-10 wajib diisi.',
            'diagnoses.min'      => 'Minimal satu diagnosa ICD-10 wajib diisi.',
        ]);

        $data = [
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
        ];

        $soap = SoapNoteModel::updateOrCreate(
            ['kunjungan_id' => $this->kunjunganId],
            $data
        );

        $this->soapId = $soap->id;
        unset($this->kunjungan);
        $this->dispatch('notify', type: 'success', message: 'SOAP Note berhasil disimpan.');
    }

    public function finalisasi(): void
    {
        if (! $this->soapId) {
            $this->simpan();
        }

        if (count($this->diagnoses) === 0) {
            $this->addError('diagnoses', 'Diagnosa ICD-10 wajib diisi sebelum finalisasi.');
            return;
        }

        SoapNoteModel::where('id', $this->soapId)->update([
            'is_final'     => true,
            'finalized_at' => now(),
            'finalized_by' => auth()->id(),
        ]);

        $this->isFinal = true;
        $this->dispatch('notify', type: 'success', message: 'SOAP Note telah difinalisasi dan dikunci.');
    }

    public function render()
    {
        return view('livewire.pemeriksaan.soap-note');
    }
}

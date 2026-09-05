<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SoapNote extends Model
{
    protected $table = 'soap_note';

    protected $fillable = [
        'kunjungan_id',
        // Subjective
        'subjektif', 's_chief_complaint', 's_hpi', 's_cc_hpi', 's_past_medical', 's_past_surgical', 's_allergies', 's_other',
        // Objective
        'objektif', 'o_physical_exam', 'o_supporting_examination',
        'o_systemic_exam', 'o_observation', 'o_other',
        // Assessment
        'asesmen', 'icd_codes', 'a_primary_diagnosis', 'a_differential_diagnosis',
        'a_problems', 'a_progress_note', 'a_other',
        // Planning
        'plan', 'p_advice', 'p_treatment', 'p_transportation', 'p_escort', 'p_notes', 'p_other',
        // Status
        'is_final', 'finalized_at', 'finalized_by',
        // Revisi (setelah finalisasi -- lihat app/Livewire/Pemeriksaan/SoapNote.php)
        'revised_at', 'revised_by', 'revision_count', 'revision_reason',
    ];

    protected function casts(): array
    {
        return [
            'icd_codes'    => 'array',
            'is_final'     => 'boolean',
            'finalized_at' => 'datetime',
            'revised_at'   => 'datetime',
        ];
    }

    public function kunjungan()
    {
        return $this->belongsTo(Kunjungan::class);
    }

    public function finalizedBy()
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    public function revisedBy()
    {
        return $this->belongsTo(User::class, 'revised_by');
    }

    public function getDiagnosisUtamaAttribute(): ?array
    {
        if (! $this->icd_codes) return null;
        return collect($this->icd_codes)->firstWhere('is_primary', true);
    }

    public function getDiagnosisLainnyaAttribute(): array
    {
        if (! $this->icd_codes) return [];
        return collect($this->icd_codes)->where('is_primary', false)->values()->toArray();
    }

    /** Opsi dropdown Transportation di Planning -- dipakai form & auto-fill Resume Medis. */
    public static function opsiTransportation(): array
    {
        return [
            'fit_to_fly'     => 'Fit to Fly',
            'not_fit_to_fly' => 'Not Fit to Fly',
        ];
    }

    /** Opsi dropdown Escort di Planning -- dipakai form & auto-fill Resume Medis. */
    public static function opsiEscort(): array
    {
        return [
            'medical_escort'     => 'Medical Escort',
            'non_medical_escort' => 'Non Medical Escort',
            'no_escort'          => 'No Escort',
        ];
    }

    public function getLabelTransportationAttribute(): ?string
    {
        return $this->p_transportation ? (self::opsiTransportation()[$this->p_transportation] ?? $this->p_transportation) : null;
    }

    public function getLabelEscortAttribute(): ?string
    {
        return $this->p_escort ? (self::opsiEscort()[$this->p_escort] ?? $this->p_escort) : null;
    }
}

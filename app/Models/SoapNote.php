<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SoapNote extends Model
{
    protected $table = 'soap_note';

    protected $fillable = [
        'kunjungan_id',
        // Subjective
        'subjektif', 's_cc_hpi', 's_past_medical', 's_past_surgical', 's_allergies', 's_other',
        // Objective
        'objektif', 'o_physical_exam', 'o_systemic_exam', 'o_observation', 'o_other',
        // Assessment
        'asesmen', 'icd_codes', 'a_problems', 'a_progress_note', 'a_other',
        // Planning
        'plan', 'p_advice', 'p_other',
        // Status
        'is_final', 'finalized_at', 'finalized_by',
    ];

    protected function casts(): array
    {
        return [
            'icd_codes'    => 'array',
            'is_final'     => 'boolean',
            'finalized_at' => 'datetime',
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
}

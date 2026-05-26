<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PasienAsuransi extends Model
{
    protected $table    = 'pasien_asuransi';
    protected $fillable = [
        'pasien_id', 'asuransi_id', 'nomor_polis', 'nama_pemegang_polis',
        'berlaku_mulai', 'berlaku_sampai', 'is_primary', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'berlaku_mulai'  => 'date',
            'berlaku_sampai' => 'date',
            'is_primary'     => 'boolean',
            'is_active'      => 'boolean',
        ];
    }

    public function pasien():   BelongsTo { return $this->belongsTo(Pasien::class); }
    public function asuransi(): BelongsTo { return $this->belongsTo(Asuransi::class); }

    public function getIsAktifAttribute(): bool
    {
        if (!$this->berlaku_sampai) return $this->is_active;
        return $this->is_active && $this->berlaku_sampai->isFuture();
    }
}

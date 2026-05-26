<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Asuransi extends Model
{
    protected $table    = 'asuransi';
    protected $fillable = [
        'kode', 'nama', 'tipe', 'periode_mulai', 'periode_berakhir',
        'cover_prosedur', 'cover_laboratorium', 'cover_radiologi', 'cover_peralatan',
        'plafon_per_kunjungan', 'plafon_per_tahun',
        'pic', 'telepon', 'email', 'alamat', 'term_pembayaran_hari', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'periode_mulai'    => 'date',
            'periode_berakhir' => 'date',
            'is_active'        => 'boolean',
            'cover_prosedur'     => 'float',
            'cover_laboratorium' => 'float',
            'cover_radiologi'    => 'float',
            'cover_peralatan'    => 'float',
        ];
    }

    public function pasien(): BelongsToMany
    {
        return $this->belongsToMany(Pasien::class, 'pasien_asuransi')
                    ->withPivot(['nomor_polis', 'is_primary', 'is_active'])
                    ->withTimestamps();
    }

    public function pasienAsuransi(): HasMany
    {
        return $this->hasMany(PasienAsuransi::class);
    }

    public function piutang(): HasMany
    {
        return $this->hasMany(PiutangAsuransi::class);
    }

    public function penagihan(): HasMany
    {
        return $this->hasMany(PenagihanAsuransi::class);
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public function getIsBerlakuAttribute(): bool
    {
        if (!$this->periode_berakhir) return true;
        return $this->periode_berakhir->isFuture();
    }

    public function getTipeLabelAttribute(): string
    {
        return match ($this->tipe) {
            'swasta'     => 'Swasta',
            'bpjs'       => 'BPJS',
            'pemerintah' => 'Pemerintah',
            'corporate'  => 'Corporate',
            default      => $this->tipe,
        };
    }
}

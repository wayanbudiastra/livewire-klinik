<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class PemakaianBhp extends Model
{
    protected $table    = 'pemakaian_bhp';
    protected $fillable = [
        'nomor_bhp', 'dicatat_oleh', 'diverifikasi_oleh',
        'tanggal_pemakaian', 'status', 'catatan',
    ];

    protected function casts(): array
    {
        return ['tanggal_pemakaian' => 'date'];
    }

    public function pencatat():    BelongsTo { return $this->belongsTo(User::class, 'dicatat_oleh'); }
    public function verifikator(): BelongsTo { return $this->belongsTo(User::class, 'diverifikasi_oleh'); }
    public function items():       HasMany   { return $this->hasMany(PemakaianBhpItem::class); }

    public function getTotalNilaiAttribute(): float
    {
        return (float) $this->items->sum('nilai_total');
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'draft'      => 'Draft',
            'selesai'    => 'Selesai',
            'dibatalkan' => 'Dibatalkan',
            default      => $this->status,
        };
    }
}

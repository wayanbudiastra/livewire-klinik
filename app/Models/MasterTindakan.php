<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MasterTindakan extends Model
{
    protected $table = 'master_tindakan';

    protected $fillable = [
        'kode', 'nama', 'deskripsi', 'tarif', 'tarif_bpjs', 'tarif_wna',
        'kategori', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'tarif'      => 'decimal:2',
            'tarif_bpjs' => 'decimal:2',
            'tarif_wna'  => 'decimal:2',
            'is_active'  => 'boolean',
        ];
    }

    public function poli()
    {
        return $this->belongsToMany(Poli::class, 'tindakan_poli');
    }

    public function tindakan()
    {
        return $this->hasMany(Tindakan::class);
    }

    public function scopeUntukPoli($query, int $poliId)
    {
        return $query->whereHas('poli', fn ($q) => $q->where('poli.id', $poliId));
    }

    public function scopeAktif($query)
    {
        return $query->where('is_active', true);
    }

    public static function generateKode(): string
    {
        $last = static::where('kode', 'like', 'TND-%')
            ->whereRaw("kode REGEXP '^TND-[0-9]{4}$'")
            ->orderByRaw('CAST(SUBSTRING(kode, 5) AS UNSIGNED) DESC')
            ->value('kode');
        $seq = $last ? ((int) substr($last, 4)) + 1 : 1;
        return 'TND-' . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }
}

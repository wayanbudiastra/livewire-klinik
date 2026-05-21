<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MasterTindakan extends Model
{
    protected $table = 'master_tindakan';

    protected $fillable = [
        'kode', 'nama', 'deskripsi', 'tarif', 'tarif_bpjs',
        'kategori', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'tarif'      => 'decimal:2',
            'tarif_bpjs' => 'decimal:2',
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
        return $query->where('kategori', 'tindakan')
                     ->whereHas('poli', fn ($q) => $q->where('poli.id', $poliId));
    }

    public function scopeAktif($query)
    {
        return $query->where('is_active', true);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItemPenunjang extends Model
{
    protected $table = 'item_penunjang';

    protected $fillable = [
        'kode', 'nama', 'deskripsi', 'kategori',
        'tarif', 'tarif_bpjs', 'satuan_waktu', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'tarif'      => 'decimal:2',
            'tarif_bpjs' => 'decimal:2',
            'is_active'  => 'boolean',
        ];
    }

    public function permintaan()
    {
        return $this->hasMany(PermintaanPenunjang::class);
    }

    public function scopeAktif($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeLab($query)
    {
        return $query->where('kategori', 'lab');
    }

    public function scopeRadiologi($query)
    {
        return $query->where('kategori', 'radiologi');
    }

    public function getKategoriLabelAttribute(): string
    {
        if ($this->kategori === 'lab') return 'Laboratorium';
        if ($this->kategori === 'radiologi') return 'Radiologi';
        return ucfirst($this->kategori);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SumberInformasi extends Model
{
    protected $table    = 'sumber_informasi';
    protected $fillable = [
        'kode', 'nama', 'kategori', 'icon',
        'butuh_keterangan', 'urutan', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'butuh_keterangan' => 'boolean',
            'is_active'        => 'boolean',
        ];
    }

    public function pasien(): HasMany
    {
        return $this->hasMany(Pasien::class, 'sumber_informasi_id');
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true)->orderBy('urutan');
    }

    public function getLabelAttribute(): string
    {
        return trim(($this->icon ? $this->icon . ' ' : '') . $this->nama);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Poli extends Model
{
    protected $table = 'poli';

    protected $fillable = ['nama', 'kode', 'deskripsi', 'lantai', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function dokter()
    {
        return $this->hasMany(Dokter::class);
    }

    public function kunjungan()
    {
        return $this->hasMany(Kunjungan::class);
    }

    public function tindakan()
    {
        return $this->belongsToMany(MasterTindakan::class, 'tindakan_poli');
    }

    public function penggunaanAlat()
    {
        return $this->hasMany(PenggunaanAlat::class);
    }

    public function scopeAktif($query)
    {
        return $query->where('is_active', true);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LokasiGudang extends Model
{
    protected $table = 'lokasi_gudang';

    protected $fillable = ['kode', 'nama', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function stokGudang()
    {
        return $this->hasMany(StokGudang::class);
    }

    public function scopeAktif($query)
    {
        return $query->where('is_active', true);
    }
}

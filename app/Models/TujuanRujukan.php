<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TujuanRujukan extends Model
{
    protected $table = 'tujuan_rujukan';

    protected $fillable = ['nama', 'is_aktif'];

    protected function casts(): array
    {
        return ['is_aktif' => 'boolean'];
    }

    public function suratKeterangan()
    {
        return $this->hasMany(SuratKeterangan::class);
    }

    public function scopeAktif($query)
    {
        return $query->where('is_aktif', true);
    }
}

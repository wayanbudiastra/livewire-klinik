<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DokterPoli extends Model
{
    protected $table = 'dokter_poli';

    protected $fillable = ['dokter_id', 'poli_id', 'is_aktif'];

    protected function casts(): array
    {
        return ['is_aktif' => 'boolean'];
    }

    public function dokter()
    {
        return $this->belongsTo(Dokter::class);
    }

    public function poli()
    {
        return $this->belongsTo(Poli::class);
    }

    public function jadwalPraktek()
    {
        return $this->hasMany(JadwalPraktek::class)
                    ->orderBy('hari')
                    ->orderBy('jam_mulai');
    }
}

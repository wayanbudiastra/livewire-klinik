<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Racikan extends Model
{
    protected $table = 'racikan';

    protected $fillable = [
        'resep_id', 'nama_racikan', 'metode',
        'jumlah_sediaan', 'aturan_pakai', 'catatan',
    ];

    public function resep()
    {
        return $this->belongsTo(Resep::class);
    }

    public function bahanRacikan()
    {
        return $this->hasMany(BahanRacikan::class);
    }
}

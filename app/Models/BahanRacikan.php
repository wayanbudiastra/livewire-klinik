<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BahanRacikan extends Model
{
    protected $table = 'bahan_racikan';

    public $timestamps = false;

    protected $fillable = [
        'racikan_id', 'obat_id', 'jumlah', 'satuan',
    ];

    public function racikan()
    {
        return $this->belongsTo(Racikan::class);
    }

    public function obat()
    {
        return $this->belongsTo(Obat::class);
    }
}

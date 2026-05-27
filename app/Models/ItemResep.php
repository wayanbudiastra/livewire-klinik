<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItemResep extends Model
{
    protected $table = 'item_resep';

    public $timestamps = false;

    protected $fillable = [
        'resep_id', 'barang_id', 'jumlah', 'aturan_pakai', 'catatan',
    ];

    public function resep()
    {
        return $this->belongsTo(Resep::class);
    }

    public function barang()
    {
        return $this->belongsTo(Barang::class);
    }
}

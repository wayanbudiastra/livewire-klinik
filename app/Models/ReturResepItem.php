<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReturResepItem extends Model
{
    protected $table = 'retur_resep_item';

    public $timestamps = false;

    protected $fillable = [
        'retur_resep_id', 'item_resep_id', 'racikan_id', 'barang_id',
        'jumlah_retur', 'harga_satuan', 'subtotal',
    ];

    public function returResep()
    {
        return $this->belongsTo(ReturResep::class, 'retur_resep_id');
    }

    public function itemResep()
    {
        return $this->belongsTo(ItemResep::class);
    }

    public function racikan()
    {
        return $this->belongsTo(Racikan::class);
    }

    public function barang()
    {
        return $this->belongsTo(Barang::class);
    }
}

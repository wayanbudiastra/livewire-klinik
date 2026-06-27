<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReturGrItem extends Model
{
    protected $table = 'retur_gr_item';

    public $timestamps = false;

    protected $fillable = [
        'retur_gr_id', 'gr_item_id', 'barang_id',
        'jumlah_retur', 'harga_satuan', 'diskon_persen', 'subtotal',
    ];

    public function returGr()
    {
        return $this->belongsTo(ReturGr::class, 'retur_gr_id');
    }

    public function grItem()
    {
        return $this->belongsTo(GrItem::class, 'gr_item_id');
    }

    public function barang()
    {
        return $this->belongsTo(Barang::class);
    }

    public function getHargaEfektifAttribute(): float
    {
        return $this->harga_satuan * (1 - $this->diskon_persen / 100);
    }
}

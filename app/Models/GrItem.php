<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GrItem extends Model
{
    protected $table = 'gr_item';

    protected $fillable = [
        'goods_receipt_id', 'barang_id', 'po_item_id',
        'jumlah_terima', 'harga_satuan', 'diskon_persen', 'subtotal',
        'nomor_batch', 'expired_date',
        'hpr_sebelum', 'hpr_sesudah',
    ];

    protected function casts(): array
    {
        return ['expired_date' => 'date'];
    }

    public function goodsReceipt()
    {
        return $this->belongsTo(GoodsReceipt::class, 'goods_receipt_id');
    }

    public function barang()
    {
        return $this->belongsTo(Barang::class);
    }

    public function poItem()
    {
        return $this->belongsTo(PoItem::class, 'po_item_id');
    }

    public function getHargaEfektifAttribute(): float
    {
        return $this->harga_satuan * (1 - $this->diskon_persen / 100);
    }
}

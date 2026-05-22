<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PoItem extends Model
{
    protected $table = 'po_item';

    protected $fillable = [
        'purchase_order_id', 'barang_id',
        'jumlah_pesan', 'harga_satuan', 'diskon_persen', 'subtotal',
        'jumlah_diterima',
    ];

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function barang()
    {
        return $this->belongsTo(Barang::class);
    }

    public function getSisaAttribute(): int
    {
        return max(0, $this->jumlah_pesan - $this->jumlah_diterima);
    }
}

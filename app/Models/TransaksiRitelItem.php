<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransaksiRitelItem extends Model
{
    protected $table = 'transaksi_ritel_item';
    public $timestamps = false;

    protected $fillable = [
        'transaksi_ritel_id', 'barang_id', 'item_penunjang_id',
        'jumlah', 'harga_satuan', 'subtotal', 'catatan',
    ];

    protected function casts(): array
    {
        return [
            'harga_satuan' => 'decimal:2',
            'subtotal'     => 'decimal:2',
        ];
    }

    public function transaksiRitel()
    {
        return $this->belongsTo(TransaksiRitel::class);
    }

    public function barang()
    {
        return $this->belongsTo(Barang::class);
    }

    public function itemPenunjang()
    {
        return $this->belongsTo(ItemPenunjang::class);
    }

    /** Nama tampilan: obat (barang) atau lab (item penunjang) untuk susulan. */
    public function getNamaItemAttribute(): string
    {
        return $this->barang?->nama ?? $this->itemPenunjang?->nama ?? '-';
    }
}

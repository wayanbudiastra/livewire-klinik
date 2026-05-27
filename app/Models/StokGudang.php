<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StokGudang extends Model
{
    protected $table = 'stok_gudang';

    protected $fillable = [
        'barang_id', 'lokasi_gudang_id',
        'stok', 'stok_min', 'stok_max',
    ];

    public function barang()
    {
        return $this->belongsTo(Barang::class);
    }

    public function lokasiGudang()
    {
        return $this->belongsTo(LokasiGudang::class);
    }

    public function isReorderPoint(): bool
    {
        return $this->stok <= $this->stok_min;
    }

    public function isOverstock(): bool
    {
        return $this->stok_max > 0 && $this->stok >= $this->stok_max;
    }

    public function getStatusStokAttribute(): string
    {
        if ($this->stok <= 0)         return 'habis';
        if ($this->isReorderPoint())   return 'reorder';
        if ($this->isOverstock())      return 'overstock';
        return 'normal';
    }
}

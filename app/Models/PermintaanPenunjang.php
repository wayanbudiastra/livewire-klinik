<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PermintaanPenunjang extends Model
{
    protected $fillable = [
        'kunjungan_id', 'item_penunjang_id',
        'jumlah', 'catatan', 'status', 'hasil_url',
    ];

    public function kunjungan()
    {
        return $this->belongsTo(Kunjungan::class);
    }

    public function itemPenunjang()
    {
        return $this->belongsTo(ItemPenunjang::class, 'item_penunjang_id');
    }
}

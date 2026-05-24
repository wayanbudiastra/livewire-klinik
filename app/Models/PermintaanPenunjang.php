<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PermintaanPenunjang extends Model
{
    protected $table = 'permintaan_penunjang';

    protected $fillable = [
        'kunjungan_id', 'item_penunjang_id',
        'jumlah', 'prioritas', 'lokasi_tubuh', 'indikasi_klinis',
        'catatan', 'status', 'hasil_url', 'ordered_by',
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

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PemakaianAlkes extends Model
{
    protected $table = 'pemakaian_alkes';

    protected $fillable = [
        'kunjungan_id', 'barang_id', 'jumlah', 'catatan',
    ];

    public function kunjungan()
    {
        return $this->belongsTo(Kunjungan::class);
    }

    public function barang()
    {
        return $this->belongsTo(Barang::class);
    }
}

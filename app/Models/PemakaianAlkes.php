<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PemakaianAlkes extends Model
{
    protected $table = 'pemakaian_alkes';

    protected $fillable = [
        'kunjungan_id', 'barang_id', 'jumlah', 'catatan',
        'sumber', 'dicatat_oleh', 'stok_dipotong_at',
    ];

    protected function casts(): array
    {
        return ['stok_dipotong_at' => 'datetime'];
    }

    /** Diinput kasir lewat panel Tambah Komponen Tagihan (stok dipotong saat lunas). */
    public function isDariKasir(): bool
    {
        return $this->sumber === 'kasir';
    }

    public function kunjungan()
    {
        return $this->belongsTo(Kunjungan::class);
    }

    public function barang()
    {
        return $this->belongsTo(Barang::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Kunjungan extends Model
{
    protected $table = 'kunjungan';

    protected $fillable = [
        'nomor_antrean', 'pasien_id', 'dokter_id', 'poli_id',
        'tanggal', 'keluhan', 'status', 'tipe_pembayaran',
    ];

    protected function casts(): array
    {
        return ['tanggal' => 'datetime'];
    }

    public function pasien()
    {
        return $this->belongsTo(Pasien::class);
    }

    public function dokter()
    {
        return $this->belongsTo(Dokter::class);
    }

    public function poli()
    {
        return $this->belongsTo(Poli::class);
    }

    public function permintaanPenunjang()
    {
        return $this->hasMany(PermintaanPenunjang::class);
    }

    public function penggunaanAlat()
    {
        return $this->hasMany(PenggunaanAlat::class);
    }
}

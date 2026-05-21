<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PenggunaanAlat extends Model
{
    protected $table = 'penggunaan_alat';

    protected $fillable = [
        'peralatan_id', 'poli_id', 'kunjungan_id',
        'dipakai_oleh', 'waktu_mulai', 'waktu_selesai', 'catatan',
    ];

    protected function casts(): array
    {
        return [
            'waktu_mulai'   => 'datetime',
            'waktu_selesai' => 'datetime',
        ];
    }

    public function peralatan()
    {
        return $this->belongsTo(PeralatanMedis::class, 'peralatan_id');
    }

    public function poli()
    {
        return $this->belongsTo(Poli::class);
    }

    public function kunjungan()
    {
        return $this->belongsTo(Kunjungan::class);
    }

    public function getDurasiAttribute(): ?string
    {
        if (! $this->waktu_selesai) return null;
        $diff = $this->waktu_mulai->diff($this->waktu_selesai);
        return $diff->format('%H:%I:%S');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AsesmenPerawat extends Model
{
    protected $table = 'asesmen_perawat';

    protected $fillable = [
        'kunjungan_id', 'perawat_id',
        'berat_badan', 'tinggi_badan',
        'tekanan_darah', 'nadi', 'suhu', 'saturasi', 'gds',
        'anamnesis_awal',
    ];

    protected function casts(): array
    {
        return [
            'berat_badan'  => 'decimal:2',
            'tinggi_badan' => 'decimal:2',
            'suhu'         => 'decimal:1',
            'saturasi'     => 'decimal:1',
            'gds'          => 'decimal:2',
        ];
    }

    public function kunjungan()
    {
        return $this->belongsTo(Kunjungan::class);
    }

    public function perawat()
    {
        return $this->belongsTo(Perawat::class);
    }

    public function getBmiAttribute(): ?float
    {
        $bb = (float) $this->berat_badan;
        $tb = (float) $this->tinggi_badan;
        if ($bb > 0 && $tb > 0) {
            $tbMeter = $tb / 100;
            return round($bb / ($tbMeter * $tbMeter), 1);
        }
        return null;
    }
}

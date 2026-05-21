<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PeralatanMedis extends Model
{
    protected $table = 'peralatan_medis';

    protected $fillable = [
        'kode', 'nama', 'merk', 'nomor_seri', 'deskripsi',
        'status', 'is_active', 'lokasi_terakhir', 'poli_terakhir_id', 'tanggal_kalibrasi',
    ];

    protected $casts = [
        'tanggal_kalibrasi' => 'date',
        'is_active'         => 'boolean',
    ];

    public function poliTerakhir()
    {
        return $this->belongsTo(Poli::class, 'poli_terakhir_id');
    }

    public function riwayatPenggunaan()
    {
        return $this->hasMany(PenggunaanAlat::class, 'peralatan_id');
    }

    public function scopeTersedia($query)
    {
        return $query->where('status', 'tersedia');
    }

    public function getStatusLabelAttribute(): string
    {
        $labels = [
            'tersedia'    => 'Tersedia',
            'digunakan'   => 'Digunakan',
            'maintenance' => 'Maintenance',
            'rusak'       => 'Rusak',
        ];
        return $labels[$this->status] ?? ucfirst($this->status);
    }

    public function getStatusColorAttribute(): string
    {
        $colors = [
            'tersedia'    => 'emerald',
            'digunakan'   => 'blue',
            'maintenance' => 'amber',
            'rusak'       => 'red',
        ];
        return $colors[$this->status] ?? 'gray';
    }
}

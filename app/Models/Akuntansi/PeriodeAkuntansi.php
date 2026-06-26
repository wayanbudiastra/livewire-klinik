<?php

namespace App\Models\Akuntansi;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PeriodeAkuntansi extends Model
{
    protected $table = 'periode_akuntansi';

    protected $fillable = [
        'tahun', 'bulan', 'status',
        'ditutup_oleh', 'ditutup_pada',
        'dibuka_kembali_oleh', 'dibuka_kembali_pada', 'alasan_dibuka_kembali',
    ];

    protected function casts(): array
    {
        return [
            'ditutup_pada'        => 'datetime',
            'dibuka_kembali_pada' => 'datetime',
        ];
    }

    public function ditutupOleh()
    {
        return $this->belongsTo(User::class, 'ditutup_oleh');
    }

    public function dibukaKembaliOleh()
    {
        return $this->belongsTo(User::class, 'dibuka_kembali_oleh');
    }

    public function scopeTerbuka(Builder $query): Builder
    {
        return $query->where('status', 'terbuka');
    }

    public function scopeDitutup(Builder $query): Builder
    {
        return $query->where('status', 'ditutup');
    }

    public function getLabelAttribute(): string
    {
        $bulanNama = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        return ($bulanNama[$this->bulan] ?? $this->bulan) . ' ' . $this->tahun;
    }
}

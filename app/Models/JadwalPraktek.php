<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JadwalPraktek extends Model
{
    protected $table = 'jadwal_praktek';

    protected $fillable = [
        'dokter_poli_id', 'hari', 'jam_mulai',
        'jam_selesai', 'kuota_pasien', 'is_aktif', 'keterangan',
    ];

    protected function casts(): array
    {
        return ['is_aktif' => 'boolean'];
    }

    public function dokterPoli()
    {
        return $this->belongsTo(DokterPoli::class);
    }

    public static function getHariOptions(): array
    {
        return [
            'senin'   => 'Senin',
            'selasa'  => 'Selasa',
            'rabu'    => 'Rabu',
            'kamis'   => 'Kamis',
            'jumat'   => 'Jumat',
            'sabtu'   => 'Sabtu',
            'minggu'  => 'Minggu',
        ];
    }

    public static function hasOverlap(
        int $dokterPoliId,
        string $hari,
        string $jamMulai,
        string $jamSelesai,
        ?int $excludeId = null
    ): bool {
        return static::where('dokter_poli_id', $dokterPoliId)
            ->where('hari', $hari)
            ->where('is_aktif', true)
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->get()
            ->contains(function ($j) use ($jamMulai, $jamSelesai) {
                $newMulai   = strtotime($jamMulai);
                $newSelesai = strtotime($jamSelesai);
                $exMulai    = strtotime($j->jam_mulai);
                $exSelesai  = strtotime($j->jam_selesai);
                return $newMulai < $exSelesai && $newSelesai > $exMulai;
            });
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SuratKeterangan extends Model
{
    protected $table = 'surat_keterangan';

    protected $fillable = [
        'nomor_surat', 'kunjungan_id', 'tipe', 'dokter_id',
        'data', 'dicetak_oleh', 'dicetak_pada',
    ];

    protected function casts(): array
    {
        return [
            'data'         => 'array',
            'dicetak_pada' => 'datetime',
        ];
    }

    public function kunjungan()
    {
        return $this->belongsTo(Kunjungan::class);
    }

    public function dokter()
    {
        return $this->belongsTo(Dokter::class);
    }

    public function dicetakOleh()
    {
        return $this->belongsTo(User::class, 'dicetak_oleh');
    }

    public function getLabelTipeAttribute(): string
    {
        return match ($this->tipe) {
            'keterangan_sehat' => 'Keterangan Sehat',
            'keterangan_sakit' => 'Keterangan Sakit',
            'rujukan'          => 'Rujukan',
            'kontrol'          => 'Kontrol',
            default            => $this->tipe,
        };
    }

    /** Generate nomor surat sesuai tipe: PREFIX-YYYYMM-0001 */
    public static function generateNomor(string $tipe): string
    {
        $prefix = match ($tipe) {
            'keterangan_sehat' => 'SHT',
            'keterangan_sakit' => 'SKT',
            'rujukan'          => 'RJK',
            'kontrol'          => 'KTR',
            default            => 'SKT',
        };

        $bulan = now()->format('Ym');
        $like  = "{$prefix}-{$bulan}-%";
        $last  = static::where('nomor_surat', 'like', $like)
            ->orderByDesc('nomor_surat')
            ->value('nomor_surat');

        $urut = $last ? ((int) substr($last, -4)) + 1 : 1;

        return "{$prefix}-{$bulan}-" . str_pad($urut, 4, '0', STR_PAD_LEFT);
    }
}

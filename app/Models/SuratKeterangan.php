<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SuratKeterangan extends Model
{
    protected $table = 'surat_keterangan';

    protected $fillable = [
        'nomor_surat', 'kunjungan_id', 'tipe', 'dokter_id', 'tujuan_rujukan_id',
        'data', 'dicetak_oleh', 'dicetak_pada',
        'revised_at', 'revised_by', 'revision_count', 'revision_reason',
    ];

    protected function casts(): array
    {
        return [
            'data'         => 'array',
            'dicetak_pada' => 'datetime',
            'revised_at'   => 'datetime',
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

    public function revisedBy()
    {
        return $this->belongsTo(User::class, 'revised_by');
    }

    public function tujuanRujukan()
    {
        return $this->belongsTo(TujuanRujukan::class);
    }

    public function getLabelTipeAttribute(): string
    {
        return static::labelTipe($this->tipe);
    }

    public static function labelTipe(string $tipe): string
    {
        return match ($tipe) {
            'keterangan_sehat' => 'Keterangan Sehat',
            'keterangan_sakit' => 'Keterangan Sakit',
            'rujukan'          => 'Rujukan',
            'kontrol'          => 'Kontrol',
            'resume_medis'     => 'Resume Medis',
            default            => $tipe,
        };
    }

    /** Label bilingual hasil pemeriksaan buta warna (Keterangan Sehat). */
    public static function labelButaWarna(?string $key, string $bahasa = 'id'): ?string
    {
        if (!$key) return null;

        return match ([$key, $bahasa]) {
            ['normal', 'en']  => 'Normal',
            ['normal', 'id']  => 'Normal',
            ['parsial', 'en'] => 'Partial Colour Blindness',
            ['parsial', 'id'] => 'Buta Warna Parsial',
            ['total', 'en']   => 'Total Colour Blindness',
            ['total', 'id']   => 'Buta Warna Total',
            default           => $key,
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
            'resume_medis'     => 'RSM',
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

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReturResep extends Model
{
    protected $table = 'retur_resep';

    protected $fillable = [
        'nomor_retur', 'resep_id', 'kunjungan_id', 'billing_id',
        'tanggal_retur', 'alasan', 'catatan', 'metode_pengembalian',
        'total_nilai_retur', 'sesi_kas_id', 'diproses_oleh',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_retur' => 'date',
        ];
    }

    public function resep()
    {
        return $this->belongsTo(Resep::class);
    }

    public function kunjungan()
    {
        return $this->belongsTo(Kunjungan::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'billing_id');
    }

    public function items()
    {
        return $this->hasMany(ReturResepItem::class, 'retur_resep_id');
    }

    public function sesiKas()
    {
        return $this->belongsTo(SesiKas::class);
    }

    public function diprosesOleh()
    {
        return $this->belongsTo(User::class, 'diproses_oleh');
    }

    public static function generateNomorRetur(): string
    {
        $prefix = 'RRX-' . now()->format('Ymd') . '-';
        $last   = static::where('nomor_retur', 'like', $prefix . '%')
                    ->orderByDesc('nomor_retur')
                    ->value('nomor_retur');
        $seq    = $last ? (int) substr($last, -4) + 1 : 1;
        return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }
}

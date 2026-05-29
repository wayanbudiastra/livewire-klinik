<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TransaksiRitel extends Model
{
    protected $table = 'transaksi_ritel';

    protected $fillable = [
        'nomor_ritel', 'nama_pembeli', 'nomor_hp', 'pasien_id',
        'apoteker_id', 'kasir_id', 'sesi_kas_id', 'status', 'metode_bayar',
        'total_harga', 'total_bayar', 'kembalian', 'catatan',
        'dibayar_at', 'diserahkan_at',
    ];

    protected function casts(): array
    {
        return [
            'total_harga'   => 'decimal:2',
            'total_bayar'   => 'decimal:2',
            'kembalian'     => 'decimal:2',
            'dibayar_at'    => 'datetime',
            'diserahkan_at' => 'datetime',
        ];
    }

    public function items()
    {
        return $this->hasMany(TransaksiRitelItem::class);
    }

    public function apoteker()
    {
        return $this->belongsTo(User::class, 'apoteker_id');
    }

    public function kasir()
    {
        return $this->belongsTo(User::class, 'kasir_id');
    }

    public function pasien()
    {
        return $this->belongsTo(Pasien::class);
    }

    public function sesiKas()
    {
        return $this->belongsTo(SesiKas::class);
    }

    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'draft'          => 'Draft',
            'menunggu_kasir' => 'Menunggu Kasir',
            'dibayar'        => 'Dibayar',
            'selesai'        => 'Selesai',
            'dibatalkan'     => 'Dibatalkan',
            default          => $this->status,
        };
    }

    public static function generateNomor(): string
    {
        $prefix = 'RIT-' . now()->format('Ymd') . '-';
        $last   = static::where('nomor_ritel', 'like', $prefix . '%')
                        ->orderByDesc('nomor_ritel')
                        ->value('nomor_ritel');
        $seq    = $last ? (int) substr($last, -4) + 1 : 1;
        return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }

    public function bisaDibatalkan(): bool
    {
        return in_array($this->status, ['draft', 'menunggu_kasir']);
    }
}

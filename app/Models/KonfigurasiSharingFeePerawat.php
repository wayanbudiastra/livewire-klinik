<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Sharing fee perawat -- 1 persentase GLOBAL per kategori (BEDA dari
 * SharingFee dokter yang per-individu). Cakupan saat ini cuma 'tindakan':
 * perawat yang tercatat sbg pelaksana suatu Tindakan dapat X% dari
 * subtotal tindakan itu (lihat Tindakan::pelaksana, SharingFeeService).
 */
class KonfigurasiSharingFeePerawat extends Model
{
    protected $table = 'konfigurasi_sharing_fee_perawat';

    protected $fillable = ['kategori', 'persentase', 'updated_by'];

    protected function casts(): array
    {
        return ['persentase' => 'decimal:2'];
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** Persentase saat ini utk 1 kategori (di-cache 60 detik). 0 kalau belum diset. */
    public static function persentase(string $kategori): float
    {
        return Cache::remember("sharing_fee_perawat.{$kategori}", 60, function () use ($kategori) {
            return (float) (static::where('kategori', $kategori)->value('persentase') ?? 0);
        });
    }

    public static function clearCache(string $kategori): void
    {
        Cache::forget("sharing_fee_perawat.{$kategori}");
    }
}

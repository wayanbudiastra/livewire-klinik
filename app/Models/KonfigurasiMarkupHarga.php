<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Markup harga jual OTOMATIS berbasis harga modal, per kategori. Beda dari
 * KonfigurasiHargaWna (1 persen global, basis harga_jual, generator manual
 * lewat tombol) -- di sini dasarnya harga_pokok/harga_modal, multiplier
 * beda per kategori, dan diterapkan otomatis begitu modal berubah.
 *
 * Cakupan: 'obat_bhp' (Barang jenis obat & bahan_habis_pakai) dan 'lab'
 * (ItemPenunjang kategori lab). Radiologi/Tindakan/Alkes/lainnya TIDAK ikut,
 * tetap pakai KonfigurasiHargaWna spt sebelumnya.
 */
class KonfigurasiMarkupHarga extends Model
{
    protected $table = 'konfigurasi_markup_harga';

    protected $fillable = [
        'kategori', 'multiplier_wna', 'multiplier_ktp', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'multiplier_wna' => 'decimal:2',
            'multiplier_ktp' => 'decimal:2',
        ];
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** Ambil konfigurasi utk 1 kategori (di-cache 60 detik). Null kalau belum ada baris utk kategori itu. */
    public static function untuk(string $kategori): ?self
    {
        return Cache::remember("markup_harga.{$kategori}", 60, function () use ($kategori) {
            return static::where('kategori', $kategori)->first();
        });
    }

    public static function clearCache(string $kategori): void
    {
        Cache::forget("markup_harga.{$kategori}");
    }
}

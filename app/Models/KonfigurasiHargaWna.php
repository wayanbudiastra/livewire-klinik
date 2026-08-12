<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class KonfigurasiHargaWna extends Model
{
    protected $table = 'konfigurasi_harga_wna';

    protected $fillable = [
        'markup_persen', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'markup_persen' => 'decimal:2',
        ];
    }

    /** Ambil konfigurasi (singleton, baris pertama atau baru). */
    public static function config(): self
    {
        return static::firstOrNew(['id' => 1], ['markup_persen' => 50]);
    }

    /**
     * Persentase markup WNA saat ini (di-cache 60 detik).
     * HANYA dipakai sebagai nilai default/generator di form & bulk-apply —
     * bukan dipakai langsung saat transaksi (lihat TarifResolver).
     */
    public static function markupPersen(): float
    {
        return Cache::remember('harga_wna.markup_persen', 60, function () {
            return (float) (static::value('markup_persen') ?? 50);
        });
    }

    /** Hapus cache markup (dipanggil setelah config disimpan). */
    public static function clearCache(): void
    {
        Cache::forget('harga_wna.markup_persen');
    }
}

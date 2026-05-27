<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BatchExpired extends Model
{
    protected $table = 'batch_expired';

    protected $fillable = [
        'barang_id', 'nomor_batch',
        'tanggal_expired', 'stok_batch', 'catatan',
    ];

    protected function casts(): array
    {
        return ['tanggal_expired' => 'date'];
    }

    public function barang()
    {
        return $this->belongsTo(Barang::class);
    }

    public function getSisaHariAttribute(): int
    {
        return (int) now()->diffInDays($this->tanggal_expired, false);
    }

    public function getStatusExpiredAttribute(): string
    {
        $sisa = $this->sisa_hari;
        if ($sisa < 0)   return 'expired';
        if ($sisa <= 30) return 'kritis';
        if ($sisa <= 90) return 'warning';
        return 'aman';
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PenagihanAsuransi extends Model
{
    protected $table    = 'penagihan_asuransi';
    protected $fillable = [
        'nomor_penagihan', 'asuransi_id', 'dibuat_oleh', 'tanggal_penagihan',
        'periode_mulai', 'periode_akhir', 'total_tagihan', 'total_dibayar', 'status', 'catatan',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_penagihan' => 'date',
            'periode_mulai'     => 'date',
            'periode_akhir'     => 'date',
            'total_tagihan'     => 'decimal:2',
            'total_dibayar'     => 'decimal:2',
        ];
    }

    public function asuransi():  BelongsTo { return $this->belongsTo(Asuransi::class); }
    public function pembuat():   BelongsTo { return $this->belongsTo(User::class, 'dibuat_oleh'); }
    public function items():     HasMany   { return $this->hasMany(PenagihanItem::class, 'penagihan_id'); }
    public function pembayaran():HasMany   { return $this->hasMany(PembayaranAsuransi::class, 'penagihan_id'); }

    public function getSisaTagihanAttribute(): float
    {
        return max(0, $this->total_tagihan - $this->total_dibayar);
    }
}

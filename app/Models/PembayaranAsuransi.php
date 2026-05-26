<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PembayaranAsuransi extends Model
{
    protected $table    = 'pembayaran_asuransi';
    protected $fillable = [
        'nomor_pembayaran', 'penagihan_id', 'asuransi_id', 'dicatat_oleh',
        'jumlah_bayar', 'tanggal_bayar', 'metode', 'nomor_referensi', 'catatan',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_bayar' => 'date',
            'jumlah_bayar'  => 'decimal:2',
        ];
    }

    public function penagihan(): BelongsTo { return $this->belongsTo(PenagihanAsuransi::class, 'penagihan_id'); }
    public function asuransi():  BelongsTo { return $this->belongsTo(Asuransi::class); }
    public function pencatat():  BelongsTo { return $this->belongsTo(User::class, 'dicatat_oleh'); }

    public function getMetodeLabelAttribute(): string
    {
        return match ($this->metode) {
            'transfer' => 'Transfer Bank',
            'cek'      => 'Cek',
            'giro'     => 'Giro',
            'tunai'    => 'Tunai',
            default    => $this->metode,
        };
    }
}

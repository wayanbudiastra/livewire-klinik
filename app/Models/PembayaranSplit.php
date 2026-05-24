<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PembayaranSplit extends Model
{
    protected $table = 'pembayaran_split';

    protected $fillable = [
        'billing_id', 'sesi_kas_id', 'user_id', 'metode', 'jumlah',
        'referensi', 'nama_asuransi', 'nomor_polis', 'jumlah_cover', 'jumlah_pasien', 'tanggal_bayar',
    ];

    protected function casts(): array
    {
        return [
            'jumlah'        => 'decimal:2',
            'jumlah_cover'  => 'decimal:2',
            'jumlah_pasien' => 'decimal:2',
            'tanggal_bayar' => 'datetime',
        ];
    }

    public function billing(): BelongsTo { return $this->belongsTo(Invoice::class, 'billing_id'); }
    public function user(): BelongsTo    { return $this->belongsTo(User::class); }
    public function sesiKas(): BelongsTo { return $this->belongsTo(SesiKas::class); }

    public function getMetodeLabelAttribute(): string
    {
        return match ($this->metode) {
            'tunai'    => 'Tunai',
            'debit'    => 'Kartu Debit',
            'kredit'   => 'Kartu Kredit',
            'transfer' => 'Transfer Bank',
            'qris'     => 'QRIS',
            'bpjs'     => 'BPJS',
            'asuransi' => 'Asuransi',
            'deposit'  => 'Deposit',
            default    => ucfirst($this->metode),
        };
    }
}

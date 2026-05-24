<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransaksiDeposit extends Model
{
    protected $table = 'transaksi_deposit';

    protected $fillable = [
        'pasien_id', 'sesi_kas_id', 'user_id',
        'nomor_transaksi', 'tipe', 'jumlah',
        'saldo_sebelum', 'saldo_sesudah',
        'referensi_tipe', 'referensi_id', 'keterangan',
    ];

    protected function casts(): array
    {
        return [
            'jumlah'        => 'decimal:2',
            'saldo_sebelum' => 'decimal:2',
            'saldo_sesudah' => 'decimal:2',
        ];
    }

    public function pasien(): BelongsTo  { return $this->belongsTo(Pasien::class); }
    public function user(): BelongsTo    { return $this->belongsTo(User::class); }
    public function sesiKas(): BelongsTo { return $this->belongsTo(SesiKas::class); }
}

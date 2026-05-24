<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pembayaran extends Model
{
    protected $table = 'pembayaran';

    public $timestamps = false;

    protected $fillable = [
        'billing_id', 'shift_id', 'metode',
        'jumlah', 'bank_nama', 'nomor_referensi',
        'tipe_kartu', 'nama_asuransi', 'catatan', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'jumlah'     => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function billing()
    {
        return $this->belongsTo(Invoice::class, 'billing_id');
    }

    public function shift()
    {
        return $this->belongsTo(ShiftKasir::class, 'shift_id');
    }
}

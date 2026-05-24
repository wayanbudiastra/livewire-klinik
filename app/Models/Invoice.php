<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $table = 'billing';

    protected $fillable = [
        'kunjungan_id', 'shift_id', 'nomor_invoice',
        'total_tagihan', 'total_bayar', 'sisa', 'diskon_global',
        'status', 'cancelled_by', 'cancel_reason',
    ];

    protected function casts(): array
    {
        return [
            'total_tagihan' => 'decimal:2',
            'total_bayar'   => 'decimal:2',
            'sisa'          => 'decimal:2',
            'diskon_global' => 'decimal:2',
        ];
    }

    public function kunjungan()
    {
        return $this->belongsTo(Kunjungan::class);
    }

    public function shift()
    {
        return $this->belongsTo(ShiftKasir::class, 'shift_id');
    }

    public function items()
    {
        return $this->hasMany(InvoiceItem::class, 'billing_id');
    }

    public function pembayaran()
    {
        return $this->hasMany(Pembayaran::class, 'billing_id');
    }

    public function cancelledBy()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function scopeBelumBayar($query)
    {
        return $query->where('status', 'belum_bayar');
    }

    public function scopeLunas($query)
    {
        return $query->where('status', 'lunas');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $table = 'billing';

    protected $fillable = [
        'kunjungan_id', 'shift_id', 'sesi_kas_id', 'nomor_invoice',
        'total_tagihan', 'total_bayar', 'total_deposit_dipakai', 'sisa', 'diskon_global',
        'status', 'sudah_cetak', 'jumlah_cetak',
        'cancelled_by', 'cancel_verified_by', 'cancel_reason', 'dibatalkan_pada',
        'total_cover_asuransi', 'total_tanggungan_pasien', 'asuransi_id',
    ];

    protected function casts(): array
    {
        return [
            'total_tagihan'        => 'decimal:2',
            'total_bayar'          => 'decimal:2',
            'total_deposit_dipakai'=> 'decimal:2',
            'sisa'                 => 'decimal:2',
            'diskon_global'        => 'decimal:2',
            'sudah_cetak'          => 'boolean',
            'dibatalkan_pada'          => 'datetime',
            'total_cover_asuransi'     => 'decimal:2',
            'total_tanggungan_pasien'  => 'decimal:2',
        ];
    }

    public function kunjungan()
    {
        return $this->belongsTo(Kunjungan::class);
    }

    public function asuransi()
    {
        return $this->belongsTo(Asuransi::class);
    }

    public function shift()
    {
        return $this->belongsTo(ShiftKasir::class, 'shift_id');
    }

    public function sesiKas()
    {
        return $this->belongsTo(SesiKas::class, 'sesi_kas_id');
    }

    public function items()
    {
        return $this->hasMany(InvoiceItem::class, 'billing_id');
    }

    public function pembayaran()
    {
        return $this->hasMany(Pembayaran::class, 'billing_id');
    }

    public function pembayaranSplit()
    {
        return $this->hasMany(PembayaranSplit::class, 'billing_id');
    }

    public function cetakLogs()
    {
        return $this->hasMany(CetakInvoiceLog::class, 'billing_id');
    }

    public function cancelledBy()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function dibatalkanOleh()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function cancelVerifiedBy()
    {
        return $this->belongsTo(User::class, 'cancel_verified_by');
    }

    public function scopeBelumBayar($query)
    {
        return $query->where('status', 'belum_bayar');
    }

    public function scopeLunas($query)
    {
        return $query->where('status', 'lunas');
    }

    public function scopeDibatalkan($query)
    {
        return $query->where('status', 'dibatalkan');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReturGr extends Model
{
    protected $table = 'retur_gr';

    protected $fillable = [
        'nomor_retur', 'goods_receipt_id', 'supplier_id',
        'tanggal_retur', 'alasan', 'catatan', 'status', 'total_nilai',
        'dibuat_oleh', 'diverifikasi_oleh', 'diverifikasi_pada',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_retur'      => 'date',
            'diverifikasi_pada'  => 'datetime',
        ];
    }

    public function goodsReceipt()
    {
        return $this->belongsTo(GoodsReceipt::class, 'goods_receipt_id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items()
    {
        return $this->hasMany(ReturGrItem::class, 'retur_gr_id');
    }

    public function dibuatOleh()
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    public function diverifikasiOleh()
    {
        return $this->belongsTo(User::class, 'diverifikasi_oleh');
    }

    public static function generateNomorRetur(): string
    {
        $prefix = 'RGR-' . now()->format('Y-m-');
        $last   = static::where('nomor_retur', 'like', $prefix . '%')
                    ->orderByDesc('nomor_retur')
                    ->value('nomor_retur');
        $seq    = $last ? (int) substr($last, -4) + 1 : 1;
        return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }
}

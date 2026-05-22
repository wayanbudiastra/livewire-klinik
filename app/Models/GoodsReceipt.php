<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoodsReceipt extends Model
{
    protected $table = 'goods_receipt';

    protected $fillable = [
        'nomor_gr', 'purchase_order_id', 'supplier_id', 'diterima_oleh',
        'tanggal_terima', 'nomor_faktur_supplier', 'tanggal_faktur',
        'tanggal_jatuh_tempo', 'nomor_surat_jalan',
        'total_nilai', 'status', 'catatan',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_terima'      => 'date',
            'tanggal_faktur'      => 'date',
            'tanggal_jatuh_tempo' => 'date',
        ];
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function diterimaOleh()
    {
        return $this->belongsTo(User::class, 'diterima_oleh');
    }

    public function items()
    {
        return $this->hasMany(GrItem::class, 'goods_receipt_id');
    }

    public static function generateNomorGr(): string
    {
        $prefix = 'GR-' . now()->format('Y-m-');
        $last   = static::where('nomor_gr', 'like', $prefix . '%')
                    ->orderByDesc('nomor_gr')
                    ->value('nomor_gr');
        $seq    = $last ? (int) substr($last, -4) + 1 : 1;
        return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }
}

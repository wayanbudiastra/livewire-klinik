<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    protected $table = 'purchase_order';

    protected $fillable = [
        'nomor_po', 'supplier_id', 'dibuat_oleh', 'disetujui_oleh',
        'tanggal_po', 'tanggal_kirim_estimasi', 'tanggal_disetujui',
        'status', 'total_nilai', 'catatan',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_po'             => 'date',
            'tanggal_kirim_estimasi' => 'date',
            'tanggal_disetujui'      => 'date',
        ];
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function dibuatOleh()
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    public function disetujuiOleh()
    {
        return $this->belongsTo(User::class, 'disetujui_oleh');
    }

    public function items()
    {
        return $this->hasMany(PoItem::class, 'purchase_order_id');
    }

    public function goodsReceipts()
    {
        return $this->hasMany(GoodsReceipt::class, 'purchase_order_id');
    }

    public function scopeDraft($query)   { return $query->where('status', 'draft'); }
    public function scopeDikirim($query) { return $query->where('status', 'dikirim'); }

    public static function getStatusLabels(): array
    {
        return [
            'draft'      => 'Draft',
            'dikirim'    => 'Dikirim',
            'sebagian'   => 'Sebagian Diterima',
            'selesai'    => 'Selesai',
            'dibatalkan' => 'Dibatalkan',
        ];
    }

    public static function generateNomorPo(): string
    {
        $prefix = 'PO-' . now()->format('Y-m-');
        $last   = static::where('nomor_po', 'like', $prefix . '%')
                    ->orderByDesc('nomor_po')
                    ->value('nomor_po');
        $seq    = $last ? (int) substr($last, -4) + 1 : 1;
        return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }
}

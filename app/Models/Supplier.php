<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    protected $table = 'supplier';

    protected $fillable = [
        'kode', 'nama', 'tipe', 'pic', 'telepon', 'email',
        'alamat', 'npwp', 'lead_time_hari', 'top_hari', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function barang()
    {
        return $this->belongsToMany(Barang::class, 'supplier_barang')
                    ->withPivot(['kode_barang_supplier', 'harga_terakhir', 'is_supplier_utama'])
                    ->withTimestamps();
    }

    public function supplierBarang()
    {
        return $this->hasMany(SupplierBarang::class);
    }

    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function goodsReceipts()
    {
        return $this->hasMany(GoodsReceipt::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public static function getTipeOptions(): array
    {
        return [
            'distributor' => 'Distributor',
            'prinsipal'   => 'Prinsipal',
            'apotek'      => 'Apotek',
            'lainnya'     => 'Lainnya',
        ];
    }
}

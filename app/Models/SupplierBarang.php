<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierBarang extends Model
{
    protected $table = 'supplier_barang';

    protected $fillable = [
        'barang_id', 'supplier_id', 'kode_barang_supplier',
        'nama_barang_supplier', 'harga_terakhir', 'is_supplier_utama',
    ];

    protected function casts(): array
    {
        return ['is_supplier_utama' => 'boolean'];
    }

    public function barang()
    {
        return $this->belongsTo(Barang::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
}

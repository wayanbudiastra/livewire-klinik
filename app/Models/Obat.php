<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Obat extends Model
{
    protected $table = 'obat';

    protected $fillable = [
        'kode', 'barcode', 'nama', 'generik',
        'jenis_barang', 'is_paten',
        'satuan', 'satuan_besar_id', 'satuan_kecil_id', 'konversi',
        'stok', 'harga', 'harga_beli', 'harga_bpjs',
        'kategori', 'is_active', 'expired_date',
    ];

    protected function casts(): array
    {
        return [
            'is_active'    => 'boolean',
            'is_paten'     => 'boolean',
            'expired_date' => 'date',
            'harga'        => 'decimal:2',
            'harga_beli'   => 'decimal:2',
            'harga_bpjs'   => 'decimal:2',
        ];
    }

    public function satuanBesar()
    {
        return $this->belongsTo(Satuan::class, 'satuan_besar_id');
    }

    public function satuanKecil()
    {
        return $this->belongsTo(Satuan::class, 'satuan_kecil_id');
    }

    public function stokGudang()
    {
        return $this->hasMany(StokGudang::class);
    }

    public function batchExpired()
    {
        return $this->hasMany(BatchExpired::class)->orderBy('tanggal_expired');
    }

    public function itemResep()
    {
        return $this->hasMany(ItemResep::class);
    }

    public function scopeAktif($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('nama',    'like', "%{$term}%")
              ->orWhere('kode',   'like', "%{$term}%")
              ->orWhere('barcode','like', "%{$term}%")
              ->orWhere('generik','like', "%{$term}%");
        });
    }

    public function getStokTotalAttribute(): int
    {
        return $this->stokGudang->sum('stok') ?: $this->stok;
    }

    public function getJenisLabelAttribute(): string
    {
        return $this->jenis_barang === 'alkes' ? 'Alkes' : 'Obat';
    }
}

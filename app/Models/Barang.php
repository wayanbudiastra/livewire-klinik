<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Barang extends Model
{
    protected $table = 'barang';

    protected $fillable = [
        'kode', 'nama', 'nama_generik', 'jenis', 'kategori',
        'satuan', 'satuan_besar', 'isi_satuan_besar', 'kemasan',
        'stok', 'stok_minimum', 'stok_maksimum',
        'harga_pokok', 'harga_jual',
        'golongan', 'butuh_resep', 'is_active', 'supplier_utama_id',
    ];

    protected function casts(): array
    {
        return [
            'butuh_resep'  => 'boolean',
            'is_active'    => 'boolean',
            'harga_pokok'  => 'decimal:2',
            'harga_jual'   => 'decimal:2',
        ];
    }

    public function supplierUtama()
    {
        return $this->belongsTo(Supplier::class, 'supplier_utama_id');
    }

    public function suppliers()
    {
        return $this->belongsToMany(Supplier::class, 'supplier_barang')
                    ->withPivot(['kode_barang_supplier', 'harga_terakhir', 'is_supplier_utama'])
                    ->withTimestamps();
    }

    public function supplierBarang()
    {
        return $this->hasMany(SupplierBarang::class);
    }

    public function mutasiStok()
    {
        return $this->hasMany(MutasiStok::class)->latest();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeStokKritis($query)
    {
        return $query->whereRaw('stok <= stok_minimum');
    }

    public function getLevelStokAttribute(): string
    {
        if ($this->stok === 0)                         return 'habis';
        if ($this->stok <= $this->stok_minimum)        return 'kritis';
        if ($this->stok <= $this->stok_minimum * 1.5)  return 'hampir_habis';
        return 'aman';
    }

    public function getIsStokKritisAttribute(): bool
    {
        return $this->stok <= $this->stok_minimum;
    }

    public static function generateKode(): string
    {
        $last = static::orderByDesc('kode')->value('kode');
        $seq  = $last ? (int) ltrim(str_replace('BRG-', '', $last), '0') + 1 : 1;
        return 'BRG-' . str_pad($seq, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Pastikan stok mencukupi sebelum transaksi keluar.
     * Menggunakan lockForUpdate() untuk mencegah race condition.
     *
     * @throws \DomainException jika stok tidak cukup
     */
    public static function pastikanCukup(int $barangId, float $jumlah): self
    {
        $barang = static::lockForUpdate()->findOrFail($barangId);

        if ($barang->stok < $jumlah) {
            throw new \DomainException(
                "Stok {$barang->nama} tidak mencukupi. " .
                "Tersedia: {$barang->stok} {$barang->satuan}, " .
                "Diminta: {$jumlah} {$barang->satuan}."
            );
        }

        return $barang;
    }
}

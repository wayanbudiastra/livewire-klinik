<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MutasiStok extends Model
{
    protected $table = 'mutasi_stok';

    protected $fillable = [
        'barang_id', 'user_id', 'tipe',
        'jumlah', 'stok_sebelum', 'stok_sesudah',
        'hpr_sebelum', 'hpr_sesudah',
        'referensi_tipe', 'referensi_id', 'keterangan',
    ];

    public function barang()
    {
        return $this->belongsTo(Barang::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function getTipeLabels(): array
    {
        return [
            'masuk_pembelian'    => 'Masuk — Pembelian',
            'keluar_resep'       => 'Keluar — Resep',
            'keluar_tindakan'    => 'Keluar — Tindakan',
            'penyesuaian_masuk'  => 'Penyesuaian Tambah',
            'penyesuaian_keluar' => 'Penyesuaian Kurang',
            'retur_ke_supplier'  => 'Retur ke Supplier',
            'expired'            => 'Disposal Expired',
        ];
    }
}

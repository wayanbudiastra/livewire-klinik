<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AdjustmentStok extends Model
{
    use SoftDeletes;

    protected $table = 'adjustment_stok';

    protected $fillable = [
        'nomor_adjustment', 'barang_id', 'user_id', 'tipe', 'jumlah',
        'catatan', 'bukti_referensi', 'file_attachment', 'mutasi_stok_id', 'status',
    ];

    protected function casts(): array
    {
        return ['jumlah' => 'integer', 'created_at' => 'datetime', 'updated_at' => 'datetime'];
    }

    public function barang() { return $this->belongsTo(Barang::class); }
    public function user() { return $this->belongsTo(User::class); }
    public function mutasiStok() { return $this->belongsTo(MutasiStok::class); }

    public static function getTipeLabels(): array
    {
        return [
            'penyesuaian_masuk' => 'Tambah Stok',
            'penyesuaian_keluar' => 'Kurangi Stok',
        ];
    }
}

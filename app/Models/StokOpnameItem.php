<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StokOpnameItem extends Model
{
    protected $table    = 'stok_opname_item';
    protected $fillable = [
        'stok_opname_id', 'barang_id', 'stok_sistem', 'stok_fisik',
        'selisih', 'hpr_saat_itu', 'nilai_selisih', 'tipe_selisih', 'keterangan',
    ];

    protected function casts(): array
    {
        return [
            'stok_sistem'   => 'decimal:2',
            'stok_fisik'    => 'decimal:2',
            'selisih'       => 'decimal:2',
            'hpr_saat_itu'  => 'decimal:2',
            'nilai_selisih' => 'decimal:2',
        ];
    }

    public function opname(): BelongsTo { return $this->belongsTo(StokOpname::class, 'stok_opname_id'); }
    public function barang(): BelongsTo { return $this->belongsTo(Barang::class); }
}

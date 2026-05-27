<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PemakaianBhpItem extends Model
{
    protected $table    = 'pemakaian_bhp_item';
    protected $fillable = [
        'pemakaian_bhp_id', 'barang_id', 'jumlah',
        'harga_pokok_saat_itu', 'nilai_total', 'keterangan',
    ];

    protected function casts(): array
    {
        return [
            'jumlah'               => 'decimal:2',
            'harga_pokok_saat_itu' => 'decimal:2',
            'nilai_total'          => 'decimal:2',
        ];
    }

    public function pemakaianBhp(): BelongsTo { return $this->belongsTo(PemakaianBhp::class); }
    public function barang():       BelongsTo { return $this->belongsTo(Barang::class); }
}

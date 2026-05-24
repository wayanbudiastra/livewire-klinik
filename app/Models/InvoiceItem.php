<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    protected $table = 'invoice_item';

    protected $fillable = [
        'billing_id', 'jenis', 'ref_id',
        'nama_item', 'qty', 'satuan',
        'harga_satuan', 'diskon_item', 'subtotal',
    ];

    protected function casts(): array
    {
        return [
            'qty'          => 'decimal:2',
            'harga_satuan' => 'decimal:2',
            'diskon_item'  => 'decimal:2',
            'subtotal'     => 'decimal:2',
        ];
    }

    public function billing()
    {
        return $this->belongsTo(Invoice::class, 'billing_id');
    }
}

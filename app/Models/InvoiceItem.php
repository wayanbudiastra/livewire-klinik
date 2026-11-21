<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    protected $table = 'invoice_item';

    protected $fillable = [
        'billing_id', 'jenis', 'ref_id',
        'nama_item', 'qty', 'satuan',
        'harga_satuan', 'diskon_item', 'diskon_tipe', 'diskon_nilai', 'subtotal',
    ];

    protected function casts(): array
    {
        return [
            'qty'          => 'decimal:2',
            'harga_satuan' => 'decimal:2',
            'diskon_item'  => 'decimal:2',
            'diskon_nilai' => 'decimal:2',
            'subtotal'     => 'decimal:2',
        ];
    }

    public function billing()
    {
        return $this->belongsTo(Invoice::class, 'billing_id');
    }

    /**
     * BHP dan obat yang diinput kasir ditagih sebagai jenis 'alkes' (baris pemakaian_alkes);
     * kembalikan peta [ref_id => 'Obat'|'BHP'|'Alkes'] berdasarkan jenis barangnya, hanya
     * untuk tampilan (satu query untuk semua item).
     */
    public static function petaLabelAlkes(iterable $items): array
    {
        $refIds = collect($items)->where('jenis', 'alkes')->pluck('ref_id')->filter()->unique()->all();
        if (! $refIds) return [];

        return PemakaianAlkes::with('barang:id,jenis')->whereIn('id', $refIds)->get()
            ->mapWithKeys(fn ($p) => [$p->id => match ($p->barang?->jenis) {
                'obat'              => 'Obat',
                'bahan_habis_pakai' => 'BHP',
                default             => 'Alkes',
            }])
            ->all();
    }
}

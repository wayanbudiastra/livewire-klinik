<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JurnalInventoriPending extends Model
{
    protected $table    = 'jurnal_inventori_pending';
    protected $fillable = [
        'sumber_tipe', 'sumber_id', 'tipe_transaksi', 'tanggal_transaksi',
        'kode_akun_debit', 'kode_akun_kredit', 'nominal',
        'keterangan', 'metadata', 'status', 'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_transaksi' => 'date',
            'nominal'           => 'decimal:2',
            'metadata'          => 'array',
            'posted_at'         => 'datetime',
        ];
    }
}

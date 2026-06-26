<?php

namespace App\Models\Akuntansi;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class JurnalPending extends Model
{
    protected $table = 'jurnal_pending';

    protected $fillable = [
        'sumber_tipe', 'sumber_id', 'tipe_transaksi', 'tanggal_transaksi',
        'kode_akun_debit', 'kode_akun_kredit', 'nominal',
        'keterangan', 'metadata', 'status', 'posted_at', 'jurnal_umum_id',
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

    public function akunDebit()
    {
        return $this->belongsTo(ChartOfAccount::class, 'kode_akun_debit', 'kode');
    }

    public function akunKredit()
    {
        return $this->belongsTo(ChartOfAccount::class, 'kode_akun_kredit', 'kode');
    }

    public function jurnalUmum()
    {
        return $this->belongsTo(JurnalUmum::class, 'jurnal_umum_id');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }
}

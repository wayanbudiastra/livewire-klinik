<?php

namespace App\Models\Akuntansi;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ChartOfAccount extends Model
{
    protected $table = 'chart_of_accounts';

    protected $fillable = ['kode', 'nama', 'golongan', 'tipe_normal', 'is_aktif'];

    protected function casts(): array
    {
        return ['is_aktif' => 'boolean'];
    }

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('is_aktif', true);
    }

    /** Saldo akun (sisi normal) berdasarkan jurnal_umum yang sudah posted. */
    public function getSaldoAttribute(): float
    {
        $debit  = JurnalUmum::where('kode_akun_debit', $this->kode)->sum('nominal');
        $kredit = JurnalUmum::where('kode_akun_kredit', $this->kode)->sum('nominal');

        return $this->tipe_normal === 'debit'
            ? (float) $debit - (float) $kredit
            : (float) $kredit - (float) $debit;
    }
}

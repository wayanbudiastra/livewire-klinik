<?php

namespace App\Models\Akuntansi;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class JurnalManual extends Model
{
    protected $table = 'jurnal_manual';

    protected $fillable = [
        'tanggal', 'kategori', 'kode_akun_debit', 'kode_akun_kredit',
        'nominal', 'keterangan', 'dokumen_pendukung', 'dibuat_oleh',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'nominal' => 'decimal:2',
        ];
    }

    public function dibuatOleh()
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    public function akunDebit()
    {
        return $this->belongsTo(ChartOfAccount::class, 'kode_akun_debit', 'kode');
    }

    public function akunKredit()
    {
        return $this->belongsTo(ChartOfAccount::class, 'kode_akun_kredit', 'kode');
    }

    /** Baris jurnal_pending/jurnal_umum asli, untuk tahu status pending/posted/diabaikan. */
    public function jurnalPending()
    {
        return $this->hasOne(JurnalPending::class, 'sumber_id', 'id')
            ->where('sumber_tipe', 'jurnal_manual')
            ->where('tipe_transaksi', 'jurnal_manual');
    }

    /** Baris reversal (kalau entri ini sudah dibatalkan setelah posted). */
    public function reversalPending()
    {
        return $this->hasOne(JurnalPending::class, 'sumber_id', 'id')
            ->where('sumber_tipe', 'jurnal_manual')
            ->where('tipe_transaksi', 'pembatalan_jurnal_manual');
    }

    public function getStatusAttribute(): string
    {
        if ($this->reversalPending) {
            return 'dibatalkan';
        }

        return $this->jurnalPending?->status ?? 'pending';
    }
}

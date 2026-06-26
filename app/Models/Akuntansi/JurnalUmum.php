<?php

namespace App\Models\Akuntansi;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class JurnalUmum extends Model
{
    protected $table = 'jurnal_umum';

    protected $fillable = [
        'nomor_jurnal', 'tanggal', 'kode_akun_debit', 'kode_akun_kredit',
        'nominal', 'keterangan', 'sumber_tipe', 'sumber_id',
        'diposting_oleh', 'diposting_pada',
    ];

    protected function casts(): array
    {
        return [
            'tanggal'        => 'date',
            'nominal'        => 'decimal:2',
            'diposting_pada' => 'datetime',
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

    public function petugas()
    {
        return $this->belongsTo(User::class, 'diposting_oleh');
    }

    /** Generate nomor jurnal otomatis: JU-YYYYMM-XXXX */
    public static function generateNomor(): string
    {
        $prefix = 'JU-' . now()->format('Ym') . '-';
        $last = static::where('nomor_jurnal', 'like', "{$prefix}%")
            ->orderByDesc('nomor_jurnal')
            ->value('nomor_jurnal');

        $urut = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix . str_pad((string) $urut, 4, '0', STR_PAD_LEFT);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class SesiKas extends Model
{
    protected $table = 'sesi_kas';

    protected $fillable = [
        'user_id', 'tanggal', 'dibuka_pada', 'saldo_awal',
        'ditutup_pada', 'saldo_akhir',
        'total_cash', 'total_non_cash', 'total_deposit', 'total_bpjs', 'total_asuransi', 'total_pembatalan',
        'status', 'ditutup_oleh',
        'dibuka_kembali_oleh', 'dibuka_kembali_pada', 'alasan_dibuka_kembali',
        'catatan',
    ];

    protected function casts(): array
    {
        return [
            'tanggal'             => 'date',
            'dibuka_pada'         => 'datetime',
            'ditutup_pada'        => 'datetime',
            'dibuka_kembali_pada' => 'datetime',
            'saldo_awal'          => 'decimal:2',
            'saldo_akhir'         => 'decimal:2',
            'total_cash'          => 'decimal:2',
            'total_non_cash'      => 'decimal:2',
            'total_deposit'       => 'decimal:2',
            'total_bpjs'          => 'decimal:2',
            'total_asuransi'      => 'decimal:2',
            'total_pembatalan'    => 'decimal:2',
        ];
    }

    public function user(): BelongsTo            { return $this->belongsTo(User::class); }
    public function ditutupOleh(): BelongsTo     { return $this->belongsTo(User::class, 'ditutup_oleh'); }
    public function dibukaKembaliOleh(): BelongsTo { return $this->belongsTo(User::class, 'dibuka_kembali_oleh'); }
    public function billings(): HasMany          { return $this->hasMany(Invoice::class, 'sesi_kas_id'); }
    public function pembayaranSplit(): HasMany   { return $this->hasMany(PembayaranSplit::class); }

    public function scopeBuka($q)  { return $q->where('status', 'buka'); }
    public function scopeTutup($q) { return $q->where('status', 'tutup'); }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PiutangAsuransi extends Model
{
    protected $table    = 'piutang_asuransi';
    protected $fillable = [
        'nomor_piutang', 'billing_id', 'asuransi_id', 'pasien_id',
        'jumlah_piutang', 'jumlah_dibayar', 'sisa_piutang',
        'tanggal_piutang', 'tanggal_jatuh_tempo', 'status', 'penagihan_id', 'catatan',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_piutang'     => 'date',
            'tanggal_jatuh_tempo' => 'date',
            'jumlah_piutang'      => 'decimal:2',
            'jumlah_dibayar'      => 'decimal:2',
            'sisa_piutang'        => 'decimal:2',
        ];
    }

    public function asuransi():  BelongsTo { return $this->belongsTo(Asuransi::class); }
    public function pasien():    BelongsTo { return $this->belongsTo(Pasien::class); }
    public function billing():   BelongsTo { return $this->belongsTo(Invoice::class, 'billing_id'); }
    public function penagihan(): BelongsTo { return $this->belongsTo(PenagihanAsuransi::class, 'penagihan_id'); }

    public function getIsJatuhTempoAttribute(): bool
    {
        return $this->tanggal_jatuh_tempo
            && $this->tanggal_jatuh_tempo->isPast()
            && in_array($this->status, ['tertagih', 'diajukan']);
    }

    public function getUmurPiutangAttribute(): int
    {
        return (int) $this->tanggal_piutang->diffInDays(now());
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'tertagih'        => 'Tertagih',
            'diajukan'        => 'Diajukan',
            'dibayar_sebagian'=> 'Dibayar Sebagian',
            'lunas'           => 'Lunas',
            'ditolak'         => 'Ditolak',
            default           => $this->status,
        };
    }
}

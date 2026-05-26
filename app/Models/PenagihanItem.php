<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PenagihanItem extends Model
{
    protected $table    = 'penagihan_item';
    protected $fillable = [
        'penagihan_id', 'piutang_asuransi_id', 'jumlah_diajukan', 'jumlah_disetujui',
    ];

    protected function casts(): array
    {
        return [
            'jumlah_diajukan'  => 'decimal:2',
            'jumlah_disetujui' => 'decimal:2',
        ];
    }

    public function penagihan(): BelongsTo { return $this->belongsTo(PenagihanAsuransi::class, 'penagihan_id'); }
    public function piutang():   BelongsTo { return $this->belongsTo(PiutangAsuransi::class, 'piutang_asuransi_id'); }

    public function getSelisihAttribute(): float
    {
        if ($this->jumlah_disetujui === null) return 0;
        return (float) ($this->jumlah_diajukan - $this->jumlah_disetujui);
    }
}

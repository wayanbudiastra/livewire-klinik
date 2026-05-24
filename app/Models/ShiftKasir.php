<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShiftKasir extends Model
{
    protected $table = 'shift_kasir';

    protected $fillable = [
        'user_id', 'modal_awal',
        'total_tunai', 'total_nontunai', 'total_piutang',
        'uang_fisik_akhir', 'selisih',
        'status', 'opened_at', 'closed_at', 'catatan',
    ];

    protected function casts(): array
    {
        return [
            'modal_awal'       => 'decimal:2',
            'total_tunai'      => 'decimal:2',
            'total_nontunai'   => 'decimal:2',
            'total_piutang'    => 'decimal:2',
            'uang_fisik_akhir' => 'decimal:2',
            'selisih'          => 'decimal:2',
            'opened_at'        => 'datetime',
            'closed_at'        => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function invoice()
    {
        return $this->hasMany(Invoice::class, 'shift_id');
    }

    public function pembayaran()
    {
        return $this->hasMany(Pembayaran::class, 'shift_id');
    }

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }
}

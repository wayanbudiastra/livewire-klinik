<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class DepositPasien extends Model
{
    protected $table = 'deposit_pasien';

    protected $fillable = ['pasien_id', 'saldo', 'total_topup', 'total_terpakai'];

    protected function casts(): array
    {
        return [
            'saldo'          => 'decimal:2',
            'total_topup'    => 'decimal:2',
            'total_terpakai' => 'decimal:2',
        ];
    }

    public function pasien(): BelongsTo  { return $this->belongsTo(Pasien::class); }
    public function transaksi(): HasMany { return $this->hasMany(TransaksiDeposit::class, 'pasien_id', 'pasien_id'); }
}

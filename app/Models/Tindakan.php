<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tindakan extends Model
{
    protected $table = 'tindakan';

    protected $fillable = [
        'kunjungan_id', 'master_tindakan_id', 'pelaksana_id',
        'jumlah', 'waktu_tindakan', 'catatan',
    ];

    protected function casts(): array
    {
        return [
            'waktu_tindakan' => 'datetime',
        ];
    }

    public function kunjungan()
    {
        return $this->belongsTo(Kunjungan::class);
    }

    public function masterTindakan()
    {
        return $this->belongsTo(MasterTindakan::class);
    }

    public function pelaksana()
    {
        return $this->belongsTo(User::class, 'pelaksana_id');
    }
}

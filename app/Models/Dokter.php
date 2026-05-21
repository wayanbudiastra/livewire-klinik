<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Dokter extends Model
{
    protected $table = 'dokter';

    protected $fillable = [
        'user_id', 'poli_id', 'nip', 'sip', 'spesialisasi', 'jadwal_praktek',
    ];

    protected function casts(): array
    {
        return ['jadwal_praktek' => 'array'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function poli()
    {
        return $this->belongsTo(Poli::class);
    }

    public function kunjungan()
    {
        return $this->hasMany(Kunjungan::class);
    }
}

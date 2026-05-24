<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Resep extends Model
{
    protected $table = 'resep';

    protected $fillable = [
        'kunjungan_id', 'dokter_id',
        'status', 'catatan',
        'is_locked', 'locked_by', 'locked_at', 'catatan_farmasi',
    ];

    protected function casts(): array
    {
        return [
            'is_locked' => 'boolean',
            'locked_at' => 'datetime',
        ];
    }

    public function kunjungan()
    {
        return $this->belongsTo(Kunjungan::class);
    }

    public function dokter()
    {
        return $this->belongsTo(Dokter::class);
    }

    public function locker()
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function itemResep()
    {
        return $this->hasMany(ItemResep::class);
    }

    public function racikan()
    {
        return $this->hasMany(Racikan::class);
    }

    public function scopeMenunggu($query)
    {
        return $query->where('status', 'menunggu')->where('is_locked', false);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Perawat extends Model
{
    protected $table = 'perawat';

    protected $fillable = [
        'user_id', 'nip', 'nik',
        'ihs_id', 'ihs_status', 'ihs_synced_at', 'ihs_error_msg',
    ];

    protected function casts(): array
    {
        return [
            'ihs_synced_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function asesmenPerawat()
    {
        return $this->hasMany(AsesmenPerawat::class);
    }
}

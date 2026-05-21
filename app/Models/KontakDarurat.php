<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KontakDarurat extends Model
{
    protected $table = 'kontak_darurat';

    protected $fillable = [
        'pasien_id', 'nama', 'nomor_hp',
        'hubungan', 'alamat', 'is_primary',
    ];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }

    public function pasien()
    {
        return $this->belongsTo(Pasien::class);
    }
}

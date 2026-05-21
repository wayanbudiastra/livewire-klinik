<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pasien extends Model
{
    protected $table = 'pasien';

    protected $fillable = [
        'user_id', 'nomor_rm', 'nik', 'nama', 'tanggal_lahir',
        'jenis_kelamin', 'alamat', 'telepon', 'email',
        'golongan_darah', 'alergi', 'no_bpjs', 'no_asuransi',
    ];

    protected function casts(): array
    {
        return ['tanggal_lahir' => 'date'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function kunjungan()
    {
        return $this->hasMany(Kunjungan::class);
    }
}

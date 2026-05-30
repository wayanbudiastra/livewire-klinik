<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Klinik extends Model
{
    protected $table = 'klinik';

    protected $fillable = [
        'nama',
        'jenis',
        'alamat',
        'kota',
        'provinsi',
        'kode_pos',
        'telepon',
        'fax',
        'email',
        'website',
        'logo',
        'nomor_izin',
        'npwp',
        'nama_pimpinan',
        'jabatan_pimpinan',
        'header_struk',
        'footer_struk',
        'bahasa_icd',
    ];

    /** Ambil profil klinik (baris pertama, atau instance baru jika belum ada). */
    public static function profil(): self
    {
        return static::firstOrNew([]);
    }

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo ? asset('storage/' . $this->logo) : null;
    }
}

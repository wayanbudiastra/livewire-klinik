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

    /**
     * Logo sbg base64 data URI -- dipakai di template PDF (DomPDF).
     * DomPDF TIDAK bisa load <img src="..."> dari filesystem path (public_path())
     * atau URL biasa tanpa enable_remote diaktifkan, jadi logo gagal muncul di
     * cetakan walau file-nya ada & URL-nya valid di browser. Embed sbg base64
     * data URI supaya logo selalu ikut ter-render di dalam PDF tanpa tergantung
     * konfigurasi remote/filesystem DomPDF.
     */
    public function getLogoDataUriAttribute(): ?string
    {
        if (! $this->logo) return null;

        $path = storage_path('app/public/' . $this->logo);
        if (! is_file($path)) return null;

        $mime = match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'png'  => 'image/png',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };

        return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($path));
    }
}

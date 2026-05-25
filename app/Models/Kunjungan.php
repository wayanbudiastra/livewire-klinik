<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Kunjungan extends Model
{
    protected $table = 'kunjungan';

    protected $fillable = [
        'appointment_id', 'nomor_antrean', 'pasien_id', 'dokter_id', 'poli_id',
        'tanggal', 'keluhan', 'status', 'tipe_pembayaran',
        'waktu_panggil', 'asal_kedatangan', 'catatan_penting',
    ];

    protected function casts(): array
    {
        return [
            'tanggal'       => 'datetime',
            'waktu_panggil' => 'datetime',
        ];
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function pasien()
    {
        return $this->belongsTo(Pasien::class);
    }

    public function dokter()
    {
        return $this->belongsTo(Dokter::class);
    }

    public function poli()
    {
        return $this->belongsTo(Poli::class);
    }

    public function asesmenPerawat()
    {
        return $this->hasOne(AsesmenPerawat::class);
    }

    public function permintaanPenunjang()
    {
        return $this->hasMany(PermintaanPenunjang::class);
    }

    public function tindakan()
    {
        return $this->hasMany(Tindakan::class);
    }

    public function pemakaianAlkes()
    {
        return $this->hasMany(PemakaianAlkes::class);
    }

    public function resep()
    {
        return $this->hasMany(Resep::class);
    }

    public function invoice()
    {
        return $this->hasOne(Invoice::class)->latestOfMany();
    }

    public function penggunaanAlat()
    {
        return $this->hasMany(PenggunaanAlat::class);
    }

    public function soapNote()
    {
        return $this->hasOne(SoapNote::class);
    }

    public function getWaktuTungguAttribute(): ?string
    {
        if (! $this->waktu_panggil) return null;
        $menit = (int) $this->tanggal->diffInMinutes($this->waktu_panggil);
        if ($menit < 60) return "{$menit} mnt";
        $jam = intdiv($menit, 60);
        $sisa = $menit % 60;
        return "{$jam}j {$sisa}m";
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    protected $table = 'appointments';

    protected $fillable = [
        'kode_booking', 'pasien_id', 'dokter_id', 'poli_id',
        'jadwal_praktek_id', 'tanggal_appointment', 'keluhan',
        'status', 'catatan',
    ];

    protected function casts(): array
    {
        return ['tanggal_appointment' => 'date'];
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

    public function jadwalPraktek()
    {
        return $this->belongsTo(JadwalPraktek::class);
    }

    public function kunjungan()
    {
        return $this->hasOne(Kunjungan::class);
    }

    // Generate kode booking unik
    public static function generateKodeBooking(): string
    {
        do {
            $kode = 'BK-' . strtoupper(substr(md5(uniqid()), 0, 8));
        } while (static::where('kode_booking', $kode)->exists());

        return $kode;
    }

    public static function getStatusLabels(): array
    {
        return [
            'booked'     => 'Terdaftar',
            'checked_in' => 'Check-in',
            'cancelled'  => 'Dibatalkan',
        ];
    }
}

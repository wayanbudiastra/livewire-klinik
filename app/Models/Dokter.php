<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Dokter extends Model
{
    protected $table = 'dokter';

    protected $fillable = [
        'user_id', 'poli_id', 'nip', 'sip', 'spesialisasi', 'jadwal_praktek',
        'nik', 'no_sip', 'tgl_expired_sip',
    ];

    protected function casts(): array
    {
        return [
            'jadwal_praktek'  => 'array',
            'tgl_expired_sip' => 'date',
        ];
    }

    // ── Relasi ──────────────────────────────────────────────

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function poliUtama()
    {
        return $this->belongsTo(Poli::class, 'poli_id');
    }

    public function poli()
    {
        return $this->belongsToMany(Poli::class, 'dokter_poli')
                    ->withPivot('is_aktif')
                    ->withTimestamps();
    }

    public function dokterPoli()
    {
        return $this->hasMany(DokterPoli::class);
    }

    public function sharingFee()
    {
        return $this->hasMany(SharingFee::class);
    }

    public function kunjungan()
    {
        return $this->hasMany(Kunjungan::class);
    }

    // ── SIP Status Helpers ───────────────────────────────────

    public function getSipStatusAttribute(): string
    {
        if (! $this->tgl_expired_sip) return 'tidak_ada';
        $sisa = (int) now()->diffInDays($this->tgl_expired_sip, false);
        if ($sisa < 0)   return 'expired';
        if ($sisa <= 30) return 'segera_expired';
        return 'aktif';
    }

    public function getSipSisaHariAttribute(): ?int
    {
        if (! $this->tgl_expired_sip) return null;
        return (int) now()->diffInDays($this->tgl_expired_sip, false);
    }

    public function isSipAktif(): bool
    {
        return $this->sip_status === 'aktif';
    }

    // ── Scopes ───────────────────────────────────────────────

    public function scopeAktifDanSipValid($query)
    {
        return $query->whereHas('user', fn ($q) => $q->where('is_active', true))
                     ->where('tgl_expired_sip', '>=', now());
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pasien extends Model
{
    use SoftDeletes;

    protected $table = 'pasien';

    protected $fillable = [
        'user_id', 'nomor_rm', 'nik', 'no_paspor', 'negara_asal',
        'nama', 'tempat_lahir', 'tanggal_lahir',
        'jenis_kelamin', 'tipe_pasien',
        'alamat', 'telepon', 'email',
        'golongan_darah', 'alergi',
        'no_bpjs', 'no_asuransi', 'foto', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_lahir' => 'date',
            'is_active'     => 'boolean',
        ];
    }

    // ── Relasi ──────────────────────────────────────────────

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function kontakDarurat()
    {
        return $this->hasMany(KontakDarurat::class)
                    ->orderByDesc('is_primary')
                    ->orderBy('created_at');
    }

    public function kontakPrimary()
    {
        return $this->hasOne(KontakDarurat::class)->where('is_primary', true);
    }

    public function kunjungan()
    {
        return $this->hasMany(Kunjungan::class)->latest('tanggal');
    }

    // ── Scopes ───────────────────────────────────────────────

    public function scopeAktif($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('nama',      'like', "%{$term}%")
              ->orWhere('nomor_rm', 'like', "%{$term}%")
              ->orWhere('nik',      'like', "%{$term}%")
              ->orWhere('no_paspor','like', "%{$term}%")
              ->orWhere('telepon',  'like', "%{$term}%");
        });
    }

    // ── Helpers ──────────────────────────────────────────────

    public function getUmurAttribute(): int
    {
        return $this->tanggal_lahir->age;
    }

    public function getJenisKelaminLabelAttribute(): string
    {
        return $this->jenis_kelamin === 'L' ? 'Laki-laki' : 'Perempuan';
    }

    public static function getHubunganOptions(): array
    {
        return [
            'suami'       => 'Suami',
            'istri'       => 'Istri',
            'ayah'        => 'Ayah',
            'ibu'         => 'Ibu',
            'anak'        => 'Anak',
            'kakak'       => 'Kakak',
            'adik'        => 'Adik',
            'kakek'       => 'Kakek',
            'nenek'       => 'Nenek',
            'paman'       => 'Paman',
            'bibi'        => 'Bibi',
            'keponakan'   => 'Keponakan',
            'teman'       => 'Teman',
            'rekan_kerja' => 'Rekan Kerja',
            'lainnya'     => 'Lainnya',
        ];
    }
}

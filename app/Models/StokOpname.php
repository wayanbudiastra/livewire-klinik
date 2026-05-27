<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class StokOpname extends Model
{
    protected $table    = 'stok_opname';
    protected $fillable = [
        'nomor_opname', 'dibuat_oleh', 'diverifikasi_oleh',
        'tanggal_opname', 'keterangan_periode', 'status', 'catatan',
    ];

    protected function casts(): array
    {
        return ['tanggal_opname' => 'date'];
    }

    public function pembuat():    BelongsTo { return $this->belongsTo(User::class, 'dibuat_oleh'); }
    public function verifikator():BelongsTo { return $this->belongsTo(User::class, 'diverifikasi_oleh'); }
    public function items():      HasMany   { return $this->hasMany(StokOpnameItem::class); }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'draft'                => 'Draft',
            'menunggu_verifikasi'  => 'Menunggu Verifikasi',
            'selesai'              => 'Selesai',
            'dibatalkan'           => 'Dibatalkan',
            default                => $this->status,
        };
    }

    public function getRingkasanAttribute(): array
    {
        return [
            'total_item'    => $this->items->count(),
            'sudah_diisi'   => $this->items->whereNotNull('stok_fisik')->count(),
            'sesuai'        => $this->items->where('tipe_selisih', 'sesuai')->count(),
            'lebih'         => $this->items->where('tipe_selisih', 'lebih')->count(),
            'kurang'        => $this->items->where('tipe_selisih', 'kurang')->count(),
            'nilai_selisih' => (float) $this->items->sum('nilai_selisih'),
        ];
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProposalHarga extends Model
{
    protected $table = 'proposal_harga';

    protected $fillable = [
        'judul', 'tahun', 'tanggal_efektif', 'cakupan', 'catatan',
        'konfigurasi_kenaikan', 'ikut_bpjs', 'status',
        'dibuat_oleh', 'disetujui_oleh', 'diterapkan_oleh', 'ditolak_oleh',
        'disetujui_pada', 'diterapkan_pada', 'ditolak_pada', 'alasan_tolak',
    ];

    protected function casts(): array
    {
        return [
            'konfigurasi_kenaikan' => 'array',
            'ikut_bpjs'            => 'boolean',
            'tanggal_efektif'      => 'date',
            'disetujui_pada'       => 'datetime',
            'diterapkan_pada'      => 'datetime',
            'ditolak_pada'         => 'datetime',
        ];
    }

    // ── Relations ──────────────────────────────────────────

    public function items(): HasMany
    {
        return $this->hasMany(ProposalHargaItem::class);
    }

    public function itemsAktif(): HasMany
    {
        return $this->hasMany(ProposalHargaItem::class)->where('is_skip', false);
    }

    public function dibuatOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    public function disetujuiOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disetujui_oleh');
    }

    public function diterapkanOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diterapkan_oleh');
    }

    // ── Scopes ─────────────────────────────────────────────

    public function scopeDraft($query)       { return $query->where('status', 'draft'); }
    public function scopeMenunggu($query)    { return $query->where('status', 'menunggu_persetujuan'); }
    public function scopeDisetujui($query)   { return $query->where('status', 'disetujui'); }
    public function scopeEfektif($query)     { return $query->where('status', 'efektif'); }
    public function scopeDibatalkan($query)  { return $query->where('status', 'dibatalkan'); }

    // ── Computed Attributes ────────────────────────────────

    public function getBisaDiterapkanAttribute(): bool
    {
        return $this->status === 'disetujui'
            && now()->startOfDay()->gte($this->tanggal_efektif);
    }

    public function getRingkasanAttribute(): array
    {
        $items = $this->items;
        $naik  = $items->where('is_skip', false)->where('harga_baru', '>', 0)
                       ->filter(fn ($i) => $i->harga_baru != $i->harga_lama);
        $skip  = $items->where('is_skip', true);

        $persen = $naik->map(fn ($i) => $i->persen_aktual);

        return [
            'total'    => $items->count(),
            'naik'     => $naik->count(),
            'skip'     => $skip->count(),
            'min_pct'  => $persen->min() ?? 0,
            'max_pct'  => $persen->max() ?? 0,
            'avg_pct'  => $persen->avg() ?? 0,
        ];
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'draft'                  => 'Draft',
            'menunggu_persetujuan'   => 'Menunggu Persetujuan',
            'disetujui'              => 'Disetujui',
            'efektif'                => 'Efektif',
            'dibatalkan'             => 'Dibatalkan',
            default                  => $this->status,
        };
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'draft'                  => 'badge-gray',
            'menunggu_persetujuan'   => 'badge-warning',
            'disetujui'              => 'badge-primary',
            'efektif'                => 'badge-success',
            'dibatalkan'             => 'badge-danger',
            default                  => 'badge-gray',
        };
    }
}

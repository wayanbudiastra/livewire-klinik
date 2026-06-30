<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProposalHargaItem extends Model
{
    protected $table = 'proposal_harga_item';

    protected $fillable = [
        'proposal_harga_id', 'item_type', 'item_id',
        'item_nama', 'item_kategori',
        'harga_lama', 'persen_kenaikan', 'harga_kalkulasi', 'harga_baru',
        'harga_bpjs_lama', 'harga_bpjs_baru',
        'is_dikoreksi_manual', 'is_skip',
        'dikoreksi_oleh', 'dikoreksi_pada',
    ];

    protected function casts(): array
    {
        return [
            'harga_lama'           => 'decimal:2',
            'persen_kenaikan'      => 'decimal:2',
            'harga_kalkulasi'      => 'decimal:2',
            'harga_baru'           => 'decimal:2',
            'harga_bpjs_lama'      => 'decimal:2',
            'harga_bpjs_baru'      => 'decimal:2',
            'is_dikoreksi_manual'  => 'boolean',
            'is_skip'              => 'boolean',
            'dikoreksi_pada'       => 'datetime',
        ];
    }

    // ── Relations ──────────────────────────────────────────

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(ProposalHarga::class, 'proposal_harga_id');
    }

    public function dikorekosiOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dikoreksi_oleh');
    }

    // ── Scopes ─────────────────────────────────────────────

    public function scopeEfektif($query)
    {
        return $query->whereHas('proposal', fn ($q) => $q->where('status', 'efektif'));
    }

    public function scopeUntukItem($query, string $type, int $id)
    {
        return $query->where('item_type', $type)->where('item_id', $id);
    }

    // ── Accessors ──────────────────────────────────────────

    public function getSelisihAttribute(): float
    {
        return (float) $this->harga_baru - (float) $this->harga_lama;
    }

    public function getPersenAktualAttribute(): float
    {
        if ((float) $this->harga_lama == 0) return 0;
        return ($this->selisih / (float) $this->harga_lama) * 100;
    }

    public function getItemTypeLabelAttribute(): string
    {
        return match ($this->item_type) {
            'tindakan' => 'Tindakan',
            'barang'   => 'Barang',
            default    => $this->item_type,
        };
    }
}

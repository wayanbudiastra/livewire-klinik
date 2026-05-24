<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditKasir extends Model
{
    protected $table = 'audit_kasir';

    protected $fillable = [
        'user_id', 'superadmin_id', 'aksi',
        'referensi_tipe', 'referensi_id', 'detail', 'ip_address',
    ];

    protected function casts(): array
    {
        return ['detail' => 'array'];
    }

    public function user(): BelongsTo       { return $this->belongsTo(User::class); }
    public function superadmin(): BelongsTo { return $this->belongsTo(User::class, 'superadmin_id'); }
}

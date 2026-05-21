<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles, SoftDeletes;

    protected $fillable = [
        'nama', 'email', 'password',
        'is_active', 'foto',
        'nip', 'telepon',
        'last_login_at', 'password_changed_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'is_active'           => 'boolean',
            'last_login_at'       => 'datetime',
            'password_changed_at' => 'datetime',
            'email_verified_at'   => 'datetime',
            'password'            => 'hashed',
        ];
    }

    // ── Relasi ──────────────────────────────────────────────

    public function dokter(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Dokter::class);
    }

    // ── Scopes ──────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('nama',  'like', "%{$term}%")
              ->orWhere('email', 'like', "%{$term}%")
              ->orWhere('nip',   'like', "%{$term}%");
        });
    }

    // ── Helpers ─────────────────────────────────────────────

    public function getRoleLabelAttribute(): string
    {
        $first = $this->roles->first();
        $role  = $first ? $first->name : '-';
        return ucfirst(str_replace('_', ' ', $role));
    }

    public function getInitialAttribute(): string
    {
        return strtoupper(substr($this->nama, 0, 1));
    }
}

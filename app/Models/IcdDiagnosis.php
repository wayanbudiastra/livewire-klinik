<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IcdDiagnosis extends Model
{
    protected $table = 'icd10';

    protected $fillable = ['kode', 'nama', 'kategori'];

    public static function search(string $term, int $limit = 15): \Illuminate\Support\Collection
    {
        return static::where('kode', 'like', "%{$term}%")
            ->orWhere('nama', 'like', "%{$term}%")
            ->orderByRaw("CASE WHEN kode LIKE ? THEN 0 ELSE 1 END", ["{$term}%"])
            ->limit($limit)
            ->get(['id', 'kode', 'nama', 'kategori']);
    }
}

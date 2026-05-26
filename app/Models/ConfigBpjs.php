<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConfigBpjs extends Model
{
    protected $table    = 'config_bpjs';
    protected $fillable = [
        'kerjasama', 'is_active', 'kode_faskes', 'nama_faskes',
        'tanggal_kerjasama', 'tanggal_berakhir', 'catatan',
    ];

    protected function casts(): array
    {
        return [
            'kerjasama'         => 'boolean',
            'is_active'         => 'boolean',
            'tanggal_kerjasama' => 'date',
            'tanggal_berakhir'  => 'date',
        ];
    }

    public static function aktif(): bool
    {
        $config = static::first();
        return $config && $config->kerjasama && $config->is_active;
    }
}

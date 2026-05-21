<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SharingFee extends Model
{
    protected $table = 'sharing_fee';

    protected $fillable = ['dokter_id', 'kategori', 'persentase'];

    protected function casts(): array
    {
        return ['persentase' => 'decimal:2'];
    }

    public function dokter()
    {
        return $this->belongsTo(Dokter::class);
    }

    public static function getKategoriLabels(): array
    {
        return [
            'tindakan'  => 'Tindakan Medis',
            'lab'       => 'Laboratorium',
            'radiologi' => 'Radiologi',
            'peralatan' => 'Peralatan Medis',
        ];
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Icd10ImportLog extends Model
{
    protected $table = 'icd10_import_log';

    protected $fillable = [
        'sumber', 'sumber_url', 'versi_who', 'mode',
        'jumlah_baris', 'jumlah_baru', 'jumlah_diperbarui',
        'catatan_qa', 'dijalankan_oleh',
    ];

    public function dijalankanOleh()
    {
        return $this->belongsTo(User::class, 'dijalankan_oleh');
    }
}

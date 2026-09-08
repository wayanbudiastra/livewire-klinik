<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Lampiran dokumen/foto SOAP Note (foto luka, foto hasil jahitan, gambaran
 * radiologis, ekspertise radiologi, surat persetujuan tindakan, dll) --
 * lihat app/Livewire/Pemeriksaan/SoapNote.php. File disimpan di disk
 * 'local' (private), diakses lewat route ber-permission (routes/web.php,
 * pemeriksaan.lampiran.unduh), bukan URL publik.
 */
class SoapLampiran extends Model
{
    protected $table = 'soap_lampiran';

    protected $fillable = [
        'kunjungan_id', 'kategori', 'nama_file', 'path',
        'mime_type', 'ukuran', 'keterangan', 'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'ukuran' => 'integer',
        ];
    }

    public function kunjungan()
    {
        return $this->belongsTo(Kunjungan::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public static function opsiKategori(): array
    {
        return [
            'foto_luka'             => 'Foto Luka',
            'foto_jahitan'          => 'Foto Hasil Jahitan',
            'radiologi'             => 'Gambaran Radiologis',
            'ekspertise_radiologi'  => 'Ekspertise Radiologi',
            'persetujuan_tindakan'  => 'Surat Persetujuan Tindakan',
            'lainnya'               => 'Lainnya',
        ];
    }

    public function getLabelKategoriAttribute(): string
    {
        return self::opsiKategori()[$this->kategori] ?? $this->kategori;
    }

    public function getIsGambarAttribute(): bool
    {
        return str_starts_with((string) $this->mime_type, 'image/');
    }

    public function getUkuranLabelAttribute(): string
    {
        if (! $this->ukuran) return '-';
        return round($this->ukuran / 1024, 1) . ' KB';
    }
}

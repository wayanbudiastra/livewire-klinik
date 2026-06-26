<?php

namespace App\Services\Akuntansi;

use App\Models\Akuntansi\JurnalManual;
use Illuminate\Support\Facades\DB;

class JurnalManualService
{
    public function __construct(
        private JurnalService $jurnal,
        private PeriodeAkuntansiService $periode,
    ) {}

    /**
     * @param array{tanggal:string,kategori:?string,kode_akun_debit:string,kode_akun_kredit:string,
     *              nominal:float,keterangan:string,dokumen_pendukung:?string} $data
     */
    public function buat(array $data, int $userId): JurnalManual
    {
        if ($data['kode_akun_debit'] === $data['kode_akun_kredit']) {
            throw new \DomainException('Akun debit dan akun kredit tidak boleh sama.');
        }

        if (! $this->periode->isTerbuka($data['tanggal'])) {
            throw new \DomainException(
                'Periode untuk tanggal ini sudah ditutup. Pilih tanggal di periode yang masih terbuka, ' .
                'atau buka kembali periode tersebut dulu di menu Tutup Periode.'
            );
        }

        return DB::transaction(function () use ($data, $userId) {
            $jm = JurnalManual::create([
                'tanggal'           => $data['tanggal'],
                'kategori'          => $data['kategori'] ?? null,
                'kode_akun_debit'   => $data['kode_akun_debit'],
                'kode_akun_kredit'  => $data['kode_akun_kredit'],
                'nominal'           => $data['nominal'],
                'keterangan'        => $data['keterangan'],
                'dokumen_pendukung' => $data['dokumen_pendukung'] ?? null,
                'dibuat_oleh'       => $userId,
            ]);

            $this->jurnal->catat(
                sumberTipe:    'jurnal_manual',
                sumberId:      $jm->id,
                tipeTransaksi: 'jurnal_manual',
                tanggal:       $data['tanggal'],
                akunDebit:     $data['kode_akun_debit'],
                akunKredit:    $data['kode_akun_kredit'],
                nominal:       (float) $data['nominal'],
                keterangan:    $data['keterangan'],
            );

            return $jm;
        });
    }

    /** Batalkan entri jurnal manual -- pending diabaikan, posted dibuatkan reversal otomatis. */
    public function batalkan(JurnalManual $jm, int $userId): void
    {
        $this->jurnal->reversal('jurnal_manual', $jm->id, ['jurnal_manual'], $userId);
    }
}

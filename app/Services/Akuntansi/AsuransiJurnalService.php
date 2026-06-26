<?php

namespace App\Services\Akuntansi;

use App\Models\PiutangAsuransi;

class AsuransiJurnalService
{
    const AKUN_KAS                  = '1-1100';
    const AKUN_BANK                 = '1-1200';
    const AKUN_PIUTANG_ASURANSI     = '1-1400';
    const AKUN_PENDAPATAN_KLAIM     = '4-1400';
    const AKUN_PIUTANG_TAK_TERTAGIH = '8-1200';

    public function __construct(private JurnalService $jurnal) {}

    /** Dipanggil saat piutang asuransi terbentuk (PembayaranAsuransiService::prosesPembayaranAsuransi). */
    public function catatPiutangTerbentuk(PiutangAsuransi $piutang): void
    {
        $nominal = (float) $piutang->jumlah_piutang;
        if ($nominal <= 0) return;

        $this->jurnal->catat(
            sumberTipe:    'piutang_asuransi',
            sumberId:      $piutang->id,
            tipeTransaksi: 'piutang_terbentuk',
            tanggal:       $piutang->tanggal_piutang ?? now(),
            akunDebit:     self::AKUN_PIUTANG_ASURANSI,
            akunKredit:    self::AKUN_PENDAPATAN_KLAIM,
            nominal:       $nominal,
            keterangan:    "Piutang asuransi {$piutang->nomor_piutang}",
        );
    }

    /** Dipanggil per-alokasi saat asuransi membayar klaim (PenagihanService::catatPembayaran). */
    public function catatPelunasanPiutang(PiutangAsuransi $piutang, float $alokasi, string $metode, mixed $tanggal): void
    {
        if ($alokasi <= 0) return;

        $akunDebit = $metode === 'tunai' ? self::AKUN_KAS : self::AKUN_BANK;

        $this->jurnal->catat(
            sumberTipe:    'piutang_asuransi',
            sumberId:      $piutang->id,
            tipeTransaksi: 'pelunasan_piutang_asuransi',
            tanggal:       $tanggal,
            akunDebit:     $akunDebit,
            akunKredit:    self::AKUN_PIUTANG_ASURANSI,
            nominal:       $alokasi,
            keterangan:    "Pelunasan piutang {$piutang->nomor_piutang} ({$metode})",
            metadata:      ['metode' => $metode],
        );
    }

    /** Dipanggil saat klaim ditolak / write-off (PenagihanService::tolakKlaim). */
    public function catatWriteOff(PiutangAsuransi $piutang, float $jumlah): void
    {
        if ($jumlah <= 0) return;

        $this->jurnal->catat(
            sumberTipe:    'piutang_asuransi',
            sumberId:      $piutang->id,
            tipeTransaksi: 'writeoff_piutang_asuransi',
            tanggal:       now(),
            akunDebit:     self::AKUN_PIUTANG_TAK_TERTAGIH,
            akunKredit:    self::AKUN_PIUTANG_ASURANSI,
            nominal:       $jumlah,
            keterangan:    "Write-off piutang {$piutang->nomor_piutang} (klaim ditolak)",
        );
    }
}

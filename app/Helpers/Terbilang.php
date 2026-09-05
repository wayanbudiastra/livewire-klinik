<?php

namespace App\Helpers;

/**
 * Konversi angka ke ejaan Bahasa Indonesia (mis. 3 -> "tiga", 12 -> "dua belas").
 * Dipakai di dokumen resmi seperti Surat Keterangan Sakit ("3 (tiga) hari").
 * Cukup untuk rentang wajar (0-999.999); bukan pustaka konversi umum.
 */
class Terbilang
{
    private const SATUAN = [
        '', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan',
        'sepuluh', 'sebelas',
    ];

    public static function convert(int $angka): string
    {
        if ($angka < 0) {
            return 'minus ' . self::convert(abs($angka));
        }

        if ($angka <= 11) {
            return self::SATUAN[$angka];
        }

        if ($angka < 20) {
            return self::convert($angka - 10) . ' belas';
        }

        if ($angka < 100) {
            $puluhan = intdiv($angka, 10);
            $sisa    = $angka % 10;
            return trim(self::convert($puluhan) . ' puluh ' . ($sisa > 0 ? self::convert($sisa) : ''));
        }

        if ($angka < 200) {
            return trim('seratus ' . ($angka - 100 > 0 ? self::convert($angka - 100) : ''));
        }

        if ($angka < 1000) {
            $ratusan = intdiv($angka, 100);
            $sisa    = $angka % 100;
            return trim(self::convert($ratusan) . ' ratus ' . ($sisa > 0 ? self::convert($sisa) : ''));
        }

        if ($angka < 2000) {
            return trim('seribu ' . ($angka - 1000 > 0 ? self::convert($angka - 1000) : ''));
        }

        if ($angka < 1000000) {
            $ribuan = intdiv($angka, 1000);
            $sisa   = $angka % 1000;
            return trim(self::convert($ribuan) . ' ribu ' . ($sisa > 0 ? self::convert($sisa) : ''));
        }

        $jutaan = intdiv($angka, 1000000);
        $sisa   = $angka % 1000000;
        return trim(self::convert($jutaan) . ' juta ' . ($sisa > 0 ? self::convert($sisa) : ''));
    }
}

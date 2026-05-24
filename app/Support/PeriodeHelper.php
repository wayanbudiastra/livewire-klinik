<?php

namespace App\Support;

use Carbon\Carbon;

class PeriodeHelper
{
    public static function resolve(string $tipe, array $params): array
    {
        $tahun = (int) ($params['tahun'] ?? now()->year);

        return match ($tipe) {
            'bulanan'  => self::bulanan($tahun, (int) $params['bulan']),
            'triwulan' => self::triwulan($tahun, (int) $params['triwulan']),
            'semester' => self::semester($tahun, (int) $params['semester']),
            'tahunan'  => self::tahunan($tahun),
            'kustom'   => [
                Carbon::parse($params['tanggal_mulai'])->startOfDay(),
                Carbon::parse($params['tanggal_akhir'])->endOfDay(),
            ],
            default => self::bulanan($tahun, now()->month),
        };
    }

    private static function bulanan(int $tahun, int $bulan): array
    {
        $start = Carbon::create($tahun, $bulan, 1)->startOfMonth();
        return [$start, $start->copy()->endOfMonth()];
    }

    private static function triwulan(int $tahun, int $q): array
    {
        $bulanMulai = ($q - 1) * 3 + 1;
        $start = Carbon::create($tahun, $bulanMulai, 1)->startOfMonth();
        return [$start, $start->copy()->addMonths(2)->endOfMonth()];
    }

    private static function semester(int $tahun, int $s): array
    {
        $bulanMulai = $s === 1 ? 1 : 7;
        $start = Carbon::create($tahun, $bulanMulai, 1)->startOfMonth();
        return [$start, $start->copy()->addMonths(5)->endOfMonth()];
    }

    private static function tahunan(int $tahun): array
    {
        return [
            Carbon::create($tahun, 1, 1)->startOfYear(),
            Carbon::create($tahun, 12, 31)->endOfYear(),
        ];
    }

    public static function label(string $tipe, array $params): string
    {
        $tahun = $params['tahun'] ?? now()->year;
        $namaBulan = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        return match ($tipe) {
            'bulanan'  => "{$namaBulan[$params['bulan']]} {$tahun}",
            'triwulan' => "Triwulan {$params['triwulan']} {$tahun}",
            'semester' => "Semester {$params['semester']} {$tahun}",
            'tahunan'  => "Tahun {$tahun}",
            'kustom'   => Carbon::parse($params['tanggal_mulai'])->format('d/m/Y')
                         . ' – ' . Carbon::parse($params['tanggal_akhir'])->format('d/m/Y'),
            default    => (string) $tahun,
        };
    }
}

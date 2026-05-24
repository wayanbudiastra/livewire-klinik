<?php

namespace App\Exports\Laporan;

use App\Services\Laporan\PharmacyLaporanService;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ObatFastMovingExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithTitle
{
    public function __construct(
        private Carbon $mulai,
        private Carbon $akhir,
        private int $limit = 20
    ) {}

    public function collection()
    {
        return collect(
            app(PharmacyLaporanService::class)
                ->obatFastMoving($this->mulai, $this->akhir, $this->limit)['data']
        );
    }

    public function headings(): array
    {
        return ['Ranking', 'Kode', 'Nama Obat', 'Jenis', 'Total Keluar', 'Frekuensi', 'Stok Sekarang', 'Satuan'];
    }

    public function map($row): array
    {
        static $rank = 0;
        $rank++;
        return [
            $rank,
            $row['kode'],
            $row['nama'],
            $row['jenis'],
            $row['total_keluar'],
            $row['frekuensi'],
            $row['stok_sekarang'],
            $row['satuan'],
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [1 => ['font' => ['bold' => true]]];
    }

    public function title(): string
    {
        return 'Obat Fast Moving';
    }
}

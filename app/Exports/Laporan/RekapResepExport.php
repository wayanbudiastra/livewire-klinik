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

class RekapResepExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithTitle
{
    public function __construct(
        private Carbon $mulai,
        private Carbon $akhir
    ) {}

    public function collection()
    {
        $result = app(PharmacyLaporanService::class)
            ->rekapResep($this->mulai, $this->akhir);

        return collect($result['per_dokter'])->map(fn ($jumlah, $dokter) => [
            'dokter' => $dokter,
            'jumlah' => $jumlah,
        ]);
    }

    public function headings(): array
    {
        return ['Dokter', 'Jumlah Resep'];
    }

    public function map($row): array
    {
        return [$row['dokter'], $row['jumlah']];
    }

    public function styles(Worksheet $sheet)
    {
        return [1 => ['font' => ['bold' => true]]];
    }

    public function title(): string
    {
        return 'Rekap Resep';
    }
}

<?php

namespace App\Exports\Laporan;

use App\Services\Laporan\PemeriksaanLaporanService;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class RekapTindakanExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithTitle
{
    public function __construct(
        private Carbon $mulai,
        private Carbon $akhir
    ) {}

    public function collection()
    {
        $result = app(PemeriksaanLaporanService::class)
            ->rekapTindakan($this->mulai, $this->akhir);

        return collect($result['per_tindakan'])->map(fn ($data, $nama) => [
            'nama'        => $nama,
            'jumlah'      => $data['jumlah'],
            'total_tarif' => $data['total_tarif'],
        ]);
    }

    public function headings(): array
    {
        return ['Nama Tindakan', 'Jumlah', 'Total Tarif (Rp)'];
    }

    public function map($row): array
    {
        return [
            $row['nama'] ?? 'N/A',
            $row['jumlah'],
            $row['total_tarif'],
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [1 => ['font' => ['bold' => true]]];
    }

    public function title(): string
    {
        return 'Rekap Tindakan';
    }
}

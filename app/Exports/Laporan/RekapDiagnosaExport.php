<?php

namespace App\Exports\Laporan;

use App\Services\Laporan\PemeriksaanLaporanService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class RekapDiagnosaExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithTitle
{
    private array $data;

    public function __construct(
        private Carbon $mulai,
        private Carbon $akhir
    ) {}

    public function collection()
    {
        $result = app(PemeriksaanLaporanService::class)
            ->rekapDiagnosa($this->mulai, $this->akhir);
        $this->data = $result;

        return collect($result['semua'])->map(fn ($jumlah, $kode) => ['kode' => $kode, 'jumlah' => $jumlah]);
    }

    public function headings(): array
    {
        return ['Ranking', 'Kode ICD', 'Jumlah Kasus', 'Persentase (%)'];
    }

    public function map($row): array
    {
        static $rank = 0;
        $rank++;
        $total = array_sum($this->data['semua'] ?? []);
        return [
            $rank,
            $row['kode'],
            $row['jumlah'],
            $total > 0 ? round($row['jumlah'] / $total * 100, 2) : 0,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [1 => ['font' => ['bold' => true]]];
    }

    public function title(): string
    {
        return 'Rekap Diagnosa';
    }
}

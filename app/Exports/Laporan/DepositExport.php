<?php

namespace App\Exports\Laporan;

use App\Services\Laporan\KasirLaporanService;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DepositExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithTitle
{
    public function __construct(
        private Carbon $mulai,
        private Carbon $akhir
    ) {}

    public function collection()
    {
        return collect(
            app(KasirLaporanService::class)
                ->deposit($this->mulai, $this->akhir)['detail']
        );
    }

    public function headings(): array
    {
        return ['Tanggal', 'No. Transaksi', 'Pasien', 'No. RM', 'Tipe', 'Jumlah (Rp)', 'Saldo Sesudah (Rp)'];
    }

    public function map($row): array
    {
        return [
            $row['tanggal'],
            $row['nomor'],
            $row['pasien'],
            $row['nomor_rm'],
            ucfirst($row['tipe']),
            $row['jumlah'],
            $row['saldo_sesudah'],
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [1 => ['font' => ['bold' => true]]];
    }

    public function title(): string
    {
        return 'Transaksi Deposit';
    }
}

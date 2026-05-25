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

class CancelBillExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithTitle
{
    public function __construct(
        private Carbon $mulai,
        private Carbon $akhir
    ) {}

    public function collection()
    {
        return collect(
            app(KasirLaporanService::class)
                ->cancelBill($this->mulai, $this->akhir)['detail']
        );
    }

    public function headings(): array
    {
        return [
            'No. Invoice',
            'Tgl Transaksi',
            'Tgl Batal',
            'Pasien',
            'No. RM',
            'Nilai (Rp)',
            'Alasan',
            'Dibatalkan Oleh',
            'Diverifikasi Oleh (SuperAdmin)',
        ];
    }

    public function map($row): array
    {
        return [
            $row['nomor_invoice'],
            $row['tanggal_transaksi'],
            $row['tanggal_batal'] ?? '-',
            $row['pasien'],
            $row['nomor_rm'],
            $row['nilai'],
            $row['alasan'] ?? '-',
            $row['dibatalkan_oleh'],
            $row['diverifikasi_oleh'],
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [1 => ['font' => ['bold' => true]]];
    }

    public function title(): string
    {
        return 'Cancel Bill';
    }
}

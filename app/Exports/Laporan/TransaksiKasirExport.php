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

class TransaksiKasirExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithTitle
{
    public function __construct(
        private Carbon $mulai,
        private Carbon $akhir,
        private ?int $userId = null
    ) {}

    public function collection()
    {
        $result = app(KasirLaporanService::class)
            ->transaksiKasir($this->mulai, $this->akhir, $this->userId);

        return collect($result['per_metode'])->map(fn ($data, $metode) => [
            'metode'              => $metode,
            'jumlah_transaksi'   => $data['jumlah_transaksi'],
            'total'              => $data['total'],
        ]);
    }

    public function headings(): array
    {
        return ['Metode Pembayaran', 'Jumlah Transaksi', 'Total (Rp)'];
    }

    public function map($row): array
    {
        return [ucfirst($row['metode']), $row['jumlah_transaksi'], $row['total']];
    }

    public function styles(Worksheet $sheet)
    {
        return [1 => ['font' => ['bold' => true]]];
    }

    public function title(): string
    {
        return 'Transaksi Kasir';
    }
}

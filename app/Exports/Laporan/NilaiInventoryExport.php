<?php

namespace App\Exports\Laporan;

use App\Services\Laporan\PharmacyLaporanService;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class NilaiInventoryExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithTitle
{
    public function collection()
    {
        return collect(
            app(PharmacyLaporanService::class)->nilaiInventory()['detail']
        );
    }

    public function headings(): array
    {
        return ['Kode', 'Nama', 'Jenis', 'Stok', 'Satuan', 'Harga Pokok (Rp)', 'Nilai (Rp)'];
    }

    public function map($row): array
    {
        return [
            $row['kode'],
            $row['nama'],
            $row['jenis'],
            $row['stok'],
            $row['satuan'],
            $row['harga_pokok'],
            $row['nilai'],
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [1 => ['font' => ['bold' => true]]];
    }

    public function title(): string
    {
        return 'Nilai Inventory';
    }
}

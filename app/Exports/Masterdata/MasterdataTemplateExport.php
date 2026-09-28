<?php

namespace App\Exports\Masterdata;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Template XLS generik untuk download master data (Tindakan, Lab, Radiologi,
 * Peralatan Medis) -- satu class dipakai ulang utk keempatnya, tinggal beda
 * $headings & $exampleRows. Baris contoh disertakan supaya format
 * (posisi kolom, format angka, "Y/N" utk status) jelas bagi yang mengisi.
 */
class MasterdataTemplateExport implements FromArray, WithHeadings, WithTitle, ShouldAutoSize, WithStyles
{
    public function __construct(
        private array $headings,
        private array $exampleRows,
        private string $title,
    ) {}

    public function array(): array
    {
        return $this->exampleRows;
    }

    public function headings(): array
    {
        return $this->headings;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function styles(Worksheet $sheet)
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}

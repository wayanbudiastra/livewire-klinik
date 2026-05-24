<?php

namespace App\Exports\Laporan;

use App\Services\Laporan\RegistrasiLaporanService;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class KunjunganPasienExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithTitle
{
    public function __construct(
        private Carbon $mulai,
        private Carbon $akhir
    ) {}

    public function collection()
    {
        return app(RegistrasiLaporanService::class)
            ->kunjunganPasien($this->mulai, $this->akhir)['detail'];
    }

    public function headings(): array
    {
        return ['Tanggal', 'No. Antrean', 'No. RM', 'Nama Pasien', 'Poli', 'Dokter', 'Tipe Bayar', 'Status'];
    }

    public function map($k): array
    {
        return [
            Carbon::parse($k->tanggal)->format('d/m/Y'),
            $k->nomor_antrean ?? '-',
            $k->pasien->nomor_rm ?? '-',
            $k->pasien->nama ?? '-',
            $k->poli?->nama ?? '-',
            $k->dokter?->user->nama ?? '-',
            $k->tipe_pembayaran ?? 'Umum',
            ucfirst($k->status),
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [1 => ['font' => ['bold' => true]]];
    }

    public function title(): string
    {
        return 'Kunjungan Pasien';
    }
}

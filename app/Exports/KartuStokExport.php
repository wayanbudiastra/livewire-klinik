<?php

namespace App\Exports;

use App\Services\Inventory\KartuStokService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class KartuStokExport implements FromArray, WithHeadings, WithTitle, ShouldAutoSize
{
    public function __construct(private readonly array $data) {}

    public function title(): string
    {
        return "Kartu Stok {$this->data['barang']->kode}";
    }

    public function headings(): array
    {
        return ['Tanggal', 'Waktu', 'Tipe Mutasi', 'Keterangan', 'Masuk', 'Keluar', 'Saldo', 'HPR (Rp)', 'Dicatat Oleh'];
    }

    public function array(): array
    {
        $rows = [];

        $rows[] = [
            $this->data['tanggal_mulai'], '—', 'Saldo Awal', 'Saldo awal periode',
            '—', '—', $this->data['saldo_awal'], $this->data['hpr_awal'], '—',
        ];

        foreach ($this->data['rows'] as $r) {
            $rows[] = [
                $r['tanggal'],
                $r['waktu'],
                KartuStokService::getTipeLabel($r['tipe']),
                $r['keterangan'] ?? ($r['referensi_tipe'] ? "{$r['referensi_tipe']}#{$r['referensi_id']}" : ''),
                $r['masuk'] > 0 ? $r['masuk'] : '',
                $r['keluar'] > 0 ? $r['keluar'] : '',
                $r['saldo'],
                $r['hpr'],
                $r['user_nama'],
            ];
        }

        $rows[] = [
            '', '', '', 'TOTAL PERIODE',
            $this->data['total_masuk'],
            $this->data['total_keluar'],
            $this->data['saldo_akhir'],
            '', '',
        ];

        return $rows;
    }
}

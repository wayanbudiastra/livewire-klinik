<?php

namespace App\Services\Laporan;

use App\Models\Kunjungan;
use App\Models\SoapNote;
use App\Models\Tindakan;
use Carbon\Carbon;

class PemeriksaanLaporanService
{
    public function rekapDiagnosa(Carbon $mulai, Carbon $akhir): array
    {
        $soap = SoapNote::whereHas('kunjungan', fn ($q) =>
                $q->whereBetween('tanggal', [$mulai, $akhir])
            )
            ->whereNotNull('icd_codes')
            ->with('kunjungan.poli')
            ->get();

        $diagnosaCounter = [];
        foreach ($soap as $note) {
            foreach ($note->icd_codes ?? [] as $icd) {
                $key = is_array($icd) ? ($icd['code'] ?? $icd['kode'] ?? json_encode($icd)) : $icd;
                $diagnosaCounter[$key] = ($diagnosaCounter[$key] ?? 0) + 1;
            }
        }
        arsort($diagnosaCounter);

        return [
            'total_diagnosa' => array_sum($diagnosaCounter),
            'jumlah_jenis'   => count($diagnosaCounter),
            'sepuluh_besar'  => array_slice($diagnosaCounter, 0, 10, true),
            'semua'          => $diagnosaCounter,
        ];
    }

    public function rekapTindakan(Carbon $mulai, Carbon $akhir): array
    {
        $tindakan = Tindakan::whereHas('kunjungan', fn ($q) =>
                $q->whereBetween('tanggal', [$mulai, $akhir])
            )
            ->with('masterTindakan')
            ->get();

        return [
            'total_tindakan' => $tindakan->sum('jumlah'),
            'per_tindakan'   => $tindakan->groupBy('masterTindakan.nama')
                                  ->map(fn ($g) => [
                                      'jumlah'      => $g->sum('jumlah'),
                                      'total_tarif' => $g->sum(fn ($t) =>
                                          $t->jumlah * ($t->masterTindakan->tarif ?? 0)),
                                  ])
                                  ->sortByDesc('jumlah'),
        ];
    }

    public function rekapPerPoli(Carbon $mulai, Carbon $akhir): array
    {
        return Kunjungan::whereBetween('tanggal', [$mulai, $akhir])
            ->where('status', '!=', 'dibatalkan')
            ->selectRaw('poli_id, COUNT(*) as total')
            ->with('poli')
            ->groupBy('poli_id')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => [
                'poli'  => $r->poli?->nama ?? 'Tanpa Poli',
                'total' => $r->total,
            ])
            ->toArray();
    }

    public function rekapPerDokter(Carbon $mulai, Carbon $akhir): array
    {
        return Kunjungan::whereBetween('tanggal', [$mulai, $akhir])
            ->where('status', 'selesai')
            ->whereNotNull('dokter_id')
            ->selectRaw('dokter_id, COUNT(*) as total_pasien')
            ->with('dokter.user', 'dokter.poli')
            ->groupBy('dokter_id')
            ->orderByDesc('total_pasien')
            ->get()
            ->map(fn ($r) => [
                'dokter'       => 'dr. ' . ($r->dokter?->user->nama ?? '-'),
                'spesialisasi' => $r->dokter?->spesialisasi ?? '-',
                'poli'         => $r->dokter?->poli?->nama ?? '-',
                'total_pasien' => $r->total_pasien,
            ])
            ->toArray();
    }
}

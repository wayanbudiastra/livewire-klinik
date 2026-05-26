<?php

namespace App\Services\Laporan;

use App\Models\Appointment;
use App\Models\Kunjungan;
use App\Models\Pasien;
use Carbon\Carbon;

class RegistrasiLaporanService
{
    public function kunjunganPasien(Carbon $mulai, Carbon $akhir): array
    {
        $kunjungan = Kunjungan::query()
            ->whereBetween('tanggal', [$mulai, $akhir])
            ->where('status', '!=', 'dibatalkan')
            ->with(['pasien', 'poli', 'dokter.user'])
            ->get();

        $pasienBaru = Pasien::whereBetween('created_at', [$mulai, $akhir])->count();

        return [
            'total_kunjungan' => $kunjungan->count(),
            'pasien_baru'     => $pasienBaru,
            'pasien_lama'     => $kunjungan->count() - $pasienBaru,
            'per_poli'        => $kunjungan->groupBy('poli.nama')
                                   ->map->count()->sortDesc(),
            'per_tipe_bayar'  => $kunjungan->groupBy('tipe_pembayaran')
                                   ->map->count(),
            'per_hari'        => $kunjungan->groupBy(fn ($k) => $k->tanggal instanceof Carbon
                                       ? $k->tanggal->format('Y-m-d')
                                       : Carbon::parse($k->tanggal)->format('Y-m-d'))
                                   ->map->count(),
            'detail'          => $kunjungan,
        ];
    }

    public function batalRegistrasi(Carbon $mulai, Carbon $akhir): array
    {
        $batal = Kunjungan::whereBetween('tanggal', [$mulai, $akhir])
            ->where('status', 'dibatalkan')
            ->with(['pasien', 'poli'])
            ->get();

        return [
            'total_batal' => $batal->count(),
            'per_poli'    => $batal->groupBy('poli.nama')->map->count(),
            'detail'      => $batal,
        ];
    }

    public function appointment(Carbon $mulai, Carbon $akhir): array
    {
        $appointment = Appointment::whereBetween('tanggal_appointment', [$mulai, $akhir])
            ->with(['pasien', 'poli', 'dokter.user'])
            ->get();

        return [
            'total'       => $appointment->count(),
            'hadir'       => $appointment->where('status', 'hadir')->count(),
            'tidak_hadir' => $appointment->where('status', 'tidak_hadir')->count(),
            'dibatalkan'  => $appointment->where('status', 'cancelled')->count(),
            'rasio_hadir' => $appointment->count() > 0
                ? round($appointment->where('status', 'hadir')->count() / $appointment->count() * 100, 1)
                : 0,
            'detail'      => $appointment,
        ];
    }

    public function rekapWargaNegara(Carbon $mulai, Carbon $akhir): array
    {
        $kunjungan = Kunjungan::whereBetween('tanggal', [$mulai, $akhir])
            ->where('status', '!=', 'dibatalkan')
            ->with('pasien')
            ->get();

        $wni = $kunjungan->filter(fn ($k) => $k->pasien?->tipe_pasien === 'WNI');
        $wna = $kunjungan->filter(fn ($k) => $k->pasien?->tipe_pasien === 'WNA');

        return [
            'total_wni'      => $wni->pluck('pasien_id')->unique()->count(),
            'total_wna'      => $wna->pluck('pasien_id')->unique()->count(),
            'kunjungan_wni'  => $wni->count(),
            'kunjungan_wna'  => $wna->count(),
            'wna_per_negara' => $wna->groupBy('pasien.negara_asal')
                                  ->map(fn ($g) => $g->pluck('pasien_id')->unique()->count())
                                  ->sortDesc(),
            'detail_wna'     => $wna->unique('pasien_id')->map(fn ($k) => [
                'nomor_rm'    => $k->pasien->nomor_rm,
                'nama'        => $k->pasien->nama,
                'no_paspor'   => $k->pasien->no_paspor,
                'negara_asal' => $k->pasien->negara_asal,
            ]),
        ];
    }

    public function sumberInformasi(Carbon $mulai, Carbon $akhir): array
    {
        $pasien = Pasien::whereBetween('created_at', [$mulai->startOfDay(), $akhir->copy()->endOfDay()])
            ->with('sumberInformasi')
            ->get();

        $total = $pasien->count();

        $perSumber = $pasien
            ->groupBy(fn ($p) => $p->sumberInformasi?->nama ?? 'Tidak Tercatat')
            ->map(fn ($g) => [
                'jumlah'   => $g->count(),
                'persen'   => $total > 0 ? round($g->count() / $total * 100, 1) : 0,
                'icon'     => $g->first()->sumberInformasi?->icon ?? '❓',
                'kategori' => $g->first()->sumberInformasi?->kategori ?? 'lainnya',
            ])
            ->sortByDesc('jumlah');

        $perKategori = $pasien
            ->groupBy(fn ($p) => $p->sumberInformasi?->kategori ?? 'lainnya')
            ->map->count()
            ->sortDesc();

        $detailLainnya = $pasien
            ->filter(fn ($p) => $p->sumberInformasi?->butuh_keterangan && $p->sumber_informasi_keterangan)
            ->groupBy('sumber_informasi_keterangan')
            ->map->count()
            ->sortDesc();

        return [
            'total_pasien_baru' => $total,
            'per_sumber'        => $perSumber,
            'per_kategori'      => $perKategori,
            'detail_lainnya'    => $detailLainnya,
            'tidak_tercatat'    => $pasien->whereNull('sumber_informasi_id')->count(),
        ];
    }
}

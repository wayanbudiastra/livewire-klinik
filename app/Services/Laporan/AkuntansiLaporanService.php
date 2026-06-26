<?php

namespace App\Services\Laporan;

use App\Models\Akuntansi\ChartOfAccount;
use App\Models\Akuntansi\JurnalUmum;
use Illuminate\Support\Carbon;

class AkuntansiLaporanService
{
    /** Buku besar satu akun: daftar transaksi + saldo berjalan (running balance). */
    public function bukuBesar(string $kodeAkun, string $dari, string $sampai): array
    {
        $akun = ChartOfAccount::where('kode', $kodeAkun)->firstOrFail();

        $saldoAwal = $this->hitungSaldo($akun, null, Carbon::parse($dari)->subDay()->toDateString());

        $rows = JurnalUmum::where(function ($q) use ($kodeAkun) {
                $q->where('kode_akun_debit', $kodeAkun)->orWhere('kode_akun_kredit', $kodeAkun);
            })
            ->whereBetween('tanggal', [$dari, $sampai])
            ->orderBy('tanggal')
            ->orderBy('id')
            ->get();

        $saldoBerjalan = $saldoAwal;
        $baris = [];

        foreach ($rows as $r) {
            $debit  = $r->kode_akun_debit === $kodeAkun ? (float) $r->nominal : 0;
            $kredit = $r->kode_akun_kredit === $kodeAkun ? (float) $r->nominal : 0;

            $saldoBerjalan += $akun->tipe_normal === 'debit' ? ($debit - $kredit) : ($kredit - $debit);

            $baris[] = [
                'tanggal'     => $r->tanggal,
                'nomor'       => $r->nomor_jurnal,
                'keterangan'  => $r->keterangan,
                'debit'       => $debit,
                'kredit'      => $kredit,
                'saldo'       => $saldoBerjalan,
            ];
        }

        return [
            'akun'       => $akun,
            'saldo_awal' => $saldoAwal,
            'baris'      => $baris,
            'saldo_akhir' => $saldoBerjalan,
        ];
    }

    /** Neraca saldo: semua akun + total debit/kredit kumulatif s/d tanggal tertentu. */
    public function neracaSaldo(string $sampai): array
    {
        $akunList = ChartOfAccount::aktif()->orderBy('kode')->get();

        $baris = [];
        $totalDebit  = 0;
        $totalKredit = 0;

        foreach ($akunList as $akun) {
            $debit  = (float) JurnalUmum::where('kode_akun_debit', $akun->kode)->whereDate('tanggal', '<=', $sampai)->sum('nominal');
            $kredit = (float) JurnalUmum::where('kode_akun_kredit', $akun->kode)->whereDate('tanggal', '<=', $sampai)->sum('nominal');

            $saldo = $akun->tipe_normal === 'debit' ? ($debit - $kredit) : ($kredit - $debit);
            if ($saldo == 0) continue;

            $saldoDebit  = $akun->tipe_normal === 'debit' ? max($saldo, 0) : max(-$saldo, 0);
            $saldoKredit = $akun->tipe_normal === 'kredit' ? max($saldo, 0) : max(-$saldo, 0);

            $baris[] = [
                'akun'   => $akun,
                'debit'  => $saldoDebit,
                'kredit' => $saldoKredit,
            ];

            $totalDebit  += $saldoDebit;
            $totalKredit += $saldoKredit;
        }

        return [
            'baris'        => $baris,
            'total_debit'  => $totalDebit,
            'total_kredit' => $totalKredit,
            'seimbang'     => abs($totalDebit - $totalKredit) < 0.01,
        ];
    }

    /** Laba rugi sederhana per periode: pendapatan dikurangi biaya. */
    public function labaRugi(string $dari, string $sampai): array
    {
        $pendapatanAkun = ChartOfAccount::aktif()->where('golongan', 'pendapatan')->orderBy('kode')->get();
        $biayaAkun      = ChartOfAccount::aktif()->where('golongan', 'biaya')->orderBy('kode')->get();

        $hitungSaldoPeriode = function (ChartOfAccount $akun) use ($dari, $sampai) {
            $debit  = (float) JurnalUmum::where('kode_akun_debit', $akun->kode)->whereBetween('tanggal', [$dari, $sampai])->sum('nominal');
            $kredit = (float) JurnalUmum::where('kode_akun_kredit', $akun->kode)->whereBetween('tanggal', [$dari, $sampai])->sum('nominal');

            return $akun->tipe_normal === 'kredit' ? ($kredit - $debit) : ($debit - $kredit);
        };

        $pendapatan = $pendapatanAkun->map(fn ($a) => ['akun' => $a, 'nominal' => $hitungSaldoPeriode($a)])
            ->filter(fn ($r) => $r['nominal'] != 0)->values();
        $biaya = $biayaAkun->map(fn ($a) => ['akun' => $a, 'nominal' => $hitungSaldoPeriode($a)])
            ->filter(fn ($r) => $r['nominal'] != 0)->values();

        $totalPendapatan = $pendapatan->sum('nominal');
        $totalBiaya      = $biaya->sum('nominal');

        return [
            'pendapatan'       => $pendapatan,
            'biaya'            => $biaya,
            'total_pendapatan' => $totalPendapatan,
            'total_biaya'      => $totalBiaya,
            'laba_rugi'        => $totalPendapatan - $totalBiaya,
        ];
    }

    private function hitungSaldo(ChartOfAccount $akun, ?string $dari, string $sampai): float
    {
        $query = JurnalUmum::query();
        $debit  = (float) (clone $query)->where('kode_akun_debit', $akun->kode)->whereDate('tanggal', '<=', $sampai)->sum('nominal');
        $kredit = (float) (clone $query)->where('kode_akun_kredit', $akun->kode)->whereDate('tanggal', '<=', $sampai)->sum('nominal');

        return $akun->tipe_normal === 'debit' ? ($debit - $kredit) : ($kredit - $debit);
    }
}

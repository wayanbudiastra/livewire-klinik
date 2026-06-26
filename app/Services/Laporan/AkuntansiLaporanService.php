<?php

namespace App\Services\Laporan;

use App\Models\Akuntansi\ChartOfAccount;
use App\Models\Akuntansi\JurnalUmum;
use App\Models\Akuntansi\PeriodeAkuntansi;
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

    /**
     * Tren bulanan pendapatan & laba bersih dari Januari s/d bulan berjalan (year-to-date),
     * untuk grafik pertumbuhan di halaman Laba Rugi.
     */
    public function trendLabaRugiYtd(?int $tahun = null): array
    {
        $tahun     = $tahun ?? (int) now()->format('Y');
        $bulanAkhir = $tahun === (int) now()->format('Y') ? (int) now()->format('n') : 12;

        $bulanNama = [
            1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun',
            7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des',
        ];

        $labels    = [];
        $pendapatan = [];
        $labaBersih = [];

        for ($bulan = 1; $bulan <= $bulanAkhir; $bulan++) {
            $dari   = Carbon::create($tahun, $bulan, 1)->startOfMonth()->toDateString();
            $sampai = Carbon::create($tahun, $bulan, 1)->endOfMonth()->toDateString();

            $hasil = $this->labaRugi($dari, $sampai);

            $labels[]     = $bulanNama[$bulan];
            $pendapatan[] = round((float) $hasil['total_pendapatan'], 2);
            $labaBersih[] = round((float) $hasil['laba_rugi'], 2);
        }

        return [
            'tahun'       => $tahun,
            'labels'      => $labels,
            'pendapatan'  => $pendapatan,
            'laba_bersih' => $labaBersih,
        ];
    }

    private function hitungSaldo(ChartOfAccount $akun, ?string $dari, string $sampai): float
    {
        return $akun->saldoSampai($sampai);
    }

    /** Saldo akun (sisi normal) per kode akun & tanggal cutoff -- dipakai Neraca & Arus Kas. */
    public function saldoAkunSampai(string $kodeAkun, string $tanggal): float
    {
        return ChartOfAccount::where('kode', $kodeAkun)->firstOrFail()->saldoSampai($tanggal);
    }

    /**
     * Neraca (Balance Sheet) per tanggal cutoff -- Aset = Liabilitas + Ekuitas.
     * Laba Ditahan & Laba Tahun Berjalan dihitung dinamis dari labaRugi(), bukan dari
     * jurnal penutup fisik (lihat prd/modul_akuntansi_update.md §5.2).
     */
    public function neraca(string $tanggal, ?string $tanggalPembanding = null): array
    {
        $hasil = $this->hitungNeraca($tanggal);

        if ($tanggalPembanding) {
            $hasil['pembanding'] = $this->hitungNeraca($tanggalPembanding);
        }

        return $hasil;
    }

    private function hitungNeraca(string $tanggal): array
    {
        $epoch = '2000-01-01'; // batas awal pencarian Laba Ditahan (lebih awal dari data tertua sistem)
        $tahunCutoff = (int) Carbon::parse($tanggal)->format('Y');

        $baseline = fn (ChartOfAccount $a) => ['akun' => $a, 'nominal' => $a->saldoSampai($tanggal)];

        $asetLancar      = ChartOfAccount::aktif()->where('golongan', 'aset')->where('kelompok', 'lancar')->orderBy('kode')->get()->map($baseline)->filter(fn ($r) => $r['nominal'] != 0)->values();
        $asetTidakLancar = ChartOfAccount::aktif()->where('golongan', 'aset')->where('kelompok', 'tidak_lancar')->orderBy('kode')->get()->map($baseline)->filter(fn ($r) => $r['nominal'] != 0)->values();

        $liabilitasPendek  = ChartOfAccount::aktif()->where('golongan', 'liabilitas')->where('kelompok', 'jangka_pendek')->orderBy('kode')->get()->map($baseline)->filter(fn ($r) => $r['nominal'] != 0)->values();
        $liabilitasPanjang = ChartOfAccount::aktif()->where('golongan', 'liabilitas')->where('kelompok', 'jangka_panjang')->orderBy('kode')->get()->map($baseline)->filter(fn ($r) => $r['nominal'] != 0)->values();

        // Ekuitas: akun ekuitas selain 3-1200 (Laba Ditahan) -- nilai 3-1200 & laba tahun
        // berjalan dihitung dinamis di bawah, bukan dari saldo aktual akun itu.
        $ekuitasLain = ChartOfAccount::aktif()->where('golongan', 'ekuitas')->where('kode', '!=', '3-1200')
            ->orderBy('kode')->get()->map($baseline)->filter(fn ($r) => $r['nominal'] != 0)->values();

        $labaDitahan = $this->labaRugi($epoch, Carbon::create($tahunCutoff - 1, 12, 31)->toDateString())['laba_rugi'];
        $labaTahunBerjalan = $this->labaRugi(Carbon::create($tahunCutoff, 1, 1)->toDateString(), $tanggal)['laba_rugi'];

        $totalAsetLancar      = $asetLancar->sum('nominal');
        $totalAsetTidakLancar = $asetTidakLancar->sum('nominal');
        $totalAset            = $totalAsetLancar + $totalAsetTidakLancar;

        $totalLiabilitasPendek  = $liabilitasPendek->sum('nominal');
        $totalLiabilitasPanjang = $liabilitasPanjang->sum('nominal');
        $totalLiabilitas        = $totalLiabilitasPendek + $totalLiabilitasPanjang;

        $totalEkuitasLain = $ekuitasLain->sum('nominal');
        $totalEkuitas     = $totalEkuitasLain + $labaDitahan + $labaTahunBerjalan;

        $totalLiabilitasEkuitas = $totalLiabilitas + $totalEkuitas;
        $selisih = $totalAset - $totalLiabilitasEkuitas;

        $periodeBelumDitutup = PeriodeAkuntansi::terbuka()
            ->where(function ($q) use ($tahunCutoff) {
                $q->where('tahun', '<', $tahunCutoff);
            })
            ->orderBy('tahun')->orderBy('bulan')
            ->get()
            ->map(fn ($p) => $p->label)
            ->values();

        return [
            'tanggal'                  => $tanggal,
            'aset_lancar'              => $asetLancar,
            'total_aset_lancar'        => $totalAsetLancar,
            'aset_tidak_lancar'        => $asetTidakLancar,
            'total_aset_tidak_lancar'  => $totalAsetTidakLancar,
            'total_aset'               => $totalAset,
            'liabilitas_pendek'        => $liabilitasPendek,
            'total_liabilitas_pendek'  => $totalLiabilitasPendek,
            'liabilitas_panjang'       => $liabilitasPanjang,
            'total_liabilitas_panjang' => $totalLiabilitasPanjang,
            'total_liabilitas'         => $totalLiabilitas,
            'ekuitas_lain'             => $ekuitasLain,
            'laba_ditahan'             => $labaDitahan,
            'laba_tahun_berjalan'      => $labaTahunBerjalan,
            'total_ekuitas'            => $totalEkuitas,
            'total_liabilitas_ekuitas' => $totalLiabilitasEkuitas,
            'selisih'                  => $selisih,
            'seimbang'                 => abs($selisih) < 0.01,
            'periode_belum_ditutup'    => $periodeBelumDitutup,
            'pembanding'               => null,
        ];
    }

    /**
     * Laporan Arus Kas (Direct Method) per rentang tanggal -- mengklasifikasikan
     * pergerakan kas/bank aktual ke Aktivitas Operasi/Investasi/Pendanaan berdasarkan
     * golongan+kelompok akun lawan (lihat prd/modul_akuntansi_update.md §6.2).
     */
    public function arusKas(string $dari, string $sampai): array
    {
        $kodeKas = ChartOfAccount::kasSetaraKas()->pluck('kode')->all();

        if (empty($kodeKas)) {
            return [
                'dari' => $dari, 'sampai' => $sampai,
                'operasi' => collect(), 'investasi' => collect(), 'pendanaan' => collect(),
                'total_operasi' => 0, 'total_investasi' => 0, 'total_pendanaan' => 0,
                'kenaikan_bersih' => 0, 'saldo_awal' => 0, 'saldo_akhir' => 0, 'cocok_neraca' => true,
            ];
        }

        $rows = JurnalUmum::where(function ($q) use ($kodeKas) {
                $q->whereIn('kode_akun_debit', $kodeKas)->orWhereIn('kode_akun_kredit', $kodeKas);
            })
            ->whereBetween('tanggal', [$dari, $sampai])
            ->get();

        $akunCache = ChartOfAccount::all()->keyBy('kode');
        $kelompokAktivitas = [];

        foreach ($rows as $r) {
            $debitKas  = in_array($r->kode_akun_debit, $kodeKas, true);
            $kreditKas = in_array($r->kode_akun_kredit, $kodeKas, true);

            // Transfer internal antar akun kas/bank -- tidak mengubah total kas, lewati.
            if ($debitKas && $kreditKas) continue;
            if (! $debitKas && ! $kreditKas) continue;

            $kodeLawan = $debitKas ? $r->kode_akun_kredit : $r->kode_akun_debit;
            $arah      = $debitKas ? 1 : -1; // debit ke kas = masuk (+), kredit dari kas = keluar (-)
            $nominal   = (float) $r->nominal * $arah;

            $akunLawan = $akunCache->get($kodeLawan);
            $aktivitas = $this->klasifikasiAktivitasKas($akunLawan);

            $key = $aktivitas . '|' . $kodeLawan;
            if (! isset($kelompokAktivitas[$key])) {
                $kelompokAktivitas[$key] = [
                    'aktivitas' => $aktivitas,
                    'akun'      => $akunLawan,
                    'nominal'   => 0.0,
                ];
            }
            $kelompokAktivitas[$key]['nominal'] += $nominal;
        }

        $semua     = collect($kelompokAktivitas)->values();
        $operasi   = $semua->where('aktivitas', 'operasi')->values();
        $investasi = $semua->where('aktivitas', 'investasi')->values();
        $pendanaan = $semua->where('aktivitas', 'pendanaan')->values();

        $totalOperasi   = $operasi->sum('nominal');
        $totalInvestasi = $investasi->sum('nominal');
        $totalPendanaan = $pendanaan->sum('nominal');
        $kenaikanBersih = $totalOperasi + $totalInvestasi + $totalPendanaan;

        $saldoAwal  = (float) collect($kodeKas)->sum(fn ($k) => $this->saldoAkunSampai($k, Carbon::parse($dari)->subDay()->toDateString()));
        $saldoAkhir = (float) collect($kodeKas)->sum(fn ($k) => $this->saldoAkunSampai($k, $sampai));

        return [
            'dari'             => $dari,
            'sampai'           => $sampai,
            'operasi'          => $operasi,
            'investasi'        => $investasi,
            'pendanaan'        => $pendanaan,
            'total_operasi'    => $totalOperasi,
            'total_investasi'  => $totalInvestasi,
            'total_pendanaan'  => $totalPendanaan,
            'kenaikan_bersih'  => $kenaikanBersih,
            'saldo_awal'       => $saldoAwal,
            'saldo_akhir'      => $saldoAkhir,
            'cocok_neraca'     => abs($saldoAwal + $kenaikanBersih - $saldoAkhir) < 0.01,
        ];
    }

    private function klasifikasiAktivitasKas(?ChartOfAccount $akunLawan): string
    {
        if (! $akunLawan) return 'operasi';

        return match (true) {
            in_array($akunLawan->golongan, ['pendapatan', 'biaya'], true) => 'operasi',
            $akunLawan->golongan === 'aset' && $akunLawan->kelompok === 'tidak_lancar' => 'investasi',
            $akunLawan->golongan === 'aset' => 'operasi', // aset lancar lain (persediaan, piutang) -> modal kerja operasi
            $akunLawan->golongan === 'liabilitas' && $akunLawan->kelompok === 'jangka_panjang' => 'pendanaan',
            $akunLawan->golongan === 'liabilitas' => 'operasi', // jangka pendek (hutang dagang, dst)
            $akunLawan->golongan === 'ekuitas' => 'pendanaan',
            default => 'operasi',
        };
    }
}

<?php

namespace App\Services\Akuntansi;

use App\Models\Akuntansi\JurnalPending;
use App\Models\Akuntansi\JurnalUmum;
use Illuminate\Support\Facades\DB;

class JurnalService
{
    /**
     * Catat satu baris draft jurnal (status: pending) dari transaksi sumber.
     * Dipanggil oleh service generator jurnal per modul (Inventori, Billing, Ritel, dst).
     */
    public function catat(
        string $sumberTipe,
        int $sumberId,
        string $tipeTransaksi,
        mixed $tanggal,
        string $akunDebit,
        string $akunKredit,
        float $nominal,
        ?string $keterangan = null,
        array $metadata = []
    ): JurnalPending {
        return JurnalPending::create([
            'sumber_tipe'       => $sumberTipe,
            'sumber_id'         => $sumberId,
            'tipe_transaksi'    => $tipeTransaksi,
            'tanggal_transaksi' => $tanggal,
            'kode_akun_debit'   => $akunDebit,
            'kode_akun_kredit'  => $akunKredit,
            'nominal'           => $nominal,
            'keterangan'        => $keterangan,
            'metadata'          => $metadata,
            'status'            => 'pending',
        ]);
    }

    /**
     * Posting satu atau beberapa baris jurnal_pending menjadi jurnal_umum permanen.
     * Setiap baris pending menghasilkan satu baris jurnal_umum baru.
     *
     * @throws \DomainException jika salah satu baris bertanggal di periode yang sudah ditutup
     *         (lihat PeriodeAkuntansiService) -- seluruh batch dibatalkan, tidak ada yang
     *         diposting sebagian.
     */
    public function posting(array $jurnalPendingIds, int $userId): array
    {
        $diposting = [];
        $periodeService = app(PeriodeAkuntansiService::class);

        DB::transaction(function () use ($jurnalPendingIds, $userId, $periodeService, &$diposting) {
            $rows = JurnalPending::pending()->whereIn('id', $jurnalPendingIds)->lockForUpdate()->get();

            foreach ($rows as $row) {
                if (! $periodeService->isTerbuka($row->tanggal_transaksi)) {
                    $tgl = \Illuminate\Support\Carbon::parse($row->tanggal_transaksi);
                    throw new \DomainException(
                        "Periode {$tgl->translatedFormat('F Y')} sudah ditutup. " .
                        'Buka kembali periode ini dulu jika ingin posting jurnal bertanggal di bulan tersebut.'
                    );
                }
            }

            foreach ($rows as $row) {
                $jurnalUmum = JurnalUmum::create([
                    'nomor_jurnal'     => JurnalUmum::generateNomor(),
                    'tanggal'          => $row->tanggal_transaksi,
                    'kode_akun_debit'  => $row->kode_akun_debit,
                    'kode_akun_kredit' => $row->kode_akun_kredit,
                    'nominal'          => $row->nominal,
                    'keterangan'       => $row->keterangan,
                    'sumber_tipe'      => $row->sumber_tipe,
                    'sumber_id'        => $row->sumber_id,
                    'diposting_oleh'   => $userId,
                    'diposting_pada'   => now(),
                ]);

                $row->update([
                    'status'         => 'posted',
                    'posted_at'      => now(),
                    'jurnal_umum_id' => $jurnalUmum->id,
                ]);

                $diposting[] = $jurnalUmum;
            }
        });

        return $diposting;
    }

    /** Tandai baris jurnal_pending sebagai diabaikan (tidak diposting). */
    public function abaikan(int $jurnalPendingId, ?string $alasan = null): JurnalPending
    {
        $row = JurnalPending::pending()->findOrFail($jurnalPendingId);

        $keterangan = $row->keterangan;
        if ($alasan) {
            $keterangan .= " [Diabaikan: {$alasan}]";
        }

        $row->update([
            'status'     => 'diabaikan',
            'keterangan' => $keterangan,
        ]);

        return $row;
    }

    /**
     * Batalkan seluruh jurnal yang terkait sebuah transaksi sumber (dipanggil saat
     * transaksi sumber-nya dibatalkan/reversal — billing dibatalkan, ritel dibatalkan, dst).
     *
     * - Baris yang masih 'pending' -> langsung diabaikan. Belum pernah masuk buku besar,
     *   jadi tidak perlu reversal, cukup dicoret dari antrian posting.
     * - Baris yang sudah 'posted' -> dibuatkan jurnal reversal (debit/kredit dibalik dari
     *   baris asli) dan LANGSUNG diposting otomatis. Ini krusial: kalau reversal dibiarkan
     *   pending menunggu review manual, Buku Besar/Neraca Saldo/Laba Rugi akan tetap salah
     *   (menampilkan pendapatan yang sudah dibatalkan) sampai ada yang posting manual.
     *   Reversal murni hasil sistem dari pembatalan yang sudah disetujui (password SuperAdmin),
     *   tidak butuh judgment call tambahan, sehingga aman diposting otomatis.
     */
    public function reversal(string $sumberTipe, int $sumberId, array $tipeTransaksi, int $userId): array
    {
        $rows = JurnalPending::where('sumber_tipe', $sumberTipe)
            ->where('sumber_id', $sumberId)
            ->whereIn('tipe_transaksi', $tipeTransaksi)
            ->whereIn('status', ['pending', 'posted'])
            ->get();

        $hasilReversal = [];

        DB::transaction(function () use ($rows, $userId, &$hasilReversal) {
            foreach ($rows as $row) {
                if ($row->status === 'pending') {
                    $this->abaikan(
                        $row->id,
                        'Dibatalkan otomatis: transaksi sumber dibatalkan sebelum jurnal diposting.'
                    );
                    continue;
                }

                // status === 'posted' -> buat reversal dan langsung posting
                $reversalPending = $this->catat(
                    sumberTipe:    $row->sumber_tipe,
                    sumberId:      $row->sumber_id,
                    tipeTransaksi: 'pembatalan_' . $row->tipe_transaksi,
                    tanggal:       now(),
                    akunDebit:     $row->kode_akun_kredit, // dibalik dari baris asli
                    akunKredit:    $row->kode_akun_debit,  // dibalik dari baris asli
                    nominal:       (float) $row->nominal,
                    keterangan:    "Reversal otomatis: {$row->keterangan}",
                );

                $posted = $this->posting([$reversalPending->id], $userId);
                $hasilReversal[] = $posted[0] ?? null;
            }
        });

        return array_values(array_filter($hasilReversal));
    }
}

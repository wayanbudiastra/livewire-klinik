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
     */
    public function posting(array $jurnalPendingIds, int $userId): array
    {
        $diposting = [];

        DB::transaction(function () use ($jurnalPendingIds, $userId, &$diposting) {
            $rows = JurnalPending::pending()->whereIn('id', $jurnalPendingIds)->lockForUpdate()->get();

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
}

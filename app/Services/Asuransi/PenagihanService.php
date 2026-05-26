<?php

namespace App\Services\Asuransi;

use App\Models\{PenagihanAsuransi, PiutangAsuransi, PembayaranAsuransi, AuditKasir};
use Illuminate\Support\Facades\DB;

class PenagihanService
{
    public function buatPenagihan(int $asuransiId, array $piutangIds, int $userId): PenagihanAsuransi
    {
        return DB::transaction(function () use ($asuransiId, $piutangIds, $userId) {
            $piutangList = PiutangAsuransi::whereIn('id', $piutangIds)
                ->where('asuransi_id', $asuransiId)
                ->where('status', 'tertagih')
                ->get();

            if ($piutangList->isEmpty()) {
                throw new \RuntimeException('Tidak ada piutang valid untuk ditagih.');
            }

            $total = $piutangList->sum('sisa_piutang');

            $penagihan = PenagihanAsuransi::create([
                'nomor_penagihan'   => $this->generateNomorPenagihan(),
                'asuransi_id'       => $asuransiId,
                'dibuat_oleh'       => $userId,
                'tanggal_penagihan' => now(),
                'total_tagihan'     => $total,
                'status'            => 'diajukan',
            ]);

            foreach ($piutangList as $piutang) {
                $penagihan->items()->create([
                    'piutang_asuransi_id' => $piutang->id,
                    'jumlah_diajukan'     => $piutang->sisa_piutang,
                ]);

                $piutang->update([
                    'status'       => 'diajukan',
                    'penagihan_id' => $penagihan->id,
                ]);
            }

            return $penagihan->load('items.piutang');
        });
    }

    public function catatPembayaran(
        PenagihanAsuransi $penagihan,
        float   $jumlahBayar,
        string  $metode,
        string  $tanggalBayar,
        ?string $nomorReferensi,
        int     $userId
    ): PembayaranAsuransi {
        return DB::transaction(function () use (
            $penagihan, $jumlahBayar, $metode, $tanggalBayar, $nomorReferensi, $userId
        ) {
            $pembayaran = PembayaranAsuransi::create([
                'nomor_pembayaran' => $this->generateNomorPembayaran(),
                'penagihan_id'     => $penagihan->id,
                'asuransi_id'      => $penagihan->asuransi_id,
                'dicatat_oleh'     => $userId,
                'jumlah_bayar'     => $jumlahBayar,
                'tanggal_bayar'    => $tanggalBayar,
                'metode'           => $metode,
                'nomor_referensi'  => $nomorReferensi,
            ]);

            $sisaBayar = $jumlahBayar;
            foreach ($penagihan->items as $item) {
                if ($sisaBayar <= 0) break;

                $piutang = $item->piutang;
                $alokasi = min($sisaBayar, $piutang->sisa_piutang);

                $piutang->increment('jumlah_dibayar', $alokasi);
                $piutang->decrement('sisa_piutang', $alokasi);
                $piutang->update([
                    'status' => $piutang->fresh()->sisa_piutang <= 0 ? 'lunas' : 'dibayar_sebagian',
                ]);

                $sisaBayar -= $alokasi;

                if ($piutang->fresh()->status === 'lunas') {
                    $this->akuiPendapatan($piutang->fresh(), $userId);
                }
            }

            $penagihan->increment('total_dibayar', $jumlahBayar);
            $penagihan->update([
                'status' => $penagihan->fresh()->total_dibayar >= $penagihan->total_tagihan
                    ? 'lunas' : 'dibayar_sebagian',
            ]);

            return $pembayaran;
        });
    }

    private function akuiPendapatan(PiutangAsuransi $piutang, int $userId): void
    {
        AuditKasir::create([
            'user_id'        => $userId,
            'aksi'           => 'pakai_deposit',
            'referensi_tipe' => 'piutang_asuransi',
            'referensi_id'   => $piutang->id,
            'detail'         => json_encode([
                'nomor_piutang' => $piutang->nomor_piutang,
                'jumlah'        => $piutang->jumlah_piutang,
                'keterangan'    => 'Piutang asuransi lunas — diakui pendapatan',
            ]),
        ]);
    }

    private function generateNomorPenagihan(): string
    {
        $prefix = 'TAG-' . now()->format('Y-m-');
        $last   = PenagihanAsuransi::where('nomor_penagihan', 'like', $prefix . '%')
                    ->orderByDesc('nomor_penagihan')->value('nomor_penagihan');
        $seq    = $last ? (int) substr($last, -4) + 1 : 1;
        return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }

    private function generateNomorPembayaran(): string
    {
        $prefix = 'BYR-' . now()->format('Y-m-');
        $last   = PembayaranAsuransi::where('nomor_pembayaran', 'like', $prefix . '%')
                    ->orderByDesc('nomor_pembayaran')->value('nomor_pembayaran');
        $seq    = $last ? (int) substr($last, -4) + 1 : 1;
        return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }
}

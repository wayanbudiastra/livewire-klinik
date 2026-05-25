<?php

namespace App\Services\Kasir;

use App\Models\{Pasien, DepositPasien, TransaksiDeposit, SesiKas};
use Illuminate\Support\Facades\DB;

class DepositService
{
    public function topup(
        Pasien   $pasien,
        float    $jumlah,
        int      $userId,
        ?SesiKas $sesiKas = null,
        ?string  $keterangan = null
    ): TransaksiDeposit {
        if ($jumlah <= 0) {
            throw new \InvalidArgumentException('Jumlah top-up harus lebih dari 0.');
        }

        return DB::transaction(function () use ($pasien, $jumlah, $userId, $sesiKas, $keterangan) {
            $deposit = DepositPasien::firstOrCreate(
                ['pasien_id' => $pasien->id],
                ['saldo' => 0, 'total_topup' => 0, 'total_terpakai' => 0]
            );

            $saldoSebelum = (float) $deposit->saldo;
            $saldoSesudah = $saldoSebelum + $jumlah;

            $deposit->increment('saldo', $jumlah);
            $deposit->increment('total_topup', $jumlah);

            $trx = TransaksiDeposit::create([
                'pasien_id'       => $pasien->id,
                'sesi_kas_id'     => $sesiKas?->id,
                'user_id'         => $userId,
                'nomor_transaksi' => $this->generateNomorTransaksi(),
                'tipe'            => 'topup',
                'jumlah'          => $jumlah,
                'saldo_sebelum'   => $saldoSebelum,
                'saldo_sesudah'   => $saldoSesudah,
                'referensi_tipe'  => 'topup_manual',
                'keterangan'      => $keterangan,
            ]);

            AuditKasirService::log('topup_deposit', $userId, 'transaksi_deposit', $trx->id, [
                'pasien_id'     => $pasien->id,
                'nama_pasien'   => $pasien->nama,
                'nomor_rm'      => $pasien->nomor_rm,
                'jumlah'        => $jumlah,
                'saldo_sesudah' => $saldoSesudah,
            ]);

            return $trx;
        });
    }

    public function pakai(
        Pasien $pasien,
        float  $jumlah,
        int    $billingId,
        int    $userId
    ): TransaksiDeposit {
        return DB::transaction(function () use ($pasien, $jumlah, $billingId, $userId) {
            $deposit = DepositPasien::where('pasien_id', $pasien->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((float) $deposit->saldo < $jumlah) {
                throw new \RuntimeException(
                    'Saldo deposit tidak cukup. Saldo: Rp ' . number_format($deposit->saldo, 0, ',', '.')
                );
            }

            $saldoSebelum = (float) $deposit->saldo;
            $saldoSesudah = $saldoSebelum - $jumlah;

            $deposit->decrement('saldo', $jumlah);
            $deposit->increment('total_terpakai', $jumlah);

            return TransaksiDeposit::create([
                'pasien_id'       => $pasien->id,
                'user_id'         => $userId,
                'nomor_transaksi' => $this->generateNomorTransaksi(),
                'tipe'            => 'pemakaian',
                'jumlah'          => $jumlah,
                'saldo_sebelum'   => $saldoSebelum,
                'saldo_sesudah'   => $saldoSesudah,
                'referensi_tipe'  => 'billing',
                'referensi_id'    => $billingId,
                'keterangan'      => "Pembayaran invoice billing #{$billingId}",
            ]);
        });
    }

    public function refund(
        Pasien $pasien,
        float  $jumlah,
        int    $billingId,
        int    $userId
    ): TransaksiDeposit {
        return DB::transaction(function () use ($pasien, $jumlah, $billingId, $userId) {
            $deposit = DepositPasien::where('pasien_id', $pasien->id)
                ->lockForUpdate()
                ->firstOrFail();

            $saldoSebelum = (float) $deposit->saldo;
            $saldoSesudah = $saldoSebelum + $jumlah;

            $deposit->increment('saldo', $jumlah);
            $deposit->decrement('total_terpakai', $jumlah);

            return TransaksiDeposit::create([
                'pasien_id'       => $pasien->id,
                'user_id'         => $userId,
                'nomor_transaksi' => $this->generateNomorTransaksi(),
                'tipe'            => 'refund',
                'jumlah'          => $jumlah,
                'saldo_sebelum'   => $saldoSebelum,
                'saldo_sesudah'   => $saldoSesudah,
                'referensi_tipe'  => 'billing',
                'referensi_id'    => $billingId,
                'keterangan'      => "Refund pembatalan invoice billing #{$billingId}",
            ]);
        });
    }

    public function refundManual(
        Pasien   $pasien,
        float    $jumlah,
        int      $userId,
        ?SesiKas $sesiKas = null,
        ?string  $keterangan = null
    ): TransaksiDeposit {
        if ($jumlah <= 0) {
            throw new \InvalidArgumentException('Jumlah refund harus lebih dari 0.');
        }

        return DB::transaction(function () use ($pasien, $jumlah, $userId, $sesiKas, $keterangan) {
            $deposit = DepositPasien::where('pasien_id', $pasien->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((float) $deposit->saldo < $jumlah) {
                throw new \RuntimeException(
                    'Jumlah refund melebihi saldo deposit. Saldo saat ini: Rp ' .
                    number_format($deposit->saldo, 0, ',', '.')
                );
            }

            $saldoSebelum = (float) $deposit->saldo;
            $saldoSesudah = $saldoSebelum - $jumlah;

            $deposit->decrement('saldo', $jumlah);

            $trx = TransaksiDeposit::create([
                'pasien_id'       => $pasien->id,
                'sesi_kas_id'     => $sesiKas?->id,
                'user_id'         => $userId,
                'nomor_transaksi' => $this->generateNomorTransaksi(),
                'tipe'            => 'refund',
                'jumlah'          => $jumlah,
                'saldo_sebelum'   => $saldoSebelum,
                'saldo_sesudah'   => $saldoSesudah,
                'referensi_tipe'  => 'refund_manual',
                'keterangan'      => $keterangan ?: 'Refund manual deposit',
            ]);

            AuditKasirService::log('refund_deposit_manual', $userId, 'transaksi_deposit', $trx->id, [
                'pasien_id'     => $pasien->id,
                'nama_pasien'   => $pasien->nama,
                'nomor_rm'      => $pasien->nomor_rm,
                'jumlah'        => $jumlah,
                'saldo_sesudah' => $saldoSesudah,
                'alasan'        => $keterangan,
            ]);

            return $trx;
        });
    }

    private function generateNomorTransaksi(): string
    {
        $prefix = 'DEP-' . now()->format('Y-m-');
        $last   = TransaksiDeposit::where('nomor_transaksi', 'like', $prefix . '%')
            ->orderByDesc('nomor_transaksi')
            ->value('nomor_transaksi');
        $seq    = $last ? (int) substr($last, -4) + 1 : 1;

        return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }
}

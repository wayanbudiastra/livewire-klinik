<?php

namespace App\Services\Kasir;

use App\Models\{Invoice, PembayaranSplit, Pasien, SesiKas};
use App\Services\Akuntansi\{BillingJurnalService, SharingFeeService};
use App\Services\Concerns\VerifiesSuperAdminPassword;
use Illuminate\Support\Facades\DB;

class BillingService
{
    use VerifiesSuperAdminPassword;

    public function __construct(
        private DepositService    $depositService,
        private AuditKasirService $auditService,
    ) {}

    public function prosesSplitPayment(
        Invoice $billing,
        array   $splitItems,
        int     $userId,
        SesiKas $sesiKas
    ): Invoice {
        $totalSplit = collect($splitItems)->sum('jumlah');

        return DB::transaction(function () use ($billing, $splitItems, $userId, $sesiKas, $totalSplit) {
            // Kunci ulang & cek ulang status + sisa tagihan DI DALAM
            // transaksi -- sebelumnya dicek sekali di luar transaksi tanpa
            // lock (persis pola bug yang sudah diperbaiki di
            // TagihanPasien::prosesPembayaran(), tapi jalur split-payment
            // ini terlewat), jadi klik dobel/2 request bersamaan bisa
            // sama2 lolos dan mencatat pembayaran penuh dua kali utk
            // invoice yang sama (dobel jurnal & sharing fee).
            $billingLocked = Invoice::where('id', $billing->id)->lockForUpdate()->firstOrFail();

            if (in_array($billingLocked->status, ['lunas', 'dibatalkan'], true)) {
                throw new \RuntimeException(
                    $billingLocked->status === 'lunas'
                        ? 'Invoice ini sudah lunas.'
                        : 'Invoice ini sudah dibatalkan.'
                );
            }

            $sisaTagihan = (float) $billingLocked->sisa;
            if (abs($totalSplit - $sisaTagihan) > 0.01) {
                throw new \InvalidArgumentException(
                    'Total pembayaran (Rp ' . number_format($totalSplit, 0, ',', '.') . ') ' .
                    'tidak sesuai sisa tagihan (Rp ' . number_format($sisaTagihan, 0, ',', '.') . ').'
                );
            }

            $totalDeposit = 0;

            foreach ($splitItems as $item) {
                if ($item['metode'] === 'deposit') {
                    $pasien = Pasien::find($billingLocked->kunjungan->pasien_id);
                    $this->depositService->pakai($pasien, $item['jumlah'], $billingLocked->id, $userId);
                    $totalDeposit += $item['jumlah'];
                }

                PembayaranSplit::create([
                    'billing_id'    => $billingLocked->id,
                    'sesi_kas_id'   => $sesiKas->id,
                    'user_id'       => $userId,
                    'metode'        => $item['metode'],
                    'jumlah'        => $item['jumlah'],
                    'referensi'     => $item['referensi'] ?? null,
                    'nama_asuransi' => $item['nama_asuransi'] ?? null,
                    'nomor_polis'   => $item['nomor_polis'] ?? null,
                    'jumlah_cover'  => $item['jumlah_cover'] ?? null,
                    'jumlah_pasien' => $item['jumlah_pasien'] ?? null,
                ]);
            }

            $totalBayarBaru = (float) $billingLocked->total_bayar + $totalSplit;
            $billingLocked->update([
                'total_bayar'            => $totalBayarBaru,
                'total_deposit_dipakai'  => (float) $billingLocked->total_deposit_dipakai + $totalDeposit,
                'sisa'                   => 0,
                'status'                 => 'lunas',
                'sesi_kas_id'            => $sesiKas->id,
            ]);

            AuditKasirService::log('proses_split_payment', $userId, 'billing', $billingLocked->id, [
                'nomor_invoice' => $billingLocked->nomor_invoice,
                'total'         => $billingLocked->total_tagihan,
                'split_count'   => count($splitItems),
                'methods'       => collect($splitItems)->pluck('metode')->unique()->values(),
            ]);

            $billingFresh = $billingLocked->fresh(['items', 'kunjungan.dokter']);
            app(BillingJurnalService::class)->catatPelunasan($billingFresh, $splitItems);
            app(SharingFeeService::class)->catatSharingFee($billingFresh);

            return $billingLocked->fresh(['pembayaranSplit']);
        });
    }

    public function batalkanBilling(
        Invoice $billing,
        string  $passwordSuperAdmin,
        string  $alasan,
        int     $requestUserId
    ): Invoice {
        // Tidak dibatasi ke tanggal hari ini -- sesi kas yang dibuka kembali
        // (lewat fitur "Buka Kas Kembali") tetap valid meski tanggalnya bukan hari ini.
        $sesiKas = SesiKas::where('status', 'buka')->latest('tanggal')->first();

        if (!$sesiKas) {
            throw new \RuntimeException(
                'Kas sudah ditutup. Buka kembali kas terlebih dahulu di tab "Sesi Kas" ' .
                '(menu Billing & Kasir) menggunakan password SuperAdmin, lalu coba batalkan lagi.'
            );
        }

        if ($billing->status === 'dibatalkan') {
            throw new \RuntimeException('Invoice ini sudah dibatalkan.');
        }

        $superAdmin = $this->verifySuperAdminPassword($passwordSuperAdmin);

        return DB::transaction(function () use ($billing, $alasan, $requestUserId, $superAdmin, $sesiKas) {
            // Kunci ulang & cek ulang status DI DALAM transaksi -- sebelumnya
            // status 'dibatalkan' cuma dicek sekali di luar transaksi tanpa
            // lock, jadi 2 klik "Batalkan" bersamaan (2 staf berbeda, atau
            // retry jaringan) bisa sama2 lolos dan memicu refund deposit
            // DOBEL (saldo pasien dikreditkan 2x) + jurnal pembatalan dobel.
            $billingLocked = Invoice::where('id', $billing->id)->lockForUpdate()->firstOrFail();

            if ($billingLocked->status === 'dibatalkan') {
                throw new \RuntimeException('Invoice ini sudah dibatalkan (kemungkinan oleh proses lain yang berjalan bersamaan).');
            }

            if ((float) $billingLocked->total_deposit_dipakai > 0) {
                $pasien = Pasien::find($billingLocked->kunjungan->pasien_id);
                $this->depositService->refund(
                    $pasien,
                    (float) $billingLocked->total_deposit_dipakai,
                    $billingLocked->id,
                    $requestUserId
                );
            }

            $sesiKas->increment('total_pembatalan', $billingLocked->total_bayar);

            $billingLocked->update([
                'status'               => 'dibatalkan',
                'cancel_reason'        => $alasan,
                'cancelled_by'         => $requestUserId,
                'cancel_verified_by'   => $superAdmin->id,
                'dibatalkan_pada'      => now(),
            ]);

            AuditKasirService::log('batalkan_tagihan', $requestUserId, 'billing', $billingLocked->id, [
                'nomor_invoice'   => $billingLocked->nomor_invoice,
                'total_tagihan'   => $billingLocked->total_tagihan,
                'alasan'          => $alasan,
                'verifikasi_oleh' => $superAdmin->nama,
                'sesi_kas_id'     => $sesiKas->id,
            ], $superAdmin->id);

            $billingFresh = $billingLocked->fresh(['items', 'pembayaranSplit', 'kunjungan.dokter']);
            app(BillingJurnalService::class)->catatPembatalan($billingFresh, $requestUserId);
            app(SharingFeeService::class)->catatPembatalanSharingFee($billingFresh, $requestUserId);

            return $billingLocked->fresh();
        });
    }

}

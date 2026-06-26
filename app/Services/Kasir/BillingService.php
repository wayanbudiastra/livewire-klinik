<?php

namespace App\Services\Kasir;

use App\Models\{Invoice, PembayaranSplit, Pasien, SesiKas};
use App\Services\Akuntansi\{BillingJurnalService, SharingFeeService};
use Illuminate\Support\Facades\{DB, Hash};

class BillingService
{
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
        if ($billing->status === 'lunas') {
            throw new \RuntimeException('Invoice ini sudah lunas.');
        }
        if ($billing->status === 'dibatalkan') {
            throw new \RuntimeException('Invoice ini sudah dibatalkan.');
        }

        $totalSplit  = collect($splitItems)->sum('jumlah');
        $sisaTagihan = (float) $billing->sisa;

        if (abs($totalSplit - $sisaTagihan) > 0.01) {
            throw new \InvalidArgumentException(
                'Total pembayaran (Rp ' . number_format($totalSplit, 0, ',', '.') . ') ' .
                'tidak sesuai sisa tagihan (Rp ' . number_format($sisaTagihan, 0, ',', '.') . ').'
            );
        }

        return DB::transaction(function () use ($billing, $splitItems, $userId, $sesiKas, $totalSplit) {
            $totalDeposit = 0;

            foreach ($splitItems as $item) {
                if ($item['metode'] === 'deposit') {
                    $pasien = Pasien::find($billing->kunjungan->pasien_id);
                    $this->depositService->pakai($pasien, $item['jumlah'], $billing->id, $userId);
                    $totalDeposit += $item['jumlah'];
                }

                PembayaranSplit::create([
                    'billing_id'    => $billing->id,
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

            $totalBayarBaru = (float) $billing->total_bayar + $totalSplit;
            $billing->update([
                'total_bayar'            => $totalBayarBaru,
                'total_deposit_dipakai'  => (float) $billing->total_deposit_dipakai + $totalDeposit,
                'sisa'                   => 0,
                'status'                 => 'lunas',
                'sesi_kas_id'            => $sesiKas->id,
            ]);

            AuditKasirService::log('proses_split_payment', $userId, 'billing', $billing->id, [
                'nomor_invoice' => $billing->nomor_invoice,
                'total'         => $billing->total_tagihan,
                'split_count'   => count($splitItems),
                'methods'       => collect($splitItems)->pluck('metode')->unique()->values(),
            ]);

            $billingFresh = $billing->fresh(['items', 'kunjungan.dokter']);
            app(BillingJurnalService::class)->catatPelunasan($billingFresh, $splitItems);
            app(SharingFeeService::class)->catatSharingFee($billingFresh);

            return $billing->fresh(['pembayaranSplit']);
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
            if ((float) $billing->total_deposit_dipakai > 0) {
                $pasien = Pasien::find($billing->kunjungan->pasien_id);
                $this->depositService->refund(
                    $pasien,
                    (float) $billing->total_deposit_dipakai,
                    $billing->id,
                    $requestUserId
                );
            }

            $sesiKas->increment('total_pembatalan', $billing->total_bayar);

            $billing->update([
                'status'               => 'dibatalkan',
                'cancel_reason'        => $alasan,
                'cancelled_by'         => $requestUserId,
                'cancel_verified_by'   => $superAdmin->id,
                'dibatalkan_pada'      => now(),
            ]);

            AuditKasirService::log('batalkan_tagihan', $requestUserId, 'billing', $billing->id, [
                'nomor_invoice'   => $billing->nomor_invoice,
                'total_tagihan'   => $billing->total_tagihan,
                'alasan'          => $alasan,
                'verifikasi_oleh' => $superAdmin->nama,
                'sesi_kas_id'     => $sesiKas->id,
            ], $superAdmin->id);

            $billingFresh = $billing->fresh(['items', 'pembayaranSplit', 'kunjungan.dokter']);
            app(BillingJurnalService::class)->catatPembatalan($billingFresh, $requestUserId);
            app(SharingFeeService::class)->catatPembatalanSharingFee($billingFresh, $requestUserId);

            return $billing->fresh();
        });
    }

    public function verifySuperAdminPassword(string $password): \App\Models\User
    {
        $superAdmin = \App\Models\User::role('super_admin')
            ->where('is_active', true)
            ->first();

        if (!$superAdmin || !Hash::check($password, $superAdmin->password)) {
            throw new \RuntimeException('Password SuperAdmin tidak valid.');
        }

        return $superAdmin;
    }
}

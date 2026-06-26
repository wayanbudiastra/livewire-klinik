<?php

namespace App\Services\Asuransi;

use App\Models\{Invoice, Asuransi, PiutangAsuransi, PembayaranSplit, SesiKas};
use App\Services\Akuntansi\{AsuransiJurnalService, BillingJurnalService, SharingFeeService};
use Illuminate\Support\Facades\DB;

class PembayaranAsuransiService
{
    public function __construct(
        private CoverCalculatorService $calculator
    ) {}

    /**
     * Proses billing dengan penjamin asuransi:
     *  - Porsi cover → piutang asuransi
     *  - Porsi pasien → wajib bayar tunai/non-tunai sekarang
     */
    public function prosesPembayaranAsuransi(
        Invoice  $billing,
        Asuransi $asuransi,
        array    $pembayaranPasien,
        int      $userId,
        SesiKas  $sesiKas
    ): Invoice {
        $hitung = $this->calculator->hitungCover($billing, $asuransi);

        $totalCover  = $hitung['total_cover'];
        $totalPasien = $hitung['total_pasien'];

        $totalBayarPasien = collect($pembayaranPasien)->sum('jumlah');
        if (abs($totalBayarPasien - $totalPasien) > 0.01) {
            throw new \InvalidArgumentException(
                "Pembayaran pasien (Rp " . number_format($totalBayarPasien, 0, ',', '.') . ") " .
                "harus sama dengan tanggungan pasien (Rp " . number_format($totalPasien, 0, ',', '.') . ")."
            );
        }

        $metodeValid = ['tunai', 'debit', 'kredit', 'transfer', 'qris'];
        foreach ($pembayaranPasien as $bayar) {
            if (!in_array($bayar['metode'], $metodeValid)) {
                throw new \InvalidArgumentException(
                    "Item tidak ter-cover wajib dibayar tunai/non-tunai. Metode '{$bayar['metode']}' tidak diizinkan."
                );
            }
        }

        return DB::transaction(function () use (
            $billing, $asuransi, $pembayaranPasien, $userId, $sesiKas, $totalCover, $totalPasien
        ) {
            foreach ($pembayaranPasien as $bayar) {
                PembayaranSplit::create([
                    'billing_id'  => $billing->id,
                    'sesi_kas_id' => $sesiKas->id,
                    'user_id'     => $userId,
                    'metode'      => $bayar['metode'],
                    'jumlah'      => $bayar['jumlah'],
                    'referensi'   => $bayar['referensi'] ?? null,
                ]);
            }

            $piutang = null;
            if ($totalCover > 0) {
                $piutang = PiutangAsuransi::create([
                    'nomor_piutang'       => $this->generateNomorPiutang(),
                    'billing_id'          => $billing->id,
                    'asuransi_id'         => $asuransi->id,
                    'pasien_id'           => $billing->kunjungan->pasien_id,
                    'jumlah_piutang'      => $totalCover,
                    'jumlah_dibayar'      => 0,
                    'sisa_piutang'        => $totalCover,
                    'tanggal_piutang'     => now(),
                    'tanggal_jatuh_tempo' => now()->addDays($asuransi->term_pembayaran_hari),
                    'status'              => 'tertagih',
                ]);
            }

            $billing->update([
                'total_cover_asuransi'    => $totalCover,
                'total_tanggungan_pasien' => $totalPasien,
                'asuransi_id'             => $asuransi->id,
                'total_bayar'             => $totalPasien,
                'sisa'                    => 0,
                'status'                  => 'lunas',
                'sesi_kas_id'             => $sesiKas->id,
            ]);

            $billingFresh = $billing->fresh(['items', 'kunjungan.dokter']);

            if (!empty($pembayaranPasien)) {
                app(BillingJurnalService::class)->catatPelunasan($billingFresh, $pembayaranPasien);
            }
            if ($piutang) {
                app(AsuransiJurnalService::class)->catatPiutangTerbentuk($piutang);
            }
            app(SharingFeeService::class)->catatSharingFee($billingFresh);

            return $billing->fresh();
        });
    }

    private function generateNomorPiutang(): string
    {
        $prefix = 'PIT-' . now()->format('Y-m-');
        $last   = PiutangAsuransi::where('nomor_piutang', 'like', $prefix . '%')
                    ->orderByDesc('nomor_piutang')->value('nomor_piutang');
        $seq    = $last ? (int) substr($last, -4) + 1 : 1;
        return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }
}

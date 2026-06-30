<?php

namespace App\Services\Demo;

use App\Models\Barang;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DemoDataGeneratorService
{
    public function __construct(
        private DemoPoGrnGenerator       $poGrnGenerator,
        private DemoRitelGenerator       $ritelGenerator,
        private DemoJurnalGenerator      $jurnalGenerator,
        private DemoKunjunganGenerator   $kunjunganGenerator,
        private DemoBillingGenerator     $billingGenerator,
        private DemoBillingJurnalGenerator $billingJurnalGenerator,
        private DemoDataResetService     $resetService,
    ) {}

    public const MAX_HARI              = 10;
    public const MIN_TARGET_PO        = 1_000_000;
    public const MAX_TARGET_PO        = 100_000_000;
    public const MIN_TARGET_RITEL     = 500_000;
    public const MAX_TARGET_RITEL     = 50_000_000;
    public const MIN_KUNJUNGAN        = 1;
    public const MAX_KUNJUNGAN        = 50;

    /**
     * Periksa apakah rentang tanggal sudah memiliki data (untuk peringatan UI sebelum generate).
     */
    public function cekKonflik(Carbon $dari, Carbon $sampai): array
    {
        return $this->resetService->cekKonflik($dari, $sampai);
    }

    /**
     * Generate data demo (PO+GRN, Ritel, Kunjungan, Jurnal) untuk rentang tanggal & target tertentu.
     *
     * @param  array  $options [
     *     'generate_po_grn'       => bool,
     *     'generate_ritel'        => bool,
     *     'generate_jurnal'       => bool,
     *     'generate_kunjungan'    => bool,
     *     'generate_billing_jurnal' => bool,
     *     'target_po_harian'      => int,
     *     'target_ritel_harian'   => int,
     *     'kunjungan_per_hari'    => int,  (1–50)
     *     'include_resep_stok'    => bool,
     *     'mix_bayar'             => ['tunai'=>int,'transfer'=>int,'bpjs'=>int],
     * ]
     * @param  callable|null  $onProgress  dipanggil setiap satu hari data selesai dibuat
     * @return array Ringkasan hasil generate
     *
     * @throws \InvalidArgumentException jika validasi BR-01..BR-07 gagal
     */
    public function generate(Carbon $dari, Carbon $sampai, array $options, int $userId, ?callable $onProgress = null): array
    {
        $this->validasi($dari, $sampai, $options);

        $generatePoGrn        = $options['generate_po_grn']        ?? false;
        $generateRitel        = $options['generate_ritel']         ?? false;
        $generateJurnal       = $options['generate_jurnal']        ?? true;
        $generateKunjungan    = $options['generate_kunjungan']     ?? false;
        $generateBillingJurnal = $options['generate_billing_jurnal'] ?? true;
        $kunjunganPerHari     = (int) ($options['kunjungan_per_hari'] ?? 10);
        $includeResepStok     = (bool) ($options['include_resep_stok'] ?? true);
        $mixBayar             = $options['mix_bayar'] ?? ['tunai' => 60, 'transfer' => 30, 'bpjs' => 10];

        $result = DB::transaction(function () use (
            $dari, $sampai, $options, $userId, $onProgress,
            $generatePoGrn, $generateRitel, $generateJurnal,
            $generateKunjungan, $generateBillingJurnal,
            $kunjunganPerHari, $includeResepStok, $mixBayar
        ) {
            // Stok tracking bersama, mulai dari stok aktual saat ini di DB
            $stokBerjalan = Barang::where('is_active', 1)->pluck('stok', 'id')
                ->map(fn ($s) => (int) $s)->toArray();

            $poGrnResult     = ['po_ids' => [], 'gr_ids' => [], 'total_nilai' => 0.0, 'per_hari' => []];
            $kunjunganResult = ['kunjungan_ids' => [], 'per_hari' => []];
            $ritelResult     = ['ritel_ids' => [], 'total_harga' => 0.0, 'per_hari' => []];

            // Urutan: PO+GRN dulu (stok masuk) → Kunjungan (resep ambil stok) → Ritel (sisa stok)
            if ($generatePoGrn) {
                $poGrnResult = $this->poGrnGenerator->generate(
                    $dari, $sampai,
                    (int) $options['target_po_harian'],
                    $userId,
                    $stokBerjalan,
                    $onProgress
                );
            }

            if ($generateKunjungan) {
                $kunjunganResult = $this->kunjunganGenerator->generate(
                    $dari, $sampai,
                    $kunjunganPerHari,
                    $userId,
                    $stokBerjalan,
                    $includeResepStok,
                    $onProgress
                );
            }

            if ($generateRitel) {
                $ritelResult = $this->ritelGenerator->generate(
                    $dari, $sampai,
                    (int) $options['target_ritel_harian'],
                    $userId,
                    $stokBerjalan,
                    $onProgress
                );
            }

            // Sync stok akhir ke DB
            foreach ($stokBerjalan as $id => $stok) {
                Barang::where('id', $id)->update(['stok' => $stok]);
            }

            // Jurnal PO+GRN dan Ritel
            $jurnalGrnCount   = 0;
            $jurnalRitelCount = 0;

            if ($generateJurnal) {
                if (!empty($poGrnResult['gr_ids'])) {
                    $jurnalGrnCount = $this->jurnalGenerator->generateForGrn($poGrnResult['gr_ids'], $userId);
                }
                if (!empty($ritelResult['ritel_ids'])) {
                    $jurnalRitelCount = $this->jurnalGenerator->generateForRitel($ritelResult['ritel_ids'], $userId);
                }
            }

            // Billing + Pembayaran + Jurnal Billing
            $billingResult = ['billing_ids' => [], 'total_pendapatan' => 0.0, 'total_tindakan' => 0.0, 'total_obat' => 0.0];
            $jurnalBillingCount = 0;

            if ($generateKunjungan && !empty($kunjunganResult['kunjungan_ids'])) {
                // Jika stok resep tidak ditrack, tagihkan semua item resep
                $billingResult = $this->billingGenerator->generate(
                    $kunjunganResult['kunjungan_ids'],
                    $mixBayar,
                    $userId,
                    !$includeResepStok
                );

                if ($generateBillingJurnal && !empty($billingResult['billing_ids'])) {
                    $jurnalBillingCount = $this->billingJurnalGenerator->generate(
                        $billingResult['billing_ids'],
                        $userId
                    );
                }
            }

            return [
                'po_grn' => [
                    'jumlah_po'   => count($poGrnResult['po_ids']),
                    'jumlah_gr'   => count($poGrnResult['gr_ids']),
                    'total_nilai' => $poGrnResult['total_nilai'],
                    'per_hari'    => $poGrnResult['per_hari'],
                ],
                'ritel' => [
                    'jumlah_transaksi' => count($ritelResult['ritel_ids']),
                    'total_harga'      => $ritelResult['total_harga'],
                    'per_hari'         => $ritelResult['per_hari'],
                ],
                'kunjungan' => [
                    'jumlah_kunjungan'  => count($kunjunganResult['kunjungan_ids']),
                    'jumlah_billing'    => count($billingResult['billing_ids']),
                    'total_pendapatan'  => $billingResult['total_pendapatan'],
                    'total_tindakan'    => $billingResult['total_tindakan'],
                    'total_obat'        => $billingResult['total_obat'],
                    'per_hari'          => $kunjunganResult['per_hari'],
                ],
                'jurnal' => [
                    'jumlah_grn'     => $jurnalGrnCount,
                    'jumlah_ritel'   => $jurnalRitelCount,
                    'jumlah_billing' => $jurnalBillingCount,
                    'total'          => $jurnalGrnCount + $jurnalRitelCount + $jurnalBillingCount,
                ],
            ];
        });

        return $result;
    }

    /**
     * Hapus data demo dalam rentang tanggal.
     */
    public function hapus(Carbon $dari, Carbon $sampai): array
    {
        return $this->resetService->hapus($dari, $sampai);
    }

    private function validasi(Carbon $dari, Carbon $sampai, array $options): void
    {
        if ($sampai->lt($dari)) {
            throw new \InvalidArgumentException('Tanggal selesai harus setelah atau sama dengan tanggal mulai.');
        }

        $selisihHari = $dari->diffInDays($sampai) + 1;
        if ($selisihHari > self::MAX_HARI) {
            throw new \InvalidArgumentException('Rentang tanggal maksimal ' . self::MAX_HARI . ' hari.');
        }

        if ($sampai->startOfDay()->gt(now()->startOfDay())) {
            throw new \InvalidArgumentException('Tanggal tidak boleh di masa depan.');
        }

        $generatePoGrn     = $options['generate_po_grn']     ?? false;
        $generateRitel     = $options['generate_ritel']      ?? false;
        $generateKunjungan = $options['generate_kunjungan']  ?? false;

        if (!$generatePoGrn && !$generateRitel && !$generateKunjungan) {
            throw new \InvalidArgumentException('Minimal satu jenis data harus dipilih.');
        }

        if ($generatePoGrn) {
            $target = (int) ($options['target_po_harian'] ?? 0);
            if ($target < self::MIN_TARGET_PO || $target > self::MAX_TARGET_PO) {
                throw new \InvalidArgumentException(
                    'Target PO+GRN per hari harus antara Rp ' . number_format(self::MIN_TARGET_PO, 0, ',', '.')
                    . ' dan Rp ' . number_format(self::MAX_TARGET_PO, 0, ',', '.') . '.'
                );
            }
        }

        if ($generateRitel) {
            $target = (int) ($options['target_ritel_harian'] ?? 0);
            if ($target < self::MIN_TARGET_RITEL || $target > self::MAX_TARGET_RITEL) {
                throw new \InvalidArgumentException(
                    'Target Penjualan Ritel per hari harus antara Rp ' . number_format(self::MIN_TARGET_RITEL, 0, ',', '.')
                    . ' dan Rp ' . number_format(self::MAX_TARGET_RITEL, 0, ',', '.') . '.'
                );
            }
        }

        if ($generateKunjungan) {
            $jumlah = (int) ($options['kunjungan_per_hari'] ?? 0);
            if ($jumlah < self::MIN_KUNJUNGAN || $jumlah > self::MAX_KUNJUNGAN) {
                throw new \InvalidArgumentException(
                    'Jumlah kunjungan per hari harus antara ' . self::MIN_KUNJUNGAN . ' dan ' . self::MAX_KUNJUNGAN . '.'
                );
            }
        }
    }
}

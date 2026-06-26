<?php

namespace App\Services\Akuntansi;

use App\Models\Invoice;

class BillingJurnalService
{
    const AKUN_KAS     = '1-1100';
    const AKUN_BANK    = '1-1200';
    const AKUN_DEPOSIT = '2-1300';
    const AKUN_PIUTANG = '1-1400';

    const AKUN_PENDAPATAN = [
        'tindakan'  => '4-1100',
        'penunjang' => '4-1200',
        'obat'      => '4-1300',
        'racikan'   => '4-1300',
        'alkes'     => '4-1300',
        'manual'    => '4-1100',
    ];

    public function __construct(private JurnalService $jurnal) {}

    /**
     * Catat jurnal pendapatan saat billing lunas (dipanggil dari BillingService::prosesSplitPayment).
     * Setiap baris pembayaran (per metode) dialokasikan proporsional ke setiap kategori
     * pendapatan (per jenis item) berdasarkan kontribusi subtotal-nya di invoice.
     */
    public function catatPelunasan(Invoice $billing, array $splitItems): void
    {
        $proporsiKategori = $this->hitungProporsiKategori($billing);
        if ($proporsiKategori->isEmpty()) return;

        foreach ($splitItems as $split) {
            $jumlah = (float) ($split['jumlah'] ?? 0);
            if ($jumlah <= 0) continue;

            $akunDebit = $this->akunPembayaran($split['metode']);

            foreach ($proporsiKategori as $jenis => $proporsi) {
                $alokasi = round($jumlah * $proporsi, 2);
                if ($alokasi <= 0) continue;

                $this->jurnal->catat(
                    sumberTipe:    'billing',
                    sumberId:      $billing->id,
                    tipeTransaksi: 'pendapatan_billing',
                    tanggal:       $billing->updated_at ?? now(),
                    akunDebit:     $akunDebit,
                    akunKredit:    self::AKUN_PENDAPATAN[$jenis] ?? '4-1100',
                    nominal:       $alokasi,
                    keterangan:    "Pelunasan {$billing->nomor_invoice} ({$split['metode']} / {$jenis})",
                    metadata:      ['metode' => $split['metode'], 'jenis_item' => $jenis],
                );
            }
        }
    }

    /**
     * Reversal jurnal pendapatan saat billing yang sudah lunas dibatalkan
     * (dipanggil dari BillingService::batalkanBilling).
     *
     * TIDAK menghitung ulang alokasi -- membaca persis baris jurnal "pendapatan_billing"
     * yang sudah tercatat untuk billing ini, lalu membalik debit/kreditnya. Kalau baris
     * aslinya sudah diposting ke Jurnal Umum, reversal-nya juga langsung diposting
     * (lihat JurnalService::reversal) supaya Buku Besar/Neraca Saldo/Laba Rugi tidak
     * pernah menampilkan pendapatan dari invoice yang sudah dibatalkan.
     */
    public function catatPembatalan(Invoice $billing, int $userId): void
    {
        $this->jurnal->reversal('billing', $billing->id, ['pendapatan_billing'], $userId);
    }

    /** Proporsi subtotal per jenis item terhadap total invoice (untuk alokasi pendapatan). */
    private function hitungProporsiKategori(Invoice $billing): \Illuminate\Support\Collection
    {
        $totalInvoice = (float) $billing->items->sum('subtotal');
        if ($totalInvoice <= 0) return collect();

        return $billing->items
            ->groupBy('jenis')
            ->map(fn ($items) => (float) $items->sum('subtotal') / $totalInvoice);
    }

    private function akunPembayaran(string $metode): string
    {
        return match ($metode) {
            'tunai'                                => self::AKUN_KAS,
            'debit', 'kredit', 'transfer', 'qris'   => self::AKUN_BANK,
            'deposit'                               => self::AKUN_DEPOSIT,
            'bpjs', 'asuransi'                       => self::AKUN_PIUTANG,
            default                                  => self::AKUN_KAS,
        };
    }
}

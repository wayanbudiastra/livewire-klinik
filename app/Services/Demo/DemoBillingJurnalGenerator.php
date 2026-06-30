<?php

namespace App\Services\Demo;

use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DemoBillingJurnalGenerator
{
    private const AKUN_KAS      = '1-1100';
    private const AKUN_BANK     = '1-1200';
    private const AKUN_PIUTANG  = '1-1400';

    private const AKUN_PENDAPATAN = [
        'tindakan'  => '4-1100',
        'penunjang' => '4-1200',
        'obat'      => '4-1300',
        'racikan'   => '4-1300',
        'alkes'     => '4-1300',
        'manual'    => '4-1100',
    ];

    /**
     * Generate jurnal_umum untuk daftar billing yang sudah lunas.
     * Satu billing → satu baris jurnal per kombinasi (metode_bayar × jenis_item).
     *
     * DR Kas/Bank/Piutang | KR Pendapatan (4-1100/4-1300)
     *
     * @param  int[]  $billingIds
     * @param  int    $userId
     * @return int    Jumlah baris jurnal yang dibuat
     */
    public function generate(array $billingIds, int $userId): int
    {
        if (empty($billingIds)) return 0;

        $seqMap = $this->loadSeqMap($billingIds);
        $now    = now();
        $rows   = [];

        Invoice::whereIn('id', $billingIds)
            ->where('status', 'lunas')
            ->with(['items', 'pembayaran'])
            ->orderBy('created_at')
            ->each(function (Invoice $billing) use (&$rows, &$seqMap, $userId, $now) {
                $totalTagihan = (float) $billing->total_tagihan;
                if ($totalTagihan <= 0) return;

                // Proporsi per jenis item
                $proporsi = $billing->items
                    ->groupBy('jenis')
                    ->mapWithKeys(fn ($items, $jenis) => [
                        $jenis => (float) $items->sum('subtotal') / $totalTagihan,
                    ]);

                if ($proporsi->isEmpty()) return;

                $tgl    = Carbon::parse($billing->created_at);
                $bulan  = $tgl->format('Ym');
                $posted = $tgl->toDateString();

                foreach ($billing->pembayaran as $pembayaran) {
                    $jumlah   = (float) $pembayaran->jumlah;
                    $akunDebit = $this->akunPembayaran($pembayaran->metode);

                    foreach ($proporsi as $jenis => $pct) {
                        $alokasi = round($jumlah * $pct, 2);
                        if ($alokasi <= 0) continue;

                        $akunKredit = self::AKUN_PENDAPATAN[$jenis] ?? '4-1100';

                        $rows[] = [
                            'nomor_jurnal'     => $this->nextSeq($bulan, $seqMap),
                            'tanggal'          => $posted,
                            'kode_akun_debit'  => $akunDebit,
                            'kode_akun_kredit' => $akunKredit,
                            'nominal'          => $alokasi,
                            'keterangan'       => "Pelunasan {$billing->nomor_invoice} ({$pembayaran->metode} / {$jenis})",
                            'sumber_tipe'      => 'billing',
                            'sumber_id'        => $billing->id,
                            'diposting_oleh'   => $userId,
                            'diposting_pada'   => $tgl->toDateTimeString(),
                            'created_at'       => $now,
                            'updated_at'       => $now,
                        ];
                    }
                }
            });

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('jurnal_umum')->insert($chunk);
        }

        return count($rows);
    }

    // ── Helpers ───────────────────────────────────────────────────────

    private function loadSeqMap(array $billingIds): array
    {
        $tanggals = Invoice::whereIn('id', $billingIds)
            ->pluck('created_at')
            ->map(fn ($t) => Carbon::parse($t)->format('Ym'))
            ->unique();

        $seqMap = [];
        foreach ($tanggals as $bulan) {
            $prefix = 'JU-' . $bulan . '-';
            $last   = DB::table('jurnal_umum')
                ->where('nomor_jurnal', 'like', $prefix . '%')
                ->orderByDesc('nomor_jurnal')
                ->value('nomor_jurnal');
            $seqMap[$bulan] = $last ? (int) substr($last, -4) : 0;
        }

        return $seqMap;
    }

    private function nextSeq(string $bulan, array &$seqMap): string
    {
        $seqMap[$bulan] = ($seqMap[$bulan] ?? 0) + 1;
        return 'JU-' . $bulan . '-' . str_pad($seqMap[$bulan], 4, '0', STR_PAD_LEFT);
    }

    private function akunPembayaran(string $metode): string
    {
        return match ($metode) {
            'tunai'                                   => self::AKUN_KAS,
            'debit', 'kredit', 'transfer', 'qris'    => self::AKUN_BANK,
            'deposit'                                  => '2-1300',
            'bpjs', 'asuransi'                         => self::AKUN_PIUTANG,
            default                                    => self::AKUN_KAS,
        };
    }
}

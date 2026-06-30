<?php

namespace App\Services\Demo;

use App\Models\Barang;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Kunjungan;
use App\Models\Pembayaran;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DemoBillingGenerator
{
    /**
     * Generate billing + invoice_item + pembayaran untuk kunjungan yang sudah selesai.
     *
     * @param  array  $kunjunganIds    ID kunjungan yang akan dibuatkan billing
     * @param  array  $mixBayar        Distribusi metode pembayaran ['tunai'=>50,'transfer'=>30,'bpjs'=>20]
     * @param  int    $userId
     * @param  bool   $billAllResep    Jika true, tagihkan semua item resep (tidak cek mutasi_stok)
     * @return array  ['billing_ids'=>[], 'total_pendapatan'=>0.0, 'total_tindakan'=>0.0, 'total_obat'=>0.0]
     */
    public function generate(
        array $kunjunganIds,
        array $mixBayar,
        int $userId,
        bool $billAllResep = false
    ): array {
        if (empty($kunjunganIds)) return ['billing_ids' => [], 'total_pendapatan' => 0.0, 'total_tindakan' => 0.0, 'total_obat' => 0.0];

        $barangMap        = Barang::where('is_active', 1)->get()->keyBy('id');
        $seqPerTanggal    = $this->initSeqInvoice($kunjunganIds);
        $metodePembayaran = $this->buildDistribusiMetode($mixBayar);

        $billingIds      = [];
        $totalPendapatan = 0.0;
        $totalTindakan   = 0.0;
        $totalObat       = 0.0;

        // Map resep_id → [barang_id, ...] yang terdispensi (ada mutasi_stok keluar_resep)
        // Jika billAllResep=true, semua item resep dianggap sudah terdispensi
        $dispensedMap = $billAllResep ? null : $this->buildDispensedMap($kunjunganIds);

        Kunjungan::whereIn('id', $kunjunganIds)
            ->with([
                'tindakan.masterTindakan',
                'resep.itemResep',
            ])
            ->orderBy('tanggal')
            ->each(function (Kunjungan $kunjungan) use (
                &$billingIds, &$totalPendapatan, &$totalTindakan, &$totalObat,
                $barangMap, &$seqPerTanggal, $metodePembayaran, $userId, $dispensedMap
            ) {
                $tglStr  = Carbon::parse($kunjungan->tanggal)->toDateString();
                $tglYmd  = Carbon::parse($kunjungan->tanggal)->format('Ymd');

                // Build invoice items
                $invoiceItems = [];

                // 1. Tindakan
                foreach ($kunjungan->tindakan as $tindakan) {
                    if (!$tindakan->masterTindakan) continue;
                    $tarif    = (float) $tindakan->masterTindakan->tarif;
                    $subtotal = $tarif * $tindakan->jumlah;
                    $invoiceItems[] = [
                        'jenis'        => 'tindakan',
                        'ref_id'       => $tindakan->id,
                        'nama_item'    => $tindakan->masterTindakan->nama,
                        'qty'          => $tindakan->jumlah,
                        'satuan'       => 'tindakan',
                        'harga_satuan' => $tarif,
                        'diskon_item'  => 0,
                        'subtotal'     => $subtotal,
                    ];
                    $totalTindakan += $subtotal;
                }

                // 2. Obat dari resep
                foreach ($kunjungan->resep as $resep) {
                    $dispensedIds = $dispensedMap === null
                        ? null
                        : ($dispensedMap[$resep->id] ?? []);

                    foreach ($resep->itemResep as $item) {
                        if (!isset($barangMap[$item->barang_id])) continue;
                        // Skip jika stok check aktif dan item ini tidak terdispensi
                        if ($dispensedIds !== null && !in_array($item->barang_id, $dispensedIds)) continue;

                        $barang   = $barangMap[$item->barang_id];
                        $hargaJual = (float) $barang->harga_jual;
                        $subtotal  = $hargaJual * $item->jumlah;

                        $invoiceItems[] = [
                            'jenis'        => 'obat',
                            'ref_id'       => $item->id,
                            'nama_item'    => $barang->nama,
                            'qty'          => $item->jumlah,
                            'satuan'       => $barang->satuan ?? 'pcs',
                            'harga_satuan' => $hargaJual,
                            'diskon_item'  => 0,
                            'subtotal'     => $subtotal,
                        ];
                        $totalObat += $subtotal;
                    }
                }

                // Skip kunjungan tanpa item (seharusnya tidak terjadi)
                if (empty($invoiceItems)) return;

                $totalTagihan = array_sum(array_column($invoiceItems, 'subtotal'));

                // Nomor invoice: INV-YYYYMMDD-XXXXX
                if (!isset($seqPerTanggal[$tglStr])) {
                    $seqPerTanggal[$tglStr] = 0;
                }
                $seqPerTanggal[$tglStr]++;
                $nomorInvoice = 'INV-' . $tglYmd . '-' . str_pad($seqPerTanggal[$tglStr], 5, '0', STR_PAD_LEFT);

                // Create billing
                $billing = Invoice::create([
                    'kunjungan_id'          => $kunjungan->id,
                    'shift_id'              => null,
                    'sesi_kas_id'           => null,
                    'nomor_invoice'         => $nomorInvoice,
                    'total_tagihan'         => $totalTagihan,
                    'total_cover_asuransi'  => 0,
                    'total_tanggungan_pasien' => $totalTagihan,
                    'asuransi_id'           => null,
                    'total_bayar'           => $totalTagihan,
                    'total_deposit_dipakai' => 0,
                    'sisa'                  => 0,
                    'diskon_global'         => 0,
                    'status'                => 'lunas',
                    'sudah_cetak'           => false,
                    'jumlah_cetak'          => 0,
                    'cancelled_by'          => null,
                    'cancel_verified_by'    => null,
                    'cancel_reason'         => null,
                    'dibatalkan_pada'       => null,
                ]);

                // Create invoice items
                $billingItems = [];
                $now = now();
                foreach ($invoiceItems as $item) {
                    $billingItems[] = array_merge($item, [
                        'billing_id' => $billing->id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
                DB::table('invoice_item')->insert($billingItems);

                // Pembayaran — pilih metode berdasarkan distribusi
                $metode   = $this->pickMetode($metodePembayaran);
                $bayarAt  = Carbon::parse($kunjungan->tanggal)->setTime(
                    mt_rand(9, 15), mt_rand(0, 59)
                );

                Pembayaran::create([
                    'billing_id'      => $billing->id,
                    'shift_id'        => null,
                    'metode'          => $metode,
                    'jumlah'          => $totalTagihan,
                    'bank_nama'       => null,
                    'nomor_referensi' => null,
                    'tipe_kartu'      => null,
                    'nama_asuransi'   => null,
                    'catatan'         => 'Demo generator',
                    'created_at'      => $bayarAt,
                ]);

                $billingIds[]     = $billing->id;
                $totalPendapatan += $totalTagihan;
            });

        return [
            'billing_ids'      => $billingIds,
            'total_pendapatan' => $totalPendapatan,
            'total_tindakan'   => $totalTindakan,
            'total_obat'       => $totalObat,
        ];
    }

    // ── Helpers ───────────────────────────────────────────────────────

    /**
     * Inisialisasi sequence nomor invoice per tanggal dari DB
     * agar tidak tabrakan dengan invoice yang sudah ada.
     */
    private function initSeqInvoice(array $kunjunganIds): array
    {
        $tanggals = Kunjungan::whereIn('id', $kunjunganIds)
            ->pluck('tanggal')
            ->map(fn ($t) => Carbon::parse($t)->toDateString())
            ->unique();

        $seqMap = [];
        foreach ($tanggals as $tgl) {
            $tglYmd = Carbon::parse($tgl)->format('Ymd');
            $prefix = 'INV-' . $tglYmd . '-';
            $last   = DB::table('billing')
                ->where('nomor_invoice', 'like', $prefix . '%')
                ->orderByDesc('nomor_invoice')
                ->value('nomor_invoice');
            $seqMap[$tgl] = $last ? (int) substr($last, -5) : 0;
        }

        return $seqMap;
    }

    /**
     * Bangun map: resep_id → [barang_id, ...] yang benar-benar terdispensi
     * (memiliki mutasi_stok keluar_resep).
     */
    private function buildDispensedMap(array $kunjunganIds): array
    {
        $resepIds = DB::table('resep')
            ->whereIn('kunjungan_id', $kunjunganIds)
            ->pluck('id');

        if ($resepIds->isEmpty()) return [];

        $mutasiRows = DB::table('mutasi_stok')
            ->where('tipe', 'keluar_resep')
            ->where('referensi_tipe', 'resep')
            ->whereIn('referensi_id', $resepIds)
            ->select('referensi_id as resep_id', 'barang_id')
            ->get();

        $map = [];
        foreach ($mutasiRows as $row) {
            $map[$row->resep_id][] = $row->barang_id;
        }

        return $map;
    }

    /**
     * Ubah array persentase menjadi array kumulatif untuk random pick.
     * Input:  ['tunai'=>50,'transfer'=>30,'bpjs'=>20]
     * Output: ['tunai'=>50,'transfer'=>80,'bpjs'=>100]
     */
    private function buildDistribusiMetode(array $mixBayar): array
    {
        $total     = array_sum($mixBayar);
        $cumul     = 0;
        $distribusi = [];

        if ($total <= 0) {
            return ['tunai' => 100];
        }

        foreach ($mixBayar as $metode => $persen) {
            $cumul += ($persen / $total) * 100;
            $distribusi[$metode] = $cumul;
        }

        return $distribusi;
    }

    private function pickMetode(array $distribusi): string
    {
        $r = mt_rand(1, 100);
        foreach ($distribusi as $metode => $batas) {
            if ($r <= $batas) return $metode;
        }
        return array_key_last($distribusi);
    }
}

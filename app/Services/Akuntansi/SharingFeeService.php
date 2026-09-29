<?php

namespace App\Services\Akuntansi;

use App\Models\Invoice;
use App\Models\KonfigurasiSharingFeePerawat;
use App\Models\SharingFee;
use App\Models\Tindakan;

/**
 * Scope v1 (sederhana): hanya menghitung sharing fee kategori 'tindakan',
 * berdasarkan dokter penanggung jawab kunjungan (Kunjungan::dokter_id).
 * Kategori lab/radiologi/peralatan BELUM dihitung — perlu kategorisasi
 * ItemPenunjang per jenis yang belum tersedia untuk dipetakan dari InvoiceItem.
 *
 * Sharing fee PERAWAT beda arsitektur dari dokter di atas: bukan per-individu
 * (1 % global utk semua perawat, lihat KonfigurasiSharingFeePerawat), dan
 * bukan berdasar "penanggung jawab kunjungan" (Kunjungan tidak py perawat_id)
 * melainkan per-tindakan: siapa pun perawat yang tercatat sbg
 * Tindakan::pelaksana_id pada tindakan yang dibilling, dia dapat X% dari
 * subtotal tindakan yang DIA kerjakan sendiri. Kalau 1 billing dikerjakan
 * beberapa perawat berbeda, semua diakumulasi jadi SATU baris jurnal
 * (spt sharing fee dokter -- 1 baris per billing), rincian per-perawat
 * disimpan di kolom metadata utk audit.
 */
class SharingFeeService
{
    const AKUN_BIAYA_JASA_DOKTER  = '5-1200';
    const AKUN_HUTANG_JASA_DOKTER = '2-1200';

    const AKUN_BIAYA_JASA_PERAWAT  = '5-1300';
    const AKUN_HUTANG_JASA_PERAWAT = '2-1400';

    /**
     * Default persentase sharing fee tindakan utk dokter BARU (dipakai di
     * UserForm saat provisioning row Dokter pertama kali). Bukan berarti
     * SEMUA dokter selalu 10% -- dokter existing tetap bisa dikustomisasi
     * per-individu lewat SharingFeeForm, angka ini cuma nilai awal.
     */
    const DEFAULT_PERSENTASE_TINDAKAN_DOKTER = 10;

    public function __construct(private JurnalService $jurnal) {}

    /** Dipanggil saat billing lunas (BillingService / PembayaranAsuransiService). */
    public function catatSharingFee(Invoice $billing): void
    {
        $nilaiFee = $this->hitungNilaiFee($billing);
        if ($nilaiFee === null) return;

        [$nominal, $dokterId, $persentase] = $nilaiFee;

        $this->jurnal->catat(
            sumberTipe:    'billing',
            sumberId:      $billing->id,
            tipeTransaksi: 'sharing_fee_dokter',
            tanggal:       $billing->updated_at ?? now(),
            akunDebit:     self::AKUN_BIAYA_JASA_DOKTER,
            akunKredit:    self::AKUN_HUTANG_JASA_DOKTER,
            nominal:       $nominal,
            keterangan:    "Sharing fee dokter - {$billing->nomor_invoice}",
            metadata:      ['dokter_id' => $dokterId, 'persentase' => $persentase],
        );
    }

    /**
     * Reversal saat billing yang sudah lunas dibatalkan (BillingService::batalkanBilling).
     * Membaca persis baris jurnal "sharing_fee_dokter" yang sudah tercatat, lalu membalik
     * debit/kreditnya -- kalau sudah diposting, reversal-nya juga langsung diposting.
     */
    public function catatPembatalanSharingFee(Invoice $billing, int $userId): void
    {
        $this->jurnal->reversal('billing', $billing->id, ['sharing_fee_dokter'], $userId);
    }

    /** Dipanggil saat billing lunas, sama posisinya dgn catatSharingFee() (dokter) di atas. */
    public function catatSharingFeePerawat(Invoice $billing): void
    {
        $nilaiFee = $this->hitungNilaiFeePerawat($billing);
        if ($nilaiFee === null) return;

        [$nominal, $rincian, $persentase] = $nilaiFee;

        $this->jurnal->catat(
            sumberTipe:    'billing',
            sumberId:      $billing->id,
            tipeTransaksi: 'sharing_fee_perawat',
            tanggal:       $billing->updated_at ?? now(),
            akunDebit:     self::AKUN_BIAYA_JASA_PERAWAT,
            akunKredit:    self::AKUN_HUTANG_JASA_PERAWAT,
            nominal:       $nominal,
            keterangan:    "Sharing fee perawat - {$billing->nomor_invoice}",
            metadata:      ['persentase' => $persentase, 'rincian_perawat' => $rincian],
        );
    }

    /** Reversal, sama posisinya dgn catatPembatalanSharingFee() (dokter) di atas. */
    public function catatPembatalanSharingFeePerawat(Invoice $billing, int $userId): void
    {
        $this->jurnal->reversal('billing', $billing->id, ['sharing_fee_perawat'], $userId);
    }

    /**
     * @return array{0: float, 1: array<int, array{perawat_id:int,nama:string,subtotal:float,fee:float}>, 2: float}|null
     *         [nominal_total, rincian_per_perawat, persentase]
     */
    private function hitungNilaiFeePerawat(Invoice $billing): ?array
    {
        $persentase = KonfigurasiSharingFeePerawat::persentase('tindakan');
        if ($persentase <= 0) return null;

        $itemTindakan = $billing->items->where('jenis', 'tindakan');
        if ($itemTindakan->isEmpty()) return null;

        $tindakanIds = $itemTindakan->pluck('ref_id')->filter()->all();
        if (empty($tindakanIds)) return null;

        $tindakanList = Tindakan::with('pelaksana')
            ->whereIn('id', $tindakanIds)
            ->whereHas('pelaksana', fn ($q) => $q->role('perawat'))
            ->get()
            ->keyBy('id');

        if ($tindakanList->isEmpty()) return null;

        // Subtotal per-perawat: jumlahkan subtotal InvoiceItem dari tindakan yg
        // pelaksananya perawat tsb (1 perawat bisa jadi pelaksana >1 tindakan).
        $subtotalPerPerawat = [];
        foreach ($itemTindakan as $item) {
            $tindakan = $tindakanList->get($item->ref_id);
            if (!$tindakan) continue; // pelaksana bukan perawat (dokter/tidak ada)

            $perawatId = $tindakan->pelaksana_id;
            $subtotalPerPerawat[$perawatId] ??= ['nama' => $tindakan->pelaksana->nama, 'subtotal' => 0.0];
            $subtotalPerPerawat[$perawatId]['subtotal'] += (float) $item->subtotal;
        }

        if (empty($subtotalPerPerawat)) return null;

        $nominalTotal = 0.0;
        $rincian = [];
        foreach ($subtotalPerPerawat as $perawatId => $data) {
            $fee = round($data['subtotal'] * ($persentase / 100), 2);
            if ($fee <= 0) continue;

            $nominalTotal += $fee;
            $rincian[] = [
                'perawat_id' => $perawatId,
                'nama'       => $data['nama'],
                'subtotal'   => $data['subtotal'],
                'fee'        => $fee,
            ];
        }

        if ($nominalTotal <= 0) return null;

        return [round($nominalTotal, 2), $rincian, $persentase];
    }

    /** @return array{0: float, 1: int, 2: float}|null [nominal, dokter_id, persentase] */
    private function hitungNilaiFee(Invoice $billing): ?array
    {
        $kunjungan = $billing->kunjungan;
        if (!$kunjungan || !$kunjungan->dokter_id) return null;

        $sharingFee = SharingFee::where('dokter_id', $kunjungan->dokter_id)
            ->where('kategori', 'tindakan')
            ->first();
        if (!$sharingFee || (float) $sharingFee->persentase <= 0) return null;

        $totalTindakan = (float) $billing->items->where('jenis', 'tindakan')->sum('subtotal');
        if ($totalTindakan <= 0) return null;

        $nominal = round($totalTindakan * ((float) $sharingFee->persentase / 100), 2);
        if ($nominal <= 0) return null;

        return [$nominal, $kunjungan->dokter_id, (float) $sharingFee->persentase];
    }
}

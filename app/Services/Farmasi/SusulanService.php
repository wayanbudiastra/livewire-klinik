<?php

namespace App\Services\Farmasi;

use App\Models\Barang;
use App\Models\Dokter;
use App\Models\Invoice;
use App\Models\ItemPenunjang;
use App\Models\Kunjungan;
use App\Models\TransaksiRitel;
use App\Models\TransaksiRitelItem;
use App\Services\Harga\TarifResolver;
use Illuminate\Support\Facades\DB;

/**
 * Order susulan: lab ATAU obat tambahan atas saran dokter setelah bill
 * kunjungan lunas. Dibuat oleh kasir; wajib punya registrasi utama
 * (kunjungan asal) dan pasien ber-ID. Lab & obat DIPISAH jadi transaksi
 * sendiri. Dibayar lewat alur ritel (ObatRitelService::prosesBayar).
 *
 * Lihat docs/PERANCANGAN_COMBINE_BILL_RITEL.md.
 */
class SusulanService
{
    /**
     * @param array{pasien_id:int, kunjungan_id:int, dokter_id:int, jenis:string, catatan?:?string,
     *              items:array<int, array{barang_id?:int, item_penunjang_id?:int, jumlah?:int}>} $data
     */
    public function buat(array $data, int $kasirId): TransaksiRitel
    {
        $jenis = $data['jenis'] ?? null;
        if (! in_array($jenis, ['obat', 'lab'], true)) {
            throw new \DomainException('Jenis susulan harus "obat" atau "lab" (tidak boleh dicampur).');
        }

        if (empty($data['pasien_id'])) {
            throw new \DomainException('Susulan hanya untuk pasien yang punya ID pasien.');
        }
        if (empty($data['kunjungan_id'])) {
            throw new \DomainException('Susulan wajib punya registrasi utama (kunjungan asal).');
        }
        if (empty($data['dokter_id']) || ! Dokter::whereKey($data['dokter_id'])->exists()) {
            throw new \DomainException('Dokter penyaran wajib dipilih.');
        }
        if (empty($data['items'])) {
            throw new \DomainException('Susulan tidak boleh tanpa item.');
        }

        $kunjungan = Kunjungan::with('pasien')->find($data['kunjungan_id']);
        if (! $kunjungan) {
            throw new \DomainException('Kunjungan asal tidak ditemukan.');
        }
        if ((int) $kunjungan->pasien_id !== (int) $data['pasien_id']) {
            throw new \DomainException('Kunjungan asal bukan milik pasien tersebut.');
        }
        if ($kunjungan->status === 'dibatalkan') {
            throw new \DomainException('Kunjungan asal sudah dibatalkan.');
        }

        $maksHari = (int) config('emr.susulan_maks_hari', 14);
        if ($kunjungan->tanggal->copy()->startOfDay()->diffInDays(today()) > $maksHari) {
            throw new \DomainException("Susulan melewati batas {$maksHari} hari sejak kunjungan asal.");
        }

        $billing = Invoice::where('kunjungan_id', $kunjungan->id)
            ->where('status', '!=', 'dibatalkan')
            ->first();
        if (! $billing || $billing->status !== 'lunas') {
            throw new \DomainException('Bill kunjungan asal harus sudah lunas. Jika belum, tambahkan item langsung ke bill tersebut.');
        }

        $isBpjs = $kunjungan->tipe_pembayaran === 'bpjs';
        $isWna  = $kunjungan->pasien?->tipe_pasien === 'WNA';

        return DB::transaction(function () use ($data, $jenis, $kunjungan, $billing, $kasirId, $isBpjs, $isWna) {
            $baris = $jenis === 'lab'
                ? $this->susunItemLab($data['items'], $isBpjs, $isWna)
                : $this->susunItemObat($data['items'], $isWna);

            $tr = TransaksiRitel::create([
                'nomor_ritel'   => TransaksiRitel::generateNomor(),
                'tipe'          => 'susulan',
                'jenis_susulan' => $jenis,
                'nama_pembeli'  => $kunjungan->pasien->nama,
                'pasien_id'     => $kunjungan->pasien_id,
                'kunjungan_id'  => $kunjungan->id,
                'billing_id'    => $billing->id,
                'dokter_id'     => $data['dokter_id'],
                // kolom apoteker_id NOT NULL = pembuat transaksi; untuk susulan dibuat kasir
                'apoteker_id'   => $kasirId,
                'status'        => 'menunggu_kasir',
                'total_harga'   => collect($baris)->sum('subtotal'),
                'catatan'       => $data['catatan'] ?? null,
            ]);

            foreach ($baris as $b) {
                TransaksiRitelItem::create($b + ['transaksi_ritel_id' => $tr->id]);
            }

            return $tr->fresh('items');
        });
    }

    private function susunItemLab(array $items, bool $isBpjs, bool $isWna): array
    {
        $baris = [];
        foreach ($items as $it) {
            $item = ItemPenunjang::find($it['item_penunjang_id'] ?? null);
            if (! $item || $item->kategori !== 'lab' || ! $item->is_active) {
                throw new \DomainException('Item lab tidak valid atau tidak aktif.');
            }
            $harga = TarifResolver::pilih(
                (float) $item->tarif,
                $item->tarif_bpjs !== null ? (float) $item->tarif_bpjs : null,
                $item->tarif_wna !== null ? (float) $item->tarif_wna : null,
                $isBpjs,
                $isWna,
            );
            $baris[] = [
                'item_penunjang_id' => $item->id,
                'jumlah'            => 1,
                'harga_satuan'      => $harga,
                'subtotal'          => $harga,
            ];
        }
        return $baris;
    }

    private function susunItemObat(array $items, bool $isWna): array
    {
        $baris = [];
        foreach ($items as $it) {
            $barang = Barang::find($it['barang_id'] ?? null);
            if (! $barang || ! $barang->is_active) {
                throw new \DomainException('Obat tidak valid atau tidak aktif.');
            }
            $jumlah = (int) ($it['jumlah'] ?? 0);
            if ($jumlah < 1) {
                throw new \DomainException("Jumlah {$barang->nama} harus minimal 1.");
            }
            $harga = TarifResolver::pilih(
                (float) $barang->harga_jual,
                null,
                $barang->harga_wna !== null ? (float) $barang->harga_wna : null,
                false,
                $isWna,
            );
            $baris[] = [
                'barang_id'    => $barang->id,
                'jumlah'       => $jumlah,
                'harga_satuan' => $harga,
                'subtotal'     => $harga * $jumlah,
            ];
        }
        return $baris;
    }
}

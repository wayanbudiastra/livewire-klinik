<?php

namespace App\Services\Inventory;

use App\Models\Barang;
use App\Models\MutasiStok;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class KartuStokService
{
    public function getKartuStok(
        int    $barangId,
        string $tanggalMulai,
        string $tanggalAkhir,
        ?string $tipeMutasi = null
    ): array {
        $barang = Barang::with(['supplierUtama:id,nama'])->findOrFail($barangId);

        $mutasiSebelumPeriode = MutasiStok::where('barang_id', $barangId)
            ->where('created_at', '<', Carbon::parse($tanggalMulai)->startOfDay())
            ->orderByDesc('created_at')
            ->first();

        $saldoAwal = $mutasiSebelumPeriode?->stok_sesudah ?? 0;
        $hprAwal   = $mutasiSebelumPeriode?->hpr_sesudah  ?? (float) $barang->harga_pokok;

        $mutasi = MutasiStok::with('user:id,nama')
            ->where('barang_id', $barangId)
            ->whereBetween('created_at', [
                Carbon::parse($tanggalMulai)->startOfDay(),
                Carbon::parse($tanggalAkhir)->endOfDay(),
            ])
            ->when($tipeMutasi, fn ($q, $t) => $q->where('tipe', $t))
            ->orderBy('created_at')
            ->get();

        $tipeMasuk  = ['masuk_pembelian', 'penyesuaian_masuk'];

        $rows = $mutasi->map(function ($m) use ($tipeMasuk) {
            $isMasuk = in_array($m->tipe, $tipeMasuk);
            return [
                'id'             => $m->id,
                'tanggal'        => $m->created_at->format('d/m/Y'),
                'waktu'          => $m->created_at->format('H:i'),
                'created_at'     => $m->created_at,
                'tipe'           => $m->tipe,
                'tipe_label'     => self::getTipeLabel($m->tipe),
                'keterangan'     => $m->keterangan,
                'referensi_tipe' => $m->referensi_tipe,
                'referensi_id'   => $m->referensi_id,
                'masuk'          => $isMasuk ? $m->jumlah : 0,
                'keluar'         => ! $isMasuk ? $m->jumlah : 0,
                'saldo'          => $m->stok_sesudah,
                'hpr'            => (float) $m->hpr_sesudah,
                'user_nama'      => $m->user?->nama ?? '-',
                'is_anomali'     => $m->stok_sesudah < 0,
            ];
        });

        $totalMasuk  = $rows->sum('masuk');
        $totalKeluar = $rows->sum('keluar');
        $saldoAkhir  = $rows->last()['saldo'] ?? $saldoAwal;

        return [
            'barang'        => $barang,
            'saldo_awal'    => $saldoAwal,
            'hpr_awal'      => $hprAwal,
            'rows'          => $rows,
            'total_masuk'   => $totalMasuk,
            'total_keluar'  => $totalKeluar,
            'saldo_akhir'   => $saldoAkhir,
            'tanggal_mulai' => $tanggalMulai,
            'tanggal_akhir' => $tanggalAkhir,
        ];
    }

    public function getRingkasanMutasi(
        string $tanggalMulai,
        string $tanggalAkhir
    ): Collection {
        return MutasiStok::selectRaw('
                barang_id,
                COUNT(*) as total_transaksi,
                SUM(CASE WHEN tipe IN ("masuk_pembelian","penyesuaian_masuk") THEN jumlah ELSE 0 END) as total_masuk,
                SUM(CASE WHEN tipe NOT IN ("masuk_pembelian","penyesuaian_masuk") THEN jumlah ELSE 0 END) as total_keluar,
                MAX(stok_sesudah) as stok_tertinggi,
                MIN(stok_sesudah) as stok_terendah
            ')
            ->with('barang:id,kode,nama,satuan,stok,stok_minimum')
            ->whereBetween('created_at', [
                Carbon::parse($tanggalMulai)->startOfDay(),
                Carbon::parse($tanggalAkhir)->endOfDay(),
            ])
            ->groupBy('barang_id')
            ->orderBy('barang_id')
            ->get();
    }

    public static function getTipeLabel(string $tipe): string
    {
        return match ($tipe) {
            'masuk_pembelian'    => 'Pembelian',
            'keluar_resep'       => 'Resep',
            'keluar_tindakan'    => 'Tindakan',
            'penyesuaian_masuk'  => 'Opname (+)',
            'penyesuaian_keluar' => 'Opname (-)',
            'retur_ke_supplier'  => 'Retur',
            'expired'            => 'Expired',
            default              => ucfirst(str_replace('_', ' ', $tipe)),
        };
    }

    public static function getTipeBadgeClass(string $tipe): string
    {
        $masuk  = ['masuk_pembelian', 'penyesuaian_masuk'];
        $danger = ['expired', 'retur_ke_supplier'];
        if (in_array($tipe, $masuk))  return 'badge-success';
        if (in_array($tipe, $danger)) return 'badge-danger';
        return 'badge-warning';
    }
}

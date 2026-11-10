<?php

namespace App\Services\Inventory;

use App\Models\AdjustmentStok;
use App\Models\Barang;
use App\Models\MutasiStok;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AdjustmentStokService
{
    public function buat(array $data, int $userId, ?UploadedFile $file = null): AdjustmentStok
    {
        return DB::transaction(function () use ($data, $userId, $file) {
            $barang = Barang::where('is_active', true)->lockForUpdate()->findOrFail($data['barang_id']);
            $jumlah = (int) $data['jumlah'];
            $stokSebelum = (int) $barang->stok;
            $isMasuk = $data['tipe'] === 'penyesuaian_masuk';

            if (!$isMasuk && $jumlah > $stokSebelum) {
                throw new \DomainException("Stok tidak cukup. Stok saat ini: {$stokSebelum} unit.");
            }

            $stokSesudah = $isMasuk ? $stokSebelum + $jumlah : $stokSebelum - $jumlah;
            $filePath = $file?->store('adjustment/' . now()->format('Y/m'), 'local');
            $adjustment = AdjustmentStok::create([
                'nomor_adjustment' => $this->generateNomor(),
                'barang_id' => $barang->id,
                'user_id' => $userId,
                'tipe' => $data['tipe'],
                'jumlah' => $jumlah,
                'catatan' => $data['catatan'],
                'bukti_referensi' => $data['bukti_referensi'] ?? null,
                'file_attachment' => $filePath,
                'status' => 'submitted',
            ]);

            $barang->update(['stok' => $stokSesudah]);
            $mutasi = MutasiStok::create([
                'barang_id' => $barang->id,
                'user_id' => $userId,
                'tipe' => $data['tipe'],
                'jumlah' => $jumlah,
                'stok_sebelum' => $stokSebelum,
                'stok_sesudah' => $stokSesudah,
                'hpr_sebelum' => $barang->harga_pokok,
                'hpr_sesudah' => $barang->harga_pokok,
                'referensi_tipe' => 'adjustment_stok',
                'referensi_id' => $adjustment->id,
                'keterangan' => "[{$adjustment->nomor_adjustment}] {$adjustment->catatan}",
            ]);

            $adjustment->update(['mutasi_stok_id' => $mutasi->id]);
            return $adjustment->fresh(['barang', 'user', 'mutasiStok']);
        });
    }

    public function getById(int $id): AdjustmentStok
    {
        return AdjustmentStok::with(['barang', 'user', 'mutasiStok'])->findOrFail($id);
    }

    /**
     * Soft-delete adjustment + revert efeknya ke stok & kartu stok.
     *
     * Revert dihitung berbasis delta (bukan dikembalikan ke stok_sebelum),
     * karena bisa saja sudah ada mutasi lain setelah adjustment ini dibuat.
     *
     * @param AdjustmentStok $adjustment Record yang mau dibatalkan
     * @param int|null $userId User ID pembatal; jika null gunakan user_id adjustment
     * @param string|null $alasan Alasan pembatalan (dicatat di keterangan mutasi pembalik)
     * @throws \DomainException jika stok menjadi negatif
     */
    public function delete(AdjustmentStok $adjustment, ?int $userId = null, ?string $alasan = null): void
    {
        $filePath = $adjustment->file_attachment;

        DB::transaction(function () use ($adjustment, $userId, $alasan) {
            $adjustment->load('mutasiStok');

            $barang = Barang::lockForUpdate()->findOrFail($adjustment->barang_id);
            $stokSebelum = (int) $barang->stok;
            $jumlah = (int) $adjustment->jumlah;
            $isMasuk = $adjustment->tipe === 'penyesuaian_masuk';

            // Kebalikan dari efek awal: adjustment masuk → dikurangi, keluar → ditambah.
            $stokSesudah = $isMasuk ? $stokSebelum - $jumlah : $stokSebelum + $jumlah;

            if ($stokSesudah < 0) {
                throw new \DomainException(
                    "Tidak bisa membatalkan adjustment ini. Stok saat ini {$stokSebelum} unit, "
                    . "pembatalan akan membuat stok menjadi {$stokSesudah} unit. "
                    . 'Buat adjustment baru sebagai koreksi.'
                );
            }

            $barang->update(['stok' => $stokSesudah]);

            // Jejak pembatalan tetap dicatat di kartu stok (mutasi lawan arah),
            // supaya riwayat stok tidak "bolong".
            $keterangan = "[BATAL {$adjustment->nomor_adjustment}] Pembatalan adjustment. Catatan asli: {$adjustment->catatan}";
            if ($alasan) {
                $keterangan .= " | Alasan batal: {$alasan}";
            }

            MutasiStok::create([
                'barang_id' => $barang->id,
                'user_id' => $userId ?? $adjustment->user_id,
                'tipe' => $isMasuk ? 'penyesuaian_keluar' : 'penyesuaian_masuk',
                'jumlah' => $jumlah,
                'stok_sebelum' => $stokSebelum,
                'stok_sesudah' => $stokSesudah,
                'hpr_sebelum' => $barang->harga_pokok,
                'hpr_sesudah' => $barang->harga_pokok,
                'referensi_tipe' => 'adjustment_stok_batal',
                'referensi_id' => $adjustment->id,
                'keterangan' => $keterangan,
            ]);

            $adjustment->update(['status' => 'cancelled']);
            $adjustment->delete();
        });

        // Hapus file di luar transaksi: kalau transaksi rollback, file tidak hilang.
        if ($filePath) {
            Storage::disk('local')->delete($filePath);
        }
    }

    private function generateNomor(): string
    {
        $prefix = 'ADJ-' . now()->format('Y-m-');
        $last = AdjustmentStok::withTrashed()->where('nomor_adjustment', 'like', $prefix . '%')
            ->lockForUpdate()->orderByDesc('nomor_adjustment')->value('nomor_adjustment');
        $sequence = $last ? ((int) substr($last, -4)) + 1 : 1;
        return $prefix . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}


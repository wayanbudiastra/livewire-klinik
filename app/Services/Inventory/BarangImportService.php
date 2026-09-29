<?php

namespace App\Services\Inventory;

use App\Models\Barang;
use App\Models\Supplier;
use App\Services\Harga\MarkupHargaService;
use Illuminate\Support\Facades\DB;

/**
 * Impor massal Master Barang (Obat/Alkes/BHP/Lainnya) dari template XLS --
 * pola sama dgn MasterdataService::importTindakan() dkk: $mode 'baru'/
 * 'update' (proses terpisah, bukan upsert gabungan), lockless krn dipanggil
 * sekali per baris di dalam 1 transaksi, validasi harga di bawah modal
 * cuma peringatan (tidak memblokir).
 */
class BarangImportService
{
    public function __construct(
        private MarkupHargaService $markupHargaService,
    ) {}

    /**
     * Urutan kolom template (lihat route inventory.barang.template):
     * 0=Kode, 1=Nama Barang, 2=Jenis, 3=Satuan, 4=Stok Awal, 5=Stok Minimum,
     * 6=HPR/Harga Modal, 7=Harga Jual, 8=Kode Supplier Utama, 9=Status Aktif (Y/N).
     *
     * Jenis obat & bahan_habis_pakai ikut markup otomatis berbasis modal --
     * kalau kolom HPR (6) diisi & angka, Harga Jual (7) di file DIABAIKAN
     * dan dihitung ulang otomatis dari modal x multiplier 'obat_bhp',
     * supaya konsisten dgn hook otomatis di BarangForm/ObatForm/GR. Alkes
     * & lainnya tetap pakai Harga Jual apa adanya dari file (manual).
     *
     * @return array{imported:int,updated:int,skipped:int,errors:array,warnings:array}
     */
    public function importBarang(array $rows, string $mode = 'baru'): array
    {
        $imported = 0;
        $updated  = 0;
        $skipped  = 0;
        $errors   = [];
        $warnings = [];

        DB::transaction(function () use ($rows, $mode, &$imported, &$updated, &$skipped, &$errors, &$warnings) {
            foreach ($rows as $i => $row) {
                $lineNo = $i + 2; // +1 header, +1 baris ke-1 mulai dari 1

                $kode  = strtoupper(trim((string) ($row[0] ?? '')));
                $nama  = trim((string) ($row[1] ?? ''));
                $jenis = strtolower(trim((string) ($row[2] ?? '')));
                $satuan = trim((string) ($row[3] ?? ''));

                if ($kode === '' || $nama === '' || $satuan === '') {
                    $errors[] = "Baris {$lineNo}: kolom Kode, Nama Barang, dan Satuan wajib diisi -- baris dilewati.";
                    $skipped++;
                    continue;
                }

                if (! in_array($jenis, ['obat', 'alkes', 'bahan_habis_pakai', 'lainnya'], true)) {
                    $errors[] = "Baris {$lineNo}: jenis \"{$row[2]}\" tidak valid (harus obat/alkes/bahan_habis_pakai/lainnya) -- baris dilewati.";
                    $skipped++;
                    continue;
                }

                $existing = Barang::where('kode', $kode)->first();

                if ($mode === 'baru' && $existing) {
                    $errors[] = "Baris {$lineNo}: kode \"{$kode}\" sudah ada -- dilewati (gunakan proses Update Data untuk mengubahnya).";
                    $skipped++;
                    continue;
                }
                if ($mode === 'update' && ! $existing) {
                    $errors[] = "Baris {$lineNo}: kode \"{$kode}\" tidak ditemukan -- dilewati (gunakan proses Upload Data Baru untuk menambahkannya).";
                    $skipped++;
                    continue;
                }

                $supplierId = null;
                $supplierKodeRaw = trim((string) ($row[8] ?? ''));
                if ($supplierKodeRaw !== '') {
                    $supplierId = Supplier::where('kode', $supplierKodeRaw)->value('id');
                    if (! $supplierId) {
                        $errors[] = "Baris {$lineNo}: kode supplier \"{$supplierKodeRaw}\" tidak ditemukan -- supplier utama dikosongkan, baris tetap diproses.";
                    }
                }

                $hargaPokok = is_numeric($row[6] ?? null) ? (float) $row[6] : 0.0;
                $hargaJualFinal = is_numeric($row[7] ?? null) ? (float) $row[7] : 0.0;
                $hargaWnaFinal  = null;

                if (in_array($jenis, ['obat', 'bahan_habis_pakai'], true) && $hargaPokok > 0) {
                    $hasil = $this->markupHargaService->hitung('obat_bhp', $hargaPokok);
                    if ($hasil['ktp'] !== null) {
                        $hargaJualFinal = $hasil['ktp'];
                        $hargaWnaFinal  = $hasil['wna'];
                    }
                }

                if ($hargaJualFinal < $hargaPokok) {
                    $warnings[] = "Baris {$lineNo}: harga jual (Rp " . number_format($hargaJualFinal, 0, ',', '.')
                        . ") di bawah harga modal (Rp " . number_format($hargaPokok, 0, ',', '.') . ") -- tetap disimpan.";
                }

                $data = [
                    'nama'          => $nama,
                    'jenis'         => $jenis,
                    'satuan'        => $satuan,
                    'stok_minimum'  => is_numeric($row[5] ?? null) ? (int) $row[5] : 10,
                    'harga_pokok'   => $hargaPokok,
                    'harga_jual'    => $hargaJualFinal,
                    'harga_wna'     => $hargaWnaFinal,
                    'supplier_utama_id' => $supplierId,
                    'is_active'     => $this->parseBoolYn($row[9] ?? 'Y'),
                ];

                if ($existing) {
                    $existing->update($data);
                    $updated++;
                } else {
                    $data['stok'] = is_numeric($row[4] ?? null) ? (int) $row[4] : 0;
                    Barang::create(array_merge(['kode' => $kode], $data));
                    $imported++;
                }
            }
        });

        activity('inventory')
            ->causedBy(auth()->user())
            ->withProperties(compact('imported', 'updated', 'skipped', 'mode'))
            ->log("Impor massal Barang ({$mode}) dari file: {$imported} baru, {$updated} diupdate, {$skipped} dilewati");

        return compact('imported', 'updated', 'skipped', 'errors', 'warnings');
    }

    private function parseBoolYn(mixed $value): bool
    {
        $v = strtolower(trim((string) $value));
        return in_array($v, ['', 'y', 'ya', 'yes', '1', 'true', 'aktif'], true);
    }
}

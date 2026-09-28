<?php

namespace App\Services;

use App\Models\ItemPenunjang;
use App\Models\MasterTindakan;
use App\Models\PeralatanMedis;
use App\Models\PenggunaanAlat;
use App\Models\PermintaanPenunjang;
use App\Models\Poli;
use App\Repositories\MasterdataRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MasterdataService
{
    private MasterdataRepository $repo;

    public function __construct(MasterdataRepository $repo)
    {
        $this->repo = $repo;
    }

    // ── Search Engine ────────────────────────────────────────

    public function searchOrderable(string $keyword, int $poliId)
    {
        if (! $poliId) {
            throw ValidationException::withMessages([
                'poli_id' => 'Poli dokter tidak ditemukan. Pastikan profil dokter sudah dikonfigurasi.',
            ]);
        }
        return $this->repo->searchOrderable($keyword, $poliId);
    }

    // ── Master Tindakan ──────────────────────────────────────

    public function createTindakan(array $data, array $poliIds): MasterTindakan
    {
        if (empty($poliIds)) {
            throw ValidationException::withMessages([
                'poli_ids' => 'Tindakan wajib dipetakan ke minimal satu Poli.',
            ]);
        }

        return DB::transaction(function () use ($data, $poliIds) {
            $tindakan = MasterTindakan::create(array_merge($data, ['kategori' => 'tindakan']));
            $tindakan->poli()->sync($poliIds);

            activity('masterdata')
                ->performedOn($tindakan)
                ->causedBy(auth()->user())
                ->withProperties(['poli_count' => count($poliIds)])
                ->log('Tindakan baru dibuat');

            return $tindakan->load('poli');
        });
    }

    public function updateTindakan(int $id, array $data, array $poliIds): MasterTindakan
    {
        if (empty($poliIds)) {
            throw ValidationException::withMessages([
                'poli_ids' => 'Tindakan wajib dipetakan ke minimal satu Poli.',
            ]);
        }

        return DB::transaction(function () use ($id, $data, $poliIds) {
            $tindakan = MasterTindakan::findOrFail($id);
            $tindakan->update($data);
            $tindakan->poli()->sync($poliIds);

            activity('masterdata')
                ->performedOn($tindakan)
                ->causedBy(auth()->user())
                ->log('Tindakan diupdate');

            return $tindakan->fresh('poli');
        });
    }

    public function toggleAktifTindakan(int $id): MasterTindakan
    {
        $item = MasterTindakan::findOrFail($id);
        $item->update(['is_active' => ! $item->is_active]);
        return $item;
    }

    /**
     * Impor massal dari template XLS -- baris berindeks posisi mengikuti
     * urutan kolom template (lihat route pengaturan.masterdata.tindakan.template):
     * 0=Kode, 1=Nama, 2=Deskripsi, 3=Tarif, 4=Tarif BPJS, 5=Tarif WNA,
     * 6=Kode Poli (pisah koma), 7=Status Aktif (Y/N).
     *
     * $mode 'baru': HANYA membuat baris baru -- kode yang sudah ada di DB
     * dilewati (tidak diubah). $mode 'update': HANYA mengubah baris yang
     * kodenya sudah ada -- kode yang belum terdaftar dilewati (tidak
     * dibuat baru). Sengaja dipisah jadi 2 proses (bukan upsert gabungan)
     * supaya upload "Data Baru" tidak bisa tanpa sadar menimpa harga/data
     * yang sudah diatur manual.
     *
     * @return array{imported:int,updated:int,skipped:int,errors:array}
     */
    public function importTindakan(array $rows, string $mode = 'baru'): array
    {
        $imported = 0;
        $updated  = 0;
        $skipped  = 0;
        $errors   = [];

        DB::transaction(function () use ($rows, $mode, &$imported, &$updated, &$skipped, &$errors) {
            foreach ($rows as $i => $row) {
                $lineNo = $i + 2; // +1 header, +1 baris ke-1 mulai dari 1

                $kode  = strtoupper(trim((string) ($row[0] ?? '')));
                $nama  = trim((string) ($row[1] ?? ''));
                $tarif = trim((string) ($row[3] ?? ''));

                if ($kode === '' || $nama === '' || $tarif === '' || ! is_numeric($tarif)) {
                    $errors[] = "Baris {$lineNo}: kolom Kode, Nama, dan Tarif (angka) wajib diisi -- baris dilewati.";
                    $skipped++;
                    continue;
                }

                $existing = MasterTindakan::where('kode', $kode)->first();

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

                $poliKodeRaw = trim((string) ($row[6] ?? ''));
                $poliKodes   = array_values(array_filter(array_map('trim', explode(',', $poliKodeRaw))));
                $poliIds     = $poliKodes ? Poli::whereIn('kode', $poliKodes)->pluck('id')->toArray() : [];

                if (empty($poliIds)) {
                    $errors[] = "Baris {$lineNo}: kode Poli \"{$poliKodeRaw}\" kosong atau tidak ditemukan -- baris dilewati.";
                    $skipped++;
                    continue;
                }

                $data = [
                    'nama'       => $nama,
                    'deskripsi'  => trim((string) ($row[2] ?? '')) ?: null,
                    'tarif'      => (float) $tarif,
                    'tarif_bpjs' => is_numeric($row[4] ?? null) ? (float) $row[4] : null,
                    'tarif_wna'  => is_numeric($row[5] ?? null) ? (float) $row[5] : null,
                    'is_active'  => $this->parseBoolYn($row[7] ?? 'Y'),
                    'kategori'   => 'tindakan',
                ];

                if ($existing) {
                    $existing->update($data);
                    $existing->poli()->sync($poliIds);
                    $updated++;
                } else {
                    $baru = MasterTindakan::create(array_merge(['kode' => $kode], $data));
                    $baru->poli()->sync($poliIds);
                    $imported++;
                }
            }
        });

        activity('masterdata')
            ->causedBy(auth()->user())
            ->withProperties(compact('imported', 'updated', 'skipped', 'mode'))
            ->log("Impor massal Tindakan ({$mode}) dari file: {$imported} baru, {$updated} diupdate, {$skipped} dilewati");

        return compact('imported', 'updated', 'skipped', 'errors');
    }

    // ── Item Penunjang ───────────────────────────────────────

    public function createPenunjang(array $data): ItemPenunjang
    {
        $item = ItemPenunjang::create($data);

        activity('masterdata')
            ->performedOn($item)
            ->causedBy(auth()->user())
            ->log("Item penunjang ({$item->kategori}) ditambahkan");

        return $item;
    }

    public function updatePenunjang(int $id, array $data): ItemPenunjang
    {
        $item = ItemPenunjang::findOrFail($id);
        $item->update($data);
        return $item;
    }

    public function toggleAktifPenunjang(int $id): ItemPenunjang
    {
        $item = ItemPenunjang::findOrFail($id);
        $item->update(['is_active' => ! $item->is_active]);
        return $item;
    }

    /**
     * Impor massal dari template XLS Lab/Radiologi -- urutan kolom:
     * 0=Kode, 1=Nama, 2=Deskripsi, 3=Tarif, 4=Tarif BPJS, 5=Tarif WNA,
     * 6=Satuan Waktu, 7=Status Aktif (Y/N).
     * $mode 'baru'/'update', sama pola dgn importTindakan().
     *
     * @return array{imported:int,updated:int,skipped:int,errors:array}
     */
    public function importPenunjang(array $rows, string $kategori, string $mode = 'baru'): array
    {
        $imported = 0;
        $updated  = 0;
        $skipped  = 0;
        $errors   = [];

        DB::transaction(function () use ($rows, $kategori, $mode, &$imported, &$updated, &$skipped, &$errors) {
            foreach ($rows as $i => $row) {
                $lineNo = $i + 2;

                $kode  = strtoupper(trim((string) ($row[0] ?? '')));
                $nama  = trim((string) ($row[1] ?? ''));
                $tarif = trim((string) ($row[3] ?? ''));

                if ($kode === '' || $nama === '' || $tarif === '' || ! is_numeric($tarif)) {
                    $errors[] = "Baris {$lineNo}: kolom Kode, Nama, dan Tarif (angka) wajib diisi -- baris dilewati.";
                    $skipped++;
                    continue;
                }

                $existing = ItemPenunjang::where('kode', $kode)->first();

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

                $data = [
                    'nama'         => $nama,
                    'deskripsi'    => trim((string) ($row[2] ?? '')) ?: null,
                    'kategori'     => $kategori,
                    'tarif'        => (float) $tarif,
                    'tarif_bpjs'   => is_numeric($row[4] ?? null) ? (float) $row[4] : null,
                    'tarif_wna'    => is_numeric($row[5] ?? null) ? (float) $row[5] : null,
                    'satuan_waktu' => trim((string) ($row[6] ?? '')) ?: null,
                    'is_active'    => $this->parseBoolYn($row[7] ?? 'Y'),
                ];

                if ($existing) {
                    $existing->update($data);
                    $updated++;
                } else {
                    ItemPenunjang::create(array_merge(['kode' => $kode], $data));
                    $imported++;
                }
            }
        });

        activity('masterdata')
            ->causedBy(auth()->user())
            ->withProperties(compact('imported', 'updated', 'skipped', 'kategori', 'mode'))
            ->log("Impor massal Penunjang ({$kategori}, {$mode}) dari file: {$imported} baru, {$updated} diupdate, {$skipped} dilewati");

        return compact('imported', 'updated', 'skipped', 'errors');
    }

    // ── Peralatan Medis ──────────────────────────────────────

    public function toggleAktifPeralatan(int $id): PeralatanMedis
    {
        $alat = PeralatanMedis::findOrFail($id);
        $alat->update(['is_active' => ! $alat->is_active]);
        return $alat;
    }

    public function createPeralatan(array $data): PeralatanMedis
    {
        return PeralatanMedis::create($data);
    }

    public function updatePeralatan(int $id, array $data): PeralatanMedis
    {
        $alat = PeralatanMedis::findOrFail($id);
        $alat->update($data);
        return $alat;
    }

    /**
     * Impor massal dari template XLS Peralatan Medis -- urutan kolom:
     * 0=Kode, 1=Nama, 2=Merk, 3=Nomor Seri, 4=Deskripsi, 5=Status Aktif (Y/N).
     * $mode 'baru'/'update', sama pola dgn importTindakan(). Status
     * operasional (tersedia/digunakan/maintenance/rusak) TIDAK ikut
     * diimpor -- alat baru selalu mulai 'tersedia', status dikelola
     * manual di tabel.
     *
     * @return array{imported:int,updated:int,skipped:int,errors:array}
     */
    public function importPeralatan(array $rows, string $mode = 'baru'): array
    {
        $imported = 0;
        $updated  = 0;
        $skipped  = 0;
        $errors   = [];

        DB::transaction(function () use ($rows, $mode, &$imported, &$updated, &$skipped, &$errors) {
            foreach ($rows as $i => $row) {
                $lineNo = $i + 2;

                $kode = strtoupper(trim((string) ($row[0] ?? '')));
                $nama = trim((string) ($row[1] ?? ''));

                if ($kode === '' || $nama === '') {
                    $errors[] = "Baris {$lineNo}: kolom Kode dan Nama wajib diisi -- baris dilewati.";
                    $skipped++;
                    continue;
                }

                $existing = PeralatanMedis::where('kode', $kode)->first();

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

                $nomorSeri = trim((string) ($row[3] ?? '')) ?: null;
                if ($nomorSeri) {
                    $dipakaiLain = PeralatanMedis::where('nomor_seri', $nomorSeri)
                        ->where('kode', '!=', $kode)
                        ->exists();
                    if ($dipakaiLain) {
                        $errors[] = "Baris {$lineNo}: nomor seri \"{$nomorSeri}\" sudah dipakai alat lain -- baris dilewati.";
                        $skipped++;
                        continue;
                    }
                }

                $data = [
                    'nama'       => $nama,
                    'merk'       => trim((string) ($row[2] ?? '')) ?: null,
                    'nomor_seri' => $nomorSeri,
                    'deskripsi'  => trim((string) ($row[4] ?? '')) ?: null,
                    'is_active'  => $this->parseBoolYn($row[5] ?? 'Y'),
                ];

                if ($existing) {
                    $existing->update($data);
                    $updated++;
                } else {
                    PeralatanMedis::create(array_merge(['kode' => $kode, 'status' => 'tersedia'], $data));
                    $imported++;
                }
            }
        });

        activity('masterdata')
            ->causedBy(auth()->user())
            ->withProperties(compact('imported', 'updated', 'skipped', 'mode'))
            ->log("Impor massal Peralatan Medis ({$mode}) dari file: {$imported} baru, {$updated} diupdate, {$skipped} dilewati");

        return compact('imported', 'updated', 'skipped', 'errors');
    }

    private function parseBoolYn(mixed $value): bool
    {
        $v = strtolower(trim((string) $value));
        return in_array($v, ['', 'y', 'ya', 'yes', '1', 'true', 'aktif'], true);
    }

    public function pakaiAlat(int $peralatanId, int $poliId, ?int $kunjunganId = null): PenggunaanAlat
    {
        $alat = PeralatanMedis::findOrFail($peralatanId);

        if ($alat->status === 'digunakan') {
            throw ValidationException::withMessages([
                'peralatan' => 'Alat sedang digunakan di poli lain.',
            ]);
        }
        if (in_array($alat->status, ['maintenance', 'rusak'])) {
            throw ValidationException::withMessages([
                'peralatan' => "Alat tidak dapat digunakan: status {$alat->status}.",
            ]);
        }

        return DB::transaction(function () use ($alat, $peralatanId, $poliId, $kunjunganId) {
            $alat->update(['status' => 'digunakan', 'poli_terakhir_id' => $poliId]);
            return PenggunaanAlat::create([
                'peralatan_id' => $peralatanId,
                'poli_id'      => $poliId,
                'kunjungan_id' => $kunjunganId,
                'dipakai_oleh' => auth()->user()->nama,
            ]);
        });
    }

    public function selesaiPakaiAlat(int $penggunaanId): PenggunaanAlat
    {
        return DB::transaction(function () use ($penggunaanId) {
            $p = PenggunaanAlat::with('peralatan')->findOrFail($penggunaanId);
            $p->update(['waktu_selesai' => now()]);
            $p->peralatan->update(['status' => 'tersedia']);
            return $p;
        });
    }

    // ── Permintaan Penunjang ─────────────────────────────────

    public function buatPermintaan(int $kunjunganId, array $items): void
    {
        $data = collect($items)->map(fn ($item) => [
            'kunjungan_id'      => $kunjunganId,
            'item_penunjang_id' => $item['id'],
            'jumlah'            => $item['jumlah'] ?? 1,
            'catatan'           => $item['catatan'] ?? null,
            'status'            => 'dipesan',
            'created_at'        => now(),
            'updated_at'        => now(),
        ])->toArray();

        PermintaanPenunjang::insert($data);
    }
}

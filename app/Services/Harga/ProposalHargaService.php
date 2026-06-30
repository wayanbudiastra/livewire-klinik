<?php

namespace App\Services\Harga;

use App\Models\Barang;
use App\Models\MasterTindakan;
use App\Models\ProposalHarga;
use App\Models\ProposalHargaItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ProposalHargaService
{
    /**
     * Buat proposal baru dan generate item secara otomatis.
     */
    public function buat(array $data, User $user): ProposalHarga
    {
        $efektif = \Carbon\Carbon::parse($data['tanggal_efektif']);
        if ($efektif->lte(now()->startOfDay())) {
            throw new \DomainException('Tanggal efektif harus lebih dari hari ini.');
        }

        return DB::transaction(function () use ($data, $user) {
            $proposal = ProposalHarga::create([
                'judul'                => $data['judul'],
                'tahun'                => $data['tahun'],
                'tanggal_efektif'      => $data['tanggal_efektif'],
                'cakupan'              => $data['cakupan'],
                'catatan'              => $data['catatan'] ?? null,
                'konfigurasi_kenaikan' => $data['konfigurasi_kenaikan'] ?? [],
                'ikut_bpjs'            => $data['ikut_bpjs'] ?? false,
                'status'               => 'draft',
                'dibuat_oleh'          => $user->id,
            ]);

            $this->generateItems($proposal);

            return $proposal;
        });
    }

    private function generateItems(ProposalHarga $proposal): void
    {
        $config    = $proposal->konfigurasi_kenaikan ?? [];
        $ikutBpjs  = $proposal->ikut_bpjs;
        $bulkItems = [];
        $now       = now();

        if (in_array($proposal->cakupan, ['semua', 'tindakan'])) {
            MasterTindakan::aktif()->get()->each(function ($t) use (&$bulkItems, $config, $now) {
                $persen     = (float) ($config[$t->kategori] ?? 0);
                $hargaLama  = (float) $t->tarif;
                $hargaKalk  = $this->hitung($hargaLama, $persen);

                $bulkItems[] = [
                    'item_type'          => 'tindakan',
                    'item_id'            => $t->id,
                    'item_nama'          => $t->nama,
                    'item_kategori'      => $t->kategori,
                    'harga_lama'         => $hargaLama,
                    'persen_kenaikan'    => $persen,
                    'harga_kalkulasi'    => $hargaKalk,
                    'harga_baru'         => $hargaKalk,
                    'harga_bpjs_lama'    => null,
                    'harga_bpjs_baru'    => null,
                    'is_dikoreksi_manual'=> false,
                    'is_skip'            => false,
                    'dikoreksi_oleh'     => null,
                    'dikoreksi_pada'     => null,
                    'created_at'         => $now,
                    'updated_at'         => $now,
                ];
            });
        }

        if (in_array($proposal->cakupan, ['semua', 'barang'])) {
            Barang::aktif()->get()->each(function ($b) use (&$bulkItems, $config, $ikutBpjs, $now) {
                $persen    = (float) ($config[$b->jenis] ?? 0);
                $hargaLama = (float) $b->harga_jual;
                $hargaKalk = $this->hitung($hargaLama, $persen);

                $bpjsLama = $ikutBpjs ? (float) $b->harga_bpjs : null;
                $bpjsBaru = $ikutBpjs ? $this->hitung($bpjsLama, $persen) : null;

                $bulkItems[] = [
                    'item_type'           => 'barang',
                    'item_id'             => $b->id,
                    'item_nama'           => $b->nama,
                    'item_kategori'       => $b->jenis,
                    'harga_lama'          => $hargaLama,
                    'persen_kenaikan'     => $persen,
                    'harga_kalkulasi'     => $hargaKalk,
                    'harga_baru'          => $hargaKalk,
                    'harga_bpjs_lama'     => $bpjsLama,
                    'harga_bpjs_baru'     => $bpjsBaru,
                    'is_dikoreksi_manual' => false,
                    'is_skip'             => false,
                    'dikoreksi_oleh'      => null,
                    'dikoreksi_pada'      => null,
                    'created_at'          => $now,
                    'updated_at'          => $now,
                ];
            });
        }

        foreach (array_chunk($bulkItems, 500) as $chunk) {
            $rows = array_map(function ($i) {
                unset($i['created_at'], $i['updated_at']);
                return $i;
            }, $chunk);
            $proposal->items()->createMany($rows);
        }
    }

    /** Pembulatan ke ratusan terdekat. */
    private function hitung(float $hargaLama, float $persen): float
    {
        if ($persen == 0) return $hargaLama;
        return round($hargaLama * (1 + $persen / 100) / 100) * 100;
    }

    /**
     * Koreksi manual harga satu item.
     */
    public function koreksiItem(ProposalHargaItem $item, float $hargaBaru, ?float $hargaBpjsBaru, User $user): void
    {
        if ($item->proposal->status !== 'draft') {
            throw new \DomainException('Koreksi hanya bisa dilakukan pada proposal berstatus draft.');
        }

        $item->update([
            'harga_baru'           => $hargaBaru,
            'harga_bpjs_baru'      => $hargaBpjsBaru,
            'is_dikoreksi_manual'  => true,
            'is_skip'              => false,
            'dikoreksi_oleh'       => $user->id,
            'dikoreksi_pada'       => now(),
        ]);
    }

    /**
     * Toggle "tidak naik" untuk satu item.
     */
    public function toggleSkip(ProposalHargaItem $item, bool $skip, User $user): void
    {
        if (!in_array($item->proposal->status, ['draft', 'menunggu_persetujuan'])) {
            throw new \DomainException('Toggle hanya bisa dilakukan pada proposal draft atau menunggu persetujuan.');
        }

        $item->update([
            'is_skip'             => $skip,
            'harga_baru'          => $skip ? $item->harga_lama : $item->harga_kalkulasi,
            'is_dikoreksi_manual' => false,
            'dikoreksi_oleh'      => $user->id,
            'dikoreksi_pada'      => now(),
        ]);
    }

    /**
     * Submit proposal ke reviewer.
     */
    public function submitReview(ProposalHarga $proposal): void
    {
        if ($proposal->status !== 'draft') {
            throw new \DomainException('Hanya proposal berstatus draft yang bisa disubmit.');
        }
        if (now()->startOfDay()->gte($proposal->tanggal_efektif)) {
            throw new \DomainException('Tanggal efektif sudah lewat. Perbarui tanggal efektif sebelum submit.');
        }

        $adaYangNaik = $proposal->items()
            ->where('is_skip', false)
            ->whereColumn('harga_baru', '!=', 'harga_lama')
            ->exists();

        if (!$adaYangNaik) {
            throw new \DomainException('Proposal tidak bisa disubmit karena semua item ditandai "tidak naik".');
        }

        $proposal->update(['status' => 'menunggu_persetujuan']);
    }

    /**
     * Setujui proposal.
     */
    public function setujui(ProposalHarga $proposal, User $user): void
    {
        if ($proposal->status !== 'menunggu_persetujuan') {
            throw new \DomainException('Hanya proposal menunggu persetujuan yang bisa disetujui.');
        }

        $proposal->update([
            'status'          => 'disetujui',
            'disetujui_oleh'  => $user->id,
            'disetujui_pada'  => now(),
        ]);
    }

    /**
     * Tolak / kembalikan ke draft.
     */
    public function tolak(ProposalHarga $proposal, string $alasan, User $user): void
    {
        if ($proposal->status !== 'menunggu_persetujuan') {
            throw new \DomainException('Hanya proposal menunggu persetujuan yang bisa ditolak.');
        }

        $proposal->update([
            'status'       => 'draft',
            'alasan_tolak' => $alasan,
            'ditolak_oleh' => $user->id,
            'ditolak_pada' => now(),
        ]);
    }

    /**
     * Batalkan proposal (dari status manapun kecuali efektif).
     */
    public function batalkan(ProposalHarga $proposal, User $user): void
    {
        if ($proposal->status === 'efektif') {
            throw new \DomainException('Proposal yang sudah efektif tidak bisa dibatalkan.');
        }
        if ($proposal->status === 'dibatalkan') {
            throw new \DomainException('Proposal sudah dibatalkan.');
        }

        $proposal->update(['status' => 'dibatalkan']);
    }

    /**
     * Terapkan harga ke master data.
     */
    public function terapkan(ProposalHarga $proposal, User $user): void
    {
        if ($proposal->status !== 'disetujui') {
            throw new \DomainException('Hanya proposal berstatus disetujui yang bisa diterapkan.');
        }
        if (now()->startOfDay()->lt($proposal->tanggal_efektif)) {
            throw new \DomainException(
                'Belum bisa diterapkan. Tanggal efektif: '
                . $proposal->tanggal_efektif->format('d/m/Y') . '.'
            );
        }

        DB::transaction(function () use ($proposal, $user) {
            $proposal->items()->where('is_skip', false)->each(function (ProposalHargaItem $item) use ($proposal) {
                if ($item->item_type === 'tindakan') {
                    $upd = ['tarif' => $item->harga_baru];
                    if ($proposal->ikut_bpjs && $item->harga_bpjs_baru !== null) {
                        $upd['tarif_bpjs'] = $item->harga_bpjs_baru;
                    }
                    MasterTindakan::where('id', $item->item_id)->update($upd);
                } else {
                    $upd = ['harga_jual' => $item->harga_baru];
                    if ($proposal->ikut_bpjs && $item->harga_bpjs_baru !== null) {
                        $upd['harga_bpjs'] = $item->harga_bpjs_baru;
                    }
                    Barang::where('id', $item->item_id)->update($upd);
                }
            });

            $proposal->update([
                'status'           => 'efektif',
                'diterapkan_oleh'  => $user->id,
                'diterapkan_pada'  => now(),
            ]);
        });
    }
}

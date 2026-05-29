<?php

namespace App\Services\Kasir;

use App\Models\{SesiKas, PembayaranSplit, TransaksiRitel, User};
use Illuminate\Support\Facades\{DB, Hash};

class SesiKasService
{
    public function bukaKas(int $userId, float $saldoAwal, ?string $catatan = null): SesiKas
    {
        $existing = SesiKas::where('user_id', $userId)
            ->where('status', 'buka')
            ->whereDate('tanggal', today())
            ->first();

        if ($existing) {
            throw new \RuntimeException('Anda sudah memiliki sesi kas yang aktif hari ini.');
        }

        $sesi = SesiKas::create([
            'user_id'     => $userId,
            'tanggal'     => today(),
            'dibuka_pada' => now(),
            'saldo_awal'  => $saldoAwal,
            'status'      => 'buka',
            'catatan'     => $catatan,
        ]);

        AuditKasirService::log('buka_kas', $userId, 'sesi_kas', $sesi->id, [
            'saldo_awal' => $saldoAwal,
        ]);

        return $sesi;
    }

    public function tutupKas(SesiKas $sesi, int $userId, float $uangFisikAkhir, ?string $catatan = null): SesiKas
    {
        if ($sesi->status === 'tutup') {
            throw new \RuntimeException('Sesi kas ini sudah ditutup.');
        }

        return DB::transaction(function () use ($sesi, $userId, $uangFisikAkhir, $catatan) {
            // Rekap dari billing pasien (PembayaranSplit)
            $rekap = PembayaranSplit::where('pembayaran_split.sesi_kas_id', $sesi->id)
                ->join('billing', 'pembayaran_split.billing_id', '=', 'billing.id')
                ->where('billing.status', 'lunas')
                ->selectRaw("
                    SUM(CASE WHEN metode = 'tunai'    THEN jumlah ELSE 0 END) as total_cash,
                    SUM(CASE WHEN metode IN ('debit','kredit','transfer','qris') THEN jumlah ELSE 0 END) as total_non_cash,
                    SUM(CASE WHEN metode = 'deposit'  THEN jumlah ELSE 0 END) as total_deposit,
                    SUM(CASE WHEN metode = 'bpjs'     THEN jumlah ELSE 0 END) as total_bpjs,
                    SUM(CASE WHEN metode = 'asuransi' THEN jumlah ELSE 0 END) as total_asuransi,
                    SUM(jumlah) as saldo_akhir
                ")
                ->first();

            // Rekap dari transaksi ritel yang terkait sesi ini
            $ritel = TransaksiRitel::where('sesi_kas_id', $sesi->id)
                ->whereIn('status', ['dibayar', 'selesai'])
                ->selectRaw("
                    SUM(CASE WHEN metode_bayar = 'tunai'               THEN total_bayar ELSE 0 END) as ritel_cash,
                    SUM(CASE WHEN metode_bayar IN ('transfer','kartu','split') THEN total_bayar ELSE 0 END) as ritel_non_cash,
                    SUM(total_bayar) as ritel_total
                ")
                ->first();

            $totalCash    = (float) ($rekap->total_cash    ?? 0) + (float) ($ritel->ritel_cash    ?? 0);
            $totalNonCash = (float) ($rekap->total_non_cash ?? 0) + (float) ($ritel->ritel_non_cash ?? 0);
            $saldoAkhir   = (float) ($rekap->saldo_akhir   ?? 0) + (float) ($ritel->ritel_total   ?? 0);
            $selisih      = $uangFisikAkhir - ((float) $sesi->saldo_awal + $totalCash);

            $sesi->update([
                'status'           => 'tutup',
                'ditutup_pada'     => now(),
                'ditutup_oleh'     => $userId,
                'saldo_akhir'      => $saldoAkhir,
                'uang_fisik_akhir' => $uangFisikAkhir,
                'selisih'          => $selisih,
                'total_cash'       => $totalCash,
                'total_non_cash'   => $totalNonCash,
                'total_deposit'    => $rekap->total_deposit  ?? 0,
                'total_bpjs'       => $rekap->total_bpjs     ?? 0,
                'total_asuransi'   => $rekap->total_asuransi ?? 0,
                'catatan'          => $catatan,
            ]);

            AuditKasirService::log('tutup_kas', $userId, 'sesi_kas', $sesi->id, [
                'saldo_akhir'      => $saldoAkhir,
                'uang_fisik_akhir' => $uangFisikAkhir,
                'selisih'          => $selisih,
                'total_cash'       => $totalCash,
                'total_non_cash'   => $totalNonCash,
                'total_ritel'      => (float) ($ritel->ritel_total ?? 0),
            ]);

            return $sesi->fresh();
        });
    }

    public function bukaKasKembali(
        SesiKas $sesi,
        string  $passwordSuperAdmin,
        string  $alasan,
        int     $requestUserId
    ): SesiKas {
        if ($sesi->status !== 'tutup') {
            throw new \RuntimeException('Kas ini belum ditutup.');
        }

        $superAdmin = $this->verifySuperAdminPassword($passwordSuperAdmin);

        $sesi->update([
            'status'                => 'buka',
            'dibuka_kembali_oleh'   => $superAdmin->id,
            'dibuka_kembali_pada'   => now(),
            'alasan_dibuka_kembali' => $alasan,
        ]);

        AuditKasirService::log('buka_kas_kembali', $requestUserId, 'sesi_kas', $sesi->id, [
            'alasan'          => $alasan,
            'superadmin_nama' => $superAdmin->nama,
            'ditutup_pada'    => $sesi->ditutup_pada,
        ], $superAdmin->id);

        return $sesi->fresh();
    }

    public function getSesiAktif(int $userId): ?SesiKas
    {
        return SesiKas::where('user_id', $userId)
            ->where('status', 'buka')
            ->whereDate('tanggal', today())
            ->first();
    }

    public function verifySuperAdminPassword(string $password): User
    {
        $superAdmin = User::role('super_admin')
            ->where('is_active', true)
            ->first();

        if (!$superAdmin || !Hash::check($password, $superAdmin->password)) {
            throw new \RuntimeException('Password SuperAdmin tidak valid.');
        }

        return $superAdmin;
    }
}

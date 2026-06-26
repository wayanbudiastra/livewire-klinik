<?php

namespace App\Services\Akuntansi;

use App\Models\Akuntansi\JurnalPending;
use App\Models\Akuntansi\PeriodeAkuntansi;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

class PeriodeAkuntansiService
{
    /** Ambil periode (tahun, bulan), lazy-create kalau belum ada (default status: terbuka). */
    public function getAtauBuat(int $tahun, int $bulan): PeriodeAkuntansi
    {
        return PeriodeAkuntansi::firstOrCreate(
            ['tahun' => $tahun, 'bulan' => $bulan],
            ['status' => 'terbuka']
        );
    }

    /** Cek apakah periode yang memuat tanggal ini masih terbuka untuk posting. */
    public function isTerbuka(mixed $tanggal): bool
    {
        $tgl    = Carbon::parse($tanggal);
        $periode = $this->getAtauBuat($tgl->year, $tgl->month);

        return $periode->status === 'terbuka';
    }

    /** Jumlah baris jurnal_pending berstatus pending yang masih tersisa di bulan ini. */
    public function sisaPending(int $tahun, int $bulan): int
    {
        return JurnalPending::pending()
            ->whereYear('tanggal_transaksi', $tahun)
            ->whereMonth('tanggal_transaksi', $bulan)
            ->count();
    }

    /**
     * Tutup periode -- hanya boleh kalau tidak ada jurnal pending tersisa di bulan itu,
     * DAN bukan bulan yang sedang berjalan (lihat catatan di bawah).
     */
    public function tutup(int $tahun, int $bulan, int $userId): PeriodeAkuntansi
    {
        $periode = $this->getAtauBuat($tahun, $bulan);

        if ($periode->status === 'ditutup') {
            throw new \DomainException('Periode ini sudah ditutup.');
        }

        $sekarang = now();
        if ($tahun === $sekarang->year && $bulan === $sekarang->month) {
            throw new \DomainException(
                'Periode bulan ini (sedang berjalan) tidak bisa ditutup. ' .
                'Tutup periode dilakukan setelah bulan tersebut berakhir, paling lambat tanggal 5 ' .
                'bulan berikutnya -- entri pembatalan/reversal jurnal selalu dicatat bertanggal hari ini, ' .
                'jadi menutup bulan berjalan akan memblokir SEMUA pembatalan jurnal sistem (bukan hanya ' .
                'di bulan ini), apa pun bulan transaksi aslinya.'
            );
        }

        $sisa = $this->sisaPending($tahun, $bulan);
        if ($sisa > 0) {
            throw new \DomainException(
                "Masih ada {$sisa} baris jurnal pending bertanggal di periode ini. " .
                'Posting atau abaikan semuanya dulu sebelum menutup periode.'
            );
        }

        $periode->update([
            'status'       => 'ditutup',
            'ditutup_oleh' => $userId,
            'ditutup_pada' => now(),
        ]);

        activity('akuntansi')
            ->performedOn($periode)
            ->causedBy(User::find($userId))
            ->log("Periode {$periode->label} ditutup.");

        return $periode->fresh();
    }

    /** Buka kembali periode yang sudah ditutup -- butuh password SuperAdmin + alasan. */
    public function bukaKembali(
        int    $tahun,
        int    $bulan,
        string $passwordSuperAdmin,
        string $alasan,
        int    $requestUserId
    ): PeriodeAkuntansi {
        $periode = $this->getAtauBuat($tahun, $bulan);

        if ($periode->status !== 'ditutup') {
            throw new \DomainException('Periode ini belum ditutup.');
        }

        $superAdmin = $this->verifySuperAdminPassword($passwordSuperAdmin);

        $periode->update([
            'status'                => 'terbuka',
            'dibuka_kembali_oleh'   => $superAdmin->id,
            'dibuka_kembali_pada'   => now(),
            'alasan_dibuka_kembali' => $alasan,
        ]);

        activity('akuntansi')
            ->performedOn($periode)
            ->causedBy(User::find($requestUserId))
            ->withProperties(['alasan' => $alasan, 'superadmin_nama' => $superAdmin->nama])
            ->log("Periode {$periode->label} dibuka kembali.");

        return $periode->fresh();
    }

    public function verifySuperAdminPassword(string $password): User
    {
        $superAdmin = User::role('super_admin')->where('is_active', true)->first();

        if (! $superAdmin || ! Hash::check($password, $superAdmin->password)) {
            throw new \RuntimeException('Password SuperAdmin tidak valid.');
        }

        return $superAdmin;
    }
}

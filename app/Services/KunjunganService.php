<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\DokterPoli;
use App\Models\JadwalPraktek;
use App\Models\Kunjungan;
use App\Models\Pasien;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class KunjunganService
{
    // ── Nomor Antrean ─────────────────────────────────────────
    // A-001 = Appointment (prioritas), W-001 = Walk-in

    public function generateNomorAntrean(int $poliId, string $tanggal, bool $isAppointment = false): string
    {
        return DB::transaction(function () use ($poliId, $tanggal, $isAppointment) {
            $prefix = $isAppointment ? 'A' : 'W';

            // whereDate('tanggal', ...) sebelumnya membungkus kolom tanggal
            // dalam fungsi (DATE(tanggal) = ?), yang mencegah MySQL memakai
            // index range scan pada kolom ini -- lockForUpdate() jadi tidak
            // efektif mengunci baris yang relevan sehingga rawan race
            // condition kalau 2 pendaftaran poli+hari yang sama terjadi
            // bersamaan. Diganti whereBetween supaya sargable dan lock
          // benar-benar berlaku pada baris yang dihitung di bawah.
            $awal  = "{$tanggal} 00:00:00";
            $akhir = "{$tanggal} 23:59:59";

            $nomorAktif = Kunjungan::where('poli_id', $poliId)
                ->whereBetween('tanggal', [$awal, $akhir])
                ->where('nomor_antrean', 'like', "{$prefix}-%")
                ->whereNotIn('status', ['dibatalkan'])
                ->lockForUpdate()
                ->pluck('nomor_antrean')
                ->all();

            $nomor    = count($nomorAktif) + 1;
            $terpakai = array_flip($nomorAktif);

            // Pengaman tambahan: kalau angka hasil hitungan di atas ternyata
            // sudah dipakai kunjungan AKTIF lain, naikkan sampai ketemu yang
            // benar-benar kosong. Kunjungan yang sudah dibatalkan sengaja
            // TIDAK dihitung di sini (dan boleh nomornya dipakai ulang) --
            // itu perilaku existing yang sengaja dipertahankan, bukan bug.
            while (isset($terpakai[$prefix . '-' . str_pad($nomor, 3, '0', STR_PAD_LEFT)])) {
                $nomor++;
            }

            return $prefix . '-' . str_pad($nomor, 3, '0', STR_PAD_LEFT);
        });
    }

    // ── Validasi Kuota ────────────────────────────────────────

    public function cekSisaKuota(int $jadwalPraktekId, string $tanggal): int
    {
        $jadwal = JadwalPraktek::findOrFail($jadwalPraktekId);

        $terpakai = Kunjungan::whereDate('tanggal', $tanggal)
            ->whereHas('appointment', fn ($q) =>
                $q->where('jadwal_praktek_id', $jadwalPraktekId))
            ->whereNotIn('status', ['dibatalkan'])
            ->count();

        // Tambah appointment yang belum check-in -- $terpakai (sudah
        // checked-in, punya Kunjungan) dan $appointment (masih status
        // 'booked') SALING LEPAS: begitu appointment check-in, statusnya
        // berubah dari 'booked' (keluar dari $appointment) sekaligus
        // punya Kunjungan baru (masuk ke $terpakai). Jadi total slot
        // terpakai = jumlah keduanya, BUKAN nilai terbesarnya -- pakai
        // max() di sini bikin kuota keliru dihitung lebih longgar begitu
        // sebagian appointment pada jadwal yang sama sudah check-in
        // sementara sisanya masih booked (bisa overbooking).
        $appointment = Appointment::where('jadwal_praktek_id', $jadwalPraktekId)
            ->where('tanggal_appointment', $tanggal)
            ->where('status', 'booked')
            ->count();

        $total = $terpakai + $appointment;
        return max(0, $jadwal->kuota_pasien - $total);
    }

    // ── Jadwal Dokter Tersedia ────────────────────────────────

    public function getDokterTersedia(?string $spesialisasi = null, ?string $tanggal = null): \Illuminate\Support\Collection
    {
        $hari = $tanggal
            ? strtolower(Carbon::parse($tanggal)->locale('id')->isoFormat('dddd'))
            : strtolower(now()->locale('id')->isoFormat('dddd'));

        // Map nama hari Indonesia → enum hari
        $hariMap = [
            'senin' => 'senin', 'selasa' => 'selasa', 'rabu' => 'rabu',
            'kamis' => 'kamis', 'jumat' => 'jumat', 'sabtu' => 'sabtu', 'minggu' => 'minggu',
        ];
        $hariEnum = $hariMap[$hari] ?? $hari;

        return \App\Models\Dokter::with(['user:id,nama', 'poli:id,nama,kode',
            'dokterPoli' => fn ($q) => $q->where('is_aktif', true)
                ->with(['poli:id,nama,kode',
                    'jadwalPraktek' => fn ($jq) => $jq->where('hari', $hariEnum)->where('is_aktif', true),
                ]),
        ])
        ->whereHas('user', fn ($q) => $q->where('is_active', true))
        ->when($spesialisasi, fn ($q, $s) => $q->where('spesialisasi', 'like', "%{$s}%"))
        ->whereHas('dokterPoli.jadwalPraktek', fn ($q) =>
            $q->where('hari', $hariEnum)->where('is_aktif', true))
        ->get()
        ->filter(fn ($d) => $d->isSipAktif());
    }

    // ── Buat Appointment (Tab 1) ──────────────────────────────

    public function buatAppointment(array $data): Appointment
    {
        // Validasi kuota
        if (isset($data['jadwal_praktek_id'])) {
            $sisa = $this->cekSisaKuota($data['jadwal_praktek_id'], $data['tanggal_appointment']);
            if ($sisa <= 0) {
                throw ValidationException::withMessages([
                    'jadwal_praktek_id' => 'Kuota jadwal dokter ini sudah penuh.',
                ]);
            }
        }

        // Cek apakah pasien sudah punya appointment di hari/dokter yang sama
        $duplikat = Appointment::where('pasien_id', $data['pasien_id'])
            ->where('dokter_id', $data['dokter_id'])
            ->where('tanggal_appointment', $data['tanggal_appointment'])
            ->where('status', 'booked')
            ->exists();

        if ($duplikat) {
            throw ValidationException::withMessages([
                'pasien_id' => 'Pasien sudah memiliki appointment dengan dokter ini pada tanggal tersebut.',
            ]);
        }

        $data['kode_booking'] = Appointment::generateKodeBooking();

        $appointment = Appointment::create($data);

        activity('kunjungan')
            ->performedOn($appointment)
            ->causedBy(auth()->user())
            ->withProperties(['kode_booking' => $appointment->kode_booking])
            ->log('Appointment dibuat');

        return $appointment;
    }

    // ── Daftarkan Kunjungan (Tab 2 - Walk-in / dari Appointment) ──

    public function daftarKunjungan(array $data, ?int $appointmentId = null): Kunjungan
    {
        return DB::transaction(function () use ($data, $appointmentId) {

            // Validasi dokter punya jadwal hari ini (jika bukan dari appointment)
            if (! $appointmentId && isset($data['dokter_id']) && isset($data['poli_id'])) {
                $hariIni = strtolower(now()->locale('id')->isoFormat('dddd'));
                $hariMap = ['senin'=>'senin','selasa'=>'selasa','rabu'=>'rabu',
                            'kamis'=>'kamis','jumat'=>'jumat','sabtu'=>'sabtu','minggu'=>'minggu'];
                $hari = $hariMap[$hariIni] ?? $hariIni;

                $adaJadwal = DokterPoli::where('dokter_id', $data['dokter_id'])
                    ->where('poli_id', $data['poli_id'])
                    ->where('is_aktif', true)
                    ->whereHas('jadwalPraktek', fn ($q) =>
                        $q->where('hari', $hari)->where('is_aktif', true))
                    ->exists();

                if (! $adaJadwal) {
                    throw ValidationException::withMessages([
                        'dokter_id' => 'Dokter tidak memiliki jadwal aktif hari ini untuk poli yang dipilih.',
                    ]);
                }
            }

            // Update appointment jika dari appointment
            if ($appointmentId) {
                Appointment::where('id', $appointmentId)
                    ->update(['status' => 'checked_in']);
            }

            // Appointment → prefix A (prioritas), Walk-in → prefix W
            $isAppointment = $appointmentId !== null;
            $noAntrean = $this->generateNomorAntrean(
                $data['poli_id'],
                now()->toDateString(),
                $isAppointment
            );

            $kunjungan = Kunjungan::create([
                'appointment_id' => $appointmentId,
                'nomor_antrean'  => $noAntrean,
                'pasien_id'      => $data['pasien_id'],
                'dokter_id'      => $data['dokter_id'],
                'poli_id'        => $data['poli_id'],
                'tanggal'        => now(),
                'keluhan'        => $data['keluhan'] ?? null,
                'status'         => 'menunggu',
                'tipe_pembayaran'=> $data['tipe_pembayaran'] ?? 'umum',
            ]);

            activity('kunjungan')
                ->performedOn($kunjungan)
                ->causedBy(auth()->user())
                ->withProperties(['nomor_antrean' => $noAntrean])
                ->log('Pasien didaftarkan kunjungan');

            return $kunjungan;
        });
    }

    // ── Panggil Pasien (Menunggu → Dalam Pemeriksaan) ────────

    public function panggilPasien(int $kunjunganId): Kunjungan
    {
        $kunjungan = Kunjungan::findOrFail($kunjunganId);
        $kunjungan->update([
            'status'        => 'dalam_pemeriksaan',
            'waktu_panggil' => now(),
        ]);

        activity('kunjungan')
            ->performedOn($kunjungan)
            ->causedBy(auth()->user())
            ->log('Pasien dipanggil ke ruang pemeriksaan');

        return $kunjungan;
    }

    // ── Selesai Pemeriksaan ────────────────────────────────────

    /**
     * Menandai kunjungan selesai -- status ini yang jadi syarat pasien
     * boleh masuk alur billing (lihat TagihanPasien::prosesPembayaran()).
     * Sebelumnya TIDAK ADA pengecekan apa pun: kunjungan bisa "Selesai"
     * dan lanjut ke kasir walau dokter belum pernah menulis/
     * memfinalisasi SOAP Note-nya sama sekali -- rekam medis kunjungan
     * bisa jadi kosong padahal sudah ditagih ke pasien. Dibandingkan
     * dengan hasPendingResep yang SUDAH benar dicek sebelum pembayaran
     * bisa diproses, tapi finalisasi SOAP Note tidak.
     */
    public function selesaiPemeriksaan(int $kunjunganId): Kunjungan
    {
        $kunjungan = Kunjungan::with('soapNote')->findOrFail($kunjunganId);

        if (! $kunjungan->soapNote || ! $kunjungan->soapNote->is_final) {
            throw ValidationException::withMessages([
                'id' => 'Pemeriksaan belum bisa diselesaikan -- SOAP Note dokter belum difinalisasi.',
            ]);
        }

        $kunjungan->update(['status' => 'selesai']);

        activity('kunjungan')
            ->performedOn($kunjungan)
            ->causedBy(auth()->user())
            ->log('Pemeriksaan selesai');

        return $kunjungan;
    }

    // ── Simpan Asesmen Perawat ────────────────────────────────

    public function simpanAsesmen(int $kunjunganId, array $data): \App\Models\AsesmenPerawat
    {
        $perawatId = \App\Models\Perawat::where('user_id', auth()->id())->value('id');

        return \App\Models\AsesmenPerawat::updateOrCreate(
            ['kunjungan_id' => $kunjunganId],
            array_merge($data, ['perawat_id' => $perawatId])
        );
    }

    // ── Cancel Kunjungan ──────────────────────────────────────

    public function cancelKunjungan(int $kunjunganId): Kunjungan
    {
        $kunjungan = Kunjungan::with(['appointment', 'invoice'])->findOrFail($kunjunganId);

        // Billing sudah diimplementasi penuh (tabel billing/Invoice) --
        // cek ini sebelumnya cuma placeholder ter-comment dan lupa
        // diaktifkan lagi setelah modul billing jadi. Kunjungan yang
        // sudah punya tagihan (apa pun statusnya selain 'dibatalkan')
        // tidak boleh dibatalkan langsung -- tagihannya harus diurus dulu,
        // supaya tidak ada kunjungan berstatus "dibatalkan" tapi masih
        // punya tagihan aktif/lunas yang menggantung.
        if ($kunjungan->invoice && $kunjungan->invoice->status !== 'dibatalkan') {
            throw ValidationException::withMessages([
                'id' => "Kunjungan tidak bisa dibatalkan -- sudah ada tagihan #{$kunjungan->invoice->nomor_invoice} "
                      . "berstatus \"{$kunjungan->invoice->status}\". Batalkan/selesaikan tagihannya terlebih dahulu.",
            ]);
        }

        $kunjungan->update(['status' => 'dibatalkan']);

        // Kembalikan appointment ke booked jika ada
        if ($kunjungan->appointment) {
            $kunjungan->appointment->update(['status' => 'booked']);
        }

        activity('kunjungan')
            ->performedOn($kunjungan)
            ->causedBy(auth()->user())
            ->log('Kunjungan dibatalkan');

        return $kunjungan;
    }
}

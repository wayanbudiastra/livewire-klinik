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

            // Hitung berdasarkan prefix masing-masing
            $count = Kunjungan::whereDate('tanggal', $tanggal)
                ->where('poli_id', $poliId)
                ->where('nomor_antrean', 'like', "{$prefix}-%")
                ->whereNotIn('status', ['dibatalkan'])
                ->lockForUpdate()
                ->count();

            $nomor = $count + 1;
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

        // Tambah appointment yang belum check-in
        $appointment = Appointment::where('jadwal_praktek_id', $jadwalPraktekId)
            ->where('tanggal_appointment', $tanggal)
            ->where('status', 'booked')
            ->count();

        $total = max($terpakai, $appointment);
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

    // ── Cancel Kunjungan ──────────────────────────────────────

    public function cancelKunjungan(int $kunjunganId): Kunjungan
    {
        $kunjungan = Kunjungan::with('appointment')->findOrFail($kunjunganId);

        // Cek status billing (placeholder — billing belum diimplementasi)
        // if ($kunjungan->billing && $kunjungan->billing->status === 'closed') {
        //     throw ValidationException::withMessages(['id' => 'Billing sudah ditutup.']);
        // }

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

<?php

namespace Database\Seeders;

use App\Models\Dokter;
use App\Models\DokterPoli;
use App\Models\JadwalPraktek;
use App\Models\Poli;
use App\Models\SharingFee;
use App\Models\User;
use Illuminate\Database\Seeder;

class DokterV3Seeder extends Seeder
{
    public function run(): void
    {
        $dokterUser = User::where('email', 'dokter@emr.app')->first();
        if (! $dokterUser) {
            $this->command->warn('⚠ User dokter tidak ditemukan.');
            return;
        }

        $poliUmum = Poli::where('kode', 'UMUM')->first();
        $poliMata = Poli::where('kode', 'MATA')->first();

        if (! $poliUmum) {
            $this->command->warn('⚠ Poli belum ada. Jalankan PoliSeeder dulu.');
            return;
        }

        // ── 1. Buat/update profil dokter ─────────────────────
        $dokter = Dokter::firstOrCreate(
            ['user_id' => $dokterUser->id],
            ['poli_id' => $poliUmum ? $poliUmum->id : null]
        );

        $dokter->update([
            'nik'             => '3201011501850001',
            'no_sip'          => '446/SIP-DU/2024',
            'tgl_expired_sip' => '2026-12-31',
            'spesialisasi'    => 'Umum',
        ]);
        $this->command->info("✓ Profil dokter: {$dokterUser->nama}");

        // ── 2. Mapping Poli ──────────────────────────────────
        $mappingUmum = DokterPoli::firstOrCreate(
            ['dokter_id' => $dokter->id, 'poli_id' => $poliUmum->id],
            ['is_aktif' => true]
        );

        $mappingMata = null;
        if ($poliMata) {
            $mappingMata = DokterPoli::firstOrCreate(
                ['dokter_id' => $dokter->id, 'poli_id' => $poliMata->id],
                ['is_aktif' => true]
            );
        }
        $this->command->info('✓ Mapping Poli: Umum' . ($poliMata ? ' + Mata' : ''));

        // ── 3. Sharing Fee ───────────────────────────────────
        $fees = [
            'tindakan'  => 15,
            'lab'       => 10,
            'radiologi' => 10,
            'peralatan' => 0,
        ];

        foreach ($fees as $kategori => $persen) {
            SharingFee::updateOrCreate(
                ['dokter_id' => $dokter->id, 'kategori' => $kategori],
                ['persentase' => $persen]
            );
        }
        $this->command->info('✓ Sharing Fee: Tindakan 15% · Lab 10% · Rad 10% · Peralatan 0%');

        // ── 4. Jadwal Praktek ────────────────────────────────
        $jadwal = [
            ['dokter_poli_id' => $mappingUmum->id, 'hari' => 'senin',  'jam_mulai' => '08:00', 'jam_selesai' => '12:00', 'kuota_pasien' => 20],
            ['dokter_poli_id' => $mappingUmum->id, 'hari' => 'selasa', 'jam_mulai' => '08:00', 'jam_selesai' => '12:00', 'kuota_pasien' => 20],
            ['dokter_poli_id' => $mappingUmum->id, 'hari' => 'rabu',   'jam_mulai' => '08:00', 'jam_selesai' => '12:00', 'kuota_pasien' => 20],
            ['dokter_poli_id' => $mappingUmum->id, 'hari' => 'kamis',  'jam_mulai' => '13:00', 'jam_selesai' => '17:00', 'kuota_pasien' => 15, 'keterangan' => 'Sesi Sore'],
            ['dokter_poli_id' => $mappingUmum->id, 'hari' => 'jumat',  'jam_mulai' => '08:00', 'jam_selesai' => '11:00', 'kuota_pasien' => 12],
        ];

        if ($mappingMata) {
            $jadwal = array_merge($jadwal, [
                ['dokter_poli_id' => $mappingMata->id, 'hari' => 'senin',  'jam_mulai' => '13:00', 'jam_selesai' => '16:00', 'kuota_pasien' => 10],
                ['dokter_poli_id' => $mappingMata->id, 'hari' => 'kamis',  'jam_mulai' => '08:00', 'jam_selesai' => '11:00', 'kuota_pasien' => 10],
                ['dokter_poli_id' => $mappingMata->id, 'hari' => 'sabtu',  'jam_mulai' => '08:00', 'jam_selesai' => '12:00', 'kuota_pasien' => 15],
            ]);
        }

        foreach ($jadwal as $j) {
            JadwalPraktek::firstOrCreate(
                [
                    'dokter_poli_id' => $j['dokter_poli_id'],
                    'hari'           => $j['hari'],
                    'jam_mulai'      => $j['jam_mulai'],
                ],
                $j
            );
        }

        $this->command->info('✓ Jadwal Praktek: ' . count($jadwal) . ' slot');
        $this->command->info('✅ DokterV3Seeder selesai.');
    }
}

<?php

namespace Database\Seeders;

use App\Models\Barang;
use App\Models\Dokter;
use App\Models\DokterPoli;
use App\Models\Kunjungan;
use App\Models\MasterTindakan;
use App\Models\Pasien;
use App\Models\Poli;
use App\Models\Tindakan;
use App\Services\KunjunganService;
use App\Services\PasienService;
use Illuminate\Database\Seeder;

/**
 * Data uji LOKAL untuk mencoba fitur kasir (tab BHP & Obat, panel Tambah Komponen,
 * laporan BHP & Obat Kasir): 5 pasien terdaftar di Poli Umum dengan kunjungan
 * berstatus 'selesai' (siap ditagih kasir) + beberapa barang BHP/obat uji berstok.
 *
 * Jalankan: php artisan db:seed --class=PasienKasirUjiSeeder
 * Aman dijalankan berulang (idempoten: pasien dicari lewat NIK, barang lewat kode,
 * kunjungan hari ini tidak digandakan). Tidak dimasukkan ke DatabaseSeeder dan
 * menolak berjalan di production.
 */
class PasienKasirUjiSeeder extends Seeder
{
    private const PASIEN = [
        ['[UJI] Made Wirawan',   '5171010101900101', 'L', '1990-01-01', 'Nyeri tenggorokan dan demam ringan'],
        ['[UJI] Ni Luh Ayu',     '5171010202910102', 'P', '1991-02-02', 'Batuk pilek sejak 2 hari'],
        ['[UJI] Kadek Dharma',   '5171010303920103', 'L', '1992-03-03', 'Sakit kepala dan pusing'],
        ['[UJI] Putu Sari',      '5171010404930104', 'P', '1993-04-04', 'Luka di tangan akibat terpotong'],
        ['[UJI] Komang Adi',     '5171010505940105', 'L', '1994-05-05', 'Kontrol dan keluhan lambung'],
    ];

    /** [kode, nama, jenis, satuan, stok, harga_pokok, harga_jual] */
    private const BARANG = [
        ['TST-BHP-001', 'Kasa Steril 10x10 (Uji)',   'bahan_habis_pakai', 'pcs',  100, 1000,  2500],
        ['TST-BHP-002', 'Handscoon Steril (Uji)',    'bahan_habis_pakai', 'pasang', 50, 4000, 8000],
        ['TST-BHP-003', 'Plester Luka (Uji)',        'bahan_habis_pakai', 'pcs',   30,  500,  1500],
        ['TST-BHP-004', 'BHP Stok Kosong (Uji)',     'bahan_habis_pakai', 'pcs',    0,  500,  1500],
        ['TST-OBT-001', 'Paracetamol 500mg (Uji)',   'obat',              'tab',  200,  300,  1000],
        ['TST-OBT-002', 'Vitamin C 500mg (Uji)',     'obat',              'tab',  150,  400,  1200],
        ['TST-OBT-003', 'Antasida Doen (Uji)',       'obat',              'tab',   80,  350,  1000],
        ['TST-OBT-004', 'Obat Stok Tipis (Uji)',     'obat',              'tab',    3,  500,  1500],
    ];

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->error('Seeder data uji menolak berjalan di production.');
            return;
        }

        $poli = Poli::where('nama', 'Poli Umum')->first();
        if (! $poli) {
            $this->command?->error('Poli Umum tidak ditemukan. Jalankan PoliSeeder dulu.');
            return;
        }

        $dokter = DokterPoli::where('poli_id', $poli->id)->where('is_aktif', true)->first()?->dokter
            ?? Dokter::first();
        if (! $dokter) {
            $this->command?->error('Belum ada dokter. Jalankan DokterV3Seeder dulu.');
            return;
        }

        $konsul = MasterTindakan::where('nama', 'Pemeriksaan Fisik Umum')->first()
            ?? MasterTindakan::aktif()->orderBy('tarif')->first();

        $this->seedBarang();

        $pasienService    = app(PasienService::class);
        $kunjunganService = app(KunjunganService::class);
        $baru = 0;

        foreach (self::PASIEN as [$nama, $nik, $jk, $lahir, $keluhan]) {
            $pasien = Pasien::where('nik', $nik)->first() ?? $pasienService->create([
                'nama' => $nama, 'nik' => $nik, 'tipe_pasien' => 'WNI',
                'tempat_lahir' => 'Denpasar', 'tanggal_lahir' => $lahir, 'jenis_kelamin' => $jk,
                'alamat' => 'Alamat data uji lokal', 'telepon' => '0812000' . substr($nik, -4),
            ]);

            $sudahAda = Kunjungan::where('pasien_id', $pasien->id)
                ->where('poli_id', $poli->id)->whereDate('tanggal', today())
                ->where('status', '!=', 'dibatalkan')->exists();
            if ($sudahAda) continue;

            $kunjungan = Kunjungan::create([
                'nomor_antrean'   => $kunjunganService->generateNomorAntrean($poli->id, today()->toDateString()),
                'pasien_id'       => $pasien->id,
                'dokter_id'       => $dokter->id,
                'poli_id'         => $poli->id,
                'tanggal'         => now(),
                'keluhan'         => $keluhan,
                'status'          => 'selesai', // siap ditagih (lolos gate tanda vital kasir)
                'tipe_pembayaran' => 'umum',
            ]);

            if ($konsul) {
                Tindakan::create([
                    'kunjungan_id' => $kunjungan->id, 'master_tindakan_id' => $konsul->id,
                    'jumlah' => 1, 'waktu_tindakan' => now(),
                ]);
            }
            $baru++;
        }

        $this->command?->info("Pasien uji: " . count(self::PASIEN) . " (kunjungan baru hari ini: {$baru}). Poli Umum, dokter: " . ($dokter->user?->nama ?? "#{$dokter->id}") . '.');
    }

    private function seedBarang(): void
    {
        foreach (self::BARANG as [$kode, $nama, $jenis, $satuan, $stok, $pokok, $jual]) {
            Barang::firstOrCreate(['kode' => $kode], [
                'nama' => $nama, 'jenis' => $jenis, 'satuan' => $satuan, 'stok' => $stok,
                'harga_pokok' => $pokok, 'harga_jual' => $jual, 'is_active' => true,
            ]);
        }
    }
}

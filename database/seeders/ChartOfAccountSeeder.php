<?php

namespace Database\Seeders;

use App\Models\Akuntansi\ChartOfAccount;
use Illuminate\Database\Seeder;

class ChartOfAccountSeeder extends Seeder
{
    public function run(): void
    {
        // [kode, nama, golongan, tipe_normal, kelompok, is_kas_setara_kas]
        $akun = [
            // Aset
            ['1-1100', 'Kas',                                   'aset',       'debit',  'lancar', true],
            ['1-1200', 'Bank',                                   'aset',       'debit',  'lancar', true],
            ['1-1300', 'Persediaan Barang (Obat & Alkes)',       'aset',       'debit',  'lancar', false],
            ['1-1400', 'Piutang Asuransi/BPJS',                  'aset',       'debit',  'lancar', false],
            ['1-1500', 'Deposit Pasien',                         'aset',       'debit',  'lancar', false],

            // Liabilitas
            ['2-1100', 'Hutang Dagang (Supplier)',               'liabilitas', 'kredit', 'jangka_pendek', false],
            ['2-1200', 'Hutang Jasa Dokter (Sharing Fee)',       'liabilitas', 'kredit', 'jangka_pendek', false],
            ['2-1300', 'Titipan Deposit Pasien',                 'liabilitas', 'kredit', 'jangka_pendek', false],

            // Ekuitas
            ['3-1100', 'Modal Pemilik',                          'ekuitas',    'kredit', null, false],
            ['3-1200', 'Laba Ditahan',                           'ekuitas',    'kredit', null, false],

            // Pendapatan
            ['4-1100', 'Pendapatan Jasa Medis (Tindakan/Konsultasi)', 'pendapatan', 'kredit', null, false],
            ['4-1200', 'Pendapatan Penunjang (Lab/Radiologi)',   'pendapatan', 'kredit', null, false],
            ['4-1300', 'Pendapatan Penjualan Obat (Resep + Ritel)', 'pendapatan', 'kredit', null, false],
            ['4-1400', 'Pendapatan Klaim Asuransi/BPJS',         'pendapatan', 'kredit', null, false],

            // Biaya
            ['5-1100', 'HPP Farmasi (Obat & Alkes Terjual)',     'biaya',      'debit',  null, false],
            ['5-1200', 'Biaya Jasa Dokter (Sharing Fee)',        'biaya',      'debit',  null, false],
            ['5-2100', 'Biaya BHP (Bahan Habis Pakai)',          'biaya',      'debit',  null, false],
            ['5-3100', 'Biaya Operasional Lainnya',              'biaya',      'debit',  null, false],

            // Lainnya
            ['8-1100', 'Selisih Stok Opname',                    'lainnya',    'debit',  null, false],
            ['8-1200', 'Piutang Tak Tertagih (Write-off)',       'lainnya',    'debit',  null, false],
        ];

        foreach ($akun as [$kode, $nama, $golongan, $tipeNormal, $kelompok, $isKas]) {
            ChartOfAccount::updateOrCreate(
                ['kode' => $kode],
                [
                    'nama'              => $nama,
                    'golongan'          => $golongan,
                    'tipe_normal'       => $tipeNormal,
                    'kelompok'          => $kelompok,
                    'is_kas_setara_kas' => $isKas,
                    'is_aktif'          => true,
                ]
            );
        }

        $this->command->info('Chart of Accounts: ' . count($akun) . ' akun berhasil di-seed.');
    }
}

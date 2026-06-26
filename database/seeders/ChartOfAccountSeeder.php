<?php

namespace Database\Seeders;

use App\Models\Akuntansi\ChartOfAccount;
use Illuminate\Database\Seeder;

class ChartOfAccountSeeder extends Seeder
{
    public function run(): void
    {
        $akun = [
            // Aset
            ['1-1100', 'Kas',                                   'aset',       'debit'],
            ['1-1200', 'Bank',                                   'aset',       'debit'],
            ['1-1300', 'Persediaan Barang (Obat & Alkes)',       'aset',       'debit'],
            ['1-1400', 'Piutang Asuransi/BPJS',                  'aset',       'debit'],
            ['1-1500', 'Deposit Pasien',                         'aset',       'debit'],

            // Liabilitas
            ['2-1100', 'Hutang Dagang (Supplier)',               'liabilitas', 'kredit'],
            ['2-1200', 'Hutang Jasa Dokter (Sharing Fee)',       'liabilitas', 'kredit'],
            ['2-1300', 'Titipan Deposit Pasien',                 'liabilitas', 'kredit'],

            // Ekuitas
            ['3-1100', 'Modal Pemilik',                          'ekuitas',    'kredit'],
            ['3-1200', 'Laba Ditahan',                           'ekuitas',    'kredit'],

            // Pendapatan
            ['4-1100', 'Pendapatan Jasa Medis (Tindakan/Konsultasi)', 'pendapatan', 'kredit'],
            ['4-1200', 'Pendapatan Penunjang (Lab/Radiologi)',   'pendapatan', 'kredit'],
            ['4-1300', 'Pendapatan Penjualan Obat (Resep + Ritel)', 'pendapatan', 'kredit'],
            ['4-1400', 'Pendapatan Klaim Asuransi/BPJS',         'pendapatan', 'kredit'],

            // Biaya
            ['5-1100', 'HPP Farmasi (Obat & Alkes Terjual)',     'biaya',      'debit'],
            ['5-1200', 'Biaya Jasa Dokter (Sharing Fee)',        'biaya',      'debit'],
            ['5-2100', 'Biaya BHP (Bahan Habis Pakai)',          'biaya',      'debit'],
            ['5-3100', 'Biaya Operasional Lainnya',              'biaya',      'debit'],

            // Lainnya
            ['8-1100', 'Selisih Stok Opname',                    'lainnya',    'debit'],
            ['8-1200', 'Piutang Tak Tertagih (Write-off)',       'lainnya',    'debit'],
        ];

        foreach ($akun as [$kode, $nama, $golongan, $tipeNormal]) {
            ChartOfAccount::updateOrCreate(
                ['kode' => $kode],
                ['nama' => $nama, 'golongan' => $golongan, 'tipe_normal' => $tipeNormal, 'is_aktif' => true]
            );
        }

        $this->command->info('Chart of Accounts: ' . count($akun) . ' akun berhasil di-seed.');
    }
}

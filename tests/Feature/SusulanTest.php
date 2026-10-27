<?php

namespace Tests\Feature;

use App\Models\Akuntansi\JurnalPending;
use App\Models\Barang;
use App\Models\Dokter;
use App\Models\Invoice;
use App\Models\ItemPenunjang;
use App\Models\Kunjungan;
use App\Models\Pasien;
use App\Models\Poli;
use App\Models\SesiKas;
use App\Models\SharingFee;
use App\Models\TransaksiRitel;
use App\Models\User;
use App\Services\Farmasi\ObatRitelService;
use App\Services\Farmasi\SusulanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Order susulan (lab/obat tambahan atas saran dokter setelah bill lunas).
 * Aturan: docs/PERANCANGAN_COMBINE_BILL_RITEL.md.
 *
 * RefreshDatabase -> jalan di database emr_test (lihat phpunit.xml), bukan emr_db.
 */
class SusulanTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $prefix): User
    {
        return User::create([
            'nama' => $prefix . uniqid(), 'email' => $prefix . uniqid() . '@example.test',
            'password' => Hash::make('password'), 'is_active' => true,
        ]);
    }

    /** @return array{kasir:User, dokter:Dokter, pasien:Pasien, kunjungan:Kunjungan, billing:Invoice} */
    private function skenario(string $statusBilling = 'lunas', int $hariLalu = 1): array
    {
        $kasir  = $this->user('kasir-');
        SesiKas::create(['user_id' => $kasir->id, 'tanggal' => today(), 'dibuka_pada' => now(), 'saldo_awal' => 0, 'status' => 'buka']);

        $dokter = Dokter::create(['user_id' => $this->user('dokter-')->id]);
        $poli   = Poli::create(['nama' => 'Poli ' . uniqid(), 'kode' => 'P' . rand(1000, 9999), 'is_active' => true]);
        $pasien = Pasien::create([
            'nomor_rm' => 'RM-' . uniqid(), 'nama' => 'Pasien ' . uniqid(), 'tempat_lahir' => 'Denpasar',
            'tanggal_lahir' => '1990-01-01', 'jenis_kelamin' => 'L', 'alamat' => 'Jl. Test', 'telepon' => '08123',
        ]);
        $kunjungan = Kunjungan::create([
            'nomor_antrean' => 'W-' . rand(100, 999), 'pasien_id' => $pasien->id,
            'dokter_id' => $dokter->id, 'poli_id' => $poli->id,
            'tanggal' => now()->subDays($hariLalu), 'status' => 'selesai',
        ]);
        $billing = Invoice::create([
            'kunjungan_id' => $kunjungan->id, 'nomor_invoice' => 'INV-' . uniqid(),
            'total_tagihan' => 100000, 'total_bayar' => 100000, 'sisa' => 0, 'status' => $statusBilling,
        ]);

        return compact('kasir', 'dokter', 'pasien', 'kunjungan', 'billing');
    }

    private function itemLab(float $tarif = 85000): ItemPenunjang
    {
        return ItemPenunjang::create([
            'kode' => 'LAB' . rand(1000, 9999), 'nama' => 'Darah Lengkap', 'kategori' => 'lab',
            'tarif' => $tarif, 'is_active' => true,
        ]);
    }

    private function barang(): Barang
    {
        return Barang::create([
            'kode' => 'OBT' . rand(1000, 9999), 'nama' => 'Paracetamol', 'jenis' => 'obat', 'satuan' => 'Tab',
            'stok' => 50, 'harga_pokok' => 300, 'harga_jual' => 1000, 'is_active' => true,
        ]);
    }

    private function dataLab(array $s, ItemPenunjang $item): array
    {
        return [
            'pasien_id' => $s['pasien']->id, 'kunjungan_id' => $s['kunjungan']->id,
            'dokter_id' => $s['dokter']->id, 'jenis' => 'lab',
            'items' => [['item_penunjang_id' => $item->id]],
        ];
    }

    public function test_susulan_lab_dibuat_dan_tertaut_ke_kunjungan_dan_billing_asal(): void
    {
        $s  = $this->skenario();
        $tr = app(SusulanService::class)->buat($this->dataLab($s, $this->itemLab()), $s['kasir']->id);

        $this->assertSame('susulan', $tr->tipe);
        $this->assertSame('lab', $tr->jenis_susulan);
        $this->assertSame($s['kunjungan']->id, $tr->kunjungan_id);
        $this->assertSame($s['billing']->id, $tr->billing_id);
        $this->assertSame($s['dokter']->id, $tr->dokter_id);
        $this->assertSame('menunggu_kasir', $tr->status);
        $this->assertEquals(85000, $tr->total_harga);
        $this->assertNull($tr->items->first()->barang_id);
    }

    public function test_tidak_membuat_permintaan_penunjang(): void
    {
        $s = $this->skenario();
        app(SusulanService::class)->buat($this->dataLab($s, $this->itemLab()), $s['kasir']->id);

        $this->assertSame(0, \App\Models\PermintaanPenunjang::count());
    }

    public function test_ditolak_tanpa_pasien_id_atau_tanpa_kunjungan(): void
    {
        $s = $this->skenario();
        $svc = app(SusulanService::class);
        $data = $this->dataLab($s, $this->itemLab());

        foreach (['pasien_id' => 'ID pasien', 'kunjungan_id' => 'registrasi utama'] as $kolom => $kata) {
            $d = $data;
            unset($d[$kolom]);
            try {
                $svc->buat($d, $s['kasir']->id);
                $this->fail("Seharusnya ditolak tanpa {$kolom}");
            } catch (\DomainException $e) {
                $this->assertStringContainsString($kata, $e->getMessage());
            }
        }
    }

    public function test_ditolak_jika_kunjungan_milik_pasien_lain(): void
    {
        $s = $this->skenario();
        $lain = Pasien::create([
            'nomor_rm' => 'RM-' . uniqid(), 'nama' => 'Lain', 'tempat_lahir' => 'X',
            'tanggal_lahir' => '1991-01-01', 'jenis_kelamin' => 'P', 'alamat' => 'Y', 'telepon' => '0812',
        ]);
        $data = $this->dataLab($s, $this->itemLab());
        $data['pasien_id'] = $lain->id;

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('bukan milik pasien');
        app(SusulanService::class)->buat($data, $s['kasir']->id);
    }

    public function test_ditolak_jika_billing_asal_belum_lunas(): void
    {
        $s = $this->skenario('belum_bayar');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('sudah lunas');
        app(SusulanService::class)->buat($this->dataLab($s, $this->itemLab()), $s['kasir']->id);
    }

    public function test_batas_14_hari(): void
    {
        $ok = $this->skenario('lunas', 14);
        app(SusulanService::class)->buat($this->dataLab($ok, $this->itemLab()), $ok['kasir']->id);
        $this->assertSame(1, TransaksiRitel::susulan()->count());

        $lewat = $this->skenario('lunas', 15);
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('batas 14 hari');
        app(SusulanService::class)->buat($this->dataLab($lewat, $this->itemLab()), $lewat['kasir']->id);
    }

    public function test_ditolak_tanpa_dokter_penyaran(): void
    {
        $s = $this->skenario();
        $data = $this->dataLab($s, $this->itemLab());
        unset($data['dokter_id']);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Dokter penyaran');
        app(SusulanService::class)->buat($data, $s['kasir']->id);
    }

    public function test_lab_dan_obat_tidak_boleh_dicampur(): void
    {
        $s = $this->skenario();
        $data = $this->dataLab($s, $this->itemLab());
        $data['items'][] = ['barang_id' => $this->barang()->id, 'jumlah' => 1];

        $this->expectException(\DomainException::class);
        app(SusulanService::class)->buat($data, $s['kasir']->id);
    }

    public function test_bayar_lab_susulan_langsung_selesai_jurnal_lab_dan_fee_lab_dokter(): void
    {
        $s = $this->skenario();
        SharingFee::create(['dokter_id' => $s['dokter']->id, 'kategori' => 'lab', 'persentase' => 10]);
        SharingFee::create(['dokter_id' => $s['dokter']->id, 'kategori' => 'tindakan', 'persentase' => 15]);

        $tr = app(SusulanService::class)->buat($this->dataLab($s, $this->itemLab(85000)), $s['kasir']->id);
        $tr = app(ObatRitelService::class)->prosesBayar($tr, ['total_bayar' => 85000, 'metode_bayar' => 'tunai'], $s['kasir']->id);

        $this->assertSame('selesai', $tr->status);

        $jurnalPenjualan = JurnalPending::where('sumber_id', $tr->id)->where('tipe_transaksi', 'penjualan_ritel')->first();
        $this->assertSame('4-1200', $jurnalPenjualan->kode_akun_kredit, 'lab masuk akun pendapatan lab, bukan obat');

        $fee = JurnalPending::where('sumber_id', $tr->id)->where('tipe_transaksi', 'sharing_fee_dokter')->first();
        $this->assertNotNull($fee);
        $this->assertEquals(8500, $fee->nominal, 'fee = persentase LAB (10%) x 85.000, bukan tindakan 15%');
        $this->assertSame('5-1200', $fee->kode_akun_debit);
        $this->assertSame('2-1200', $fee->kode_akun_kredit);

        $this->assertSame(0, JurnalPending::where('sumber_id', $tr->id)->where('tipe_transaksi', 'hpp_ritel')->count(), 'lab tanpa HPP');
    }

    public function test_fee_lab_tidak_dobel_dan_tidak_ada_jika_persentase_nol(): void
    {
        $s = $this->skenario();
        $tr = app(SusulanService::class)->buat($this->dataLab($s, $this->itemLab()), $s['kasir']->id);
        $tr = app(ObatRitelService::class)->prosesBayar($tr, ['total_bayar' => 85000, 'metode_bayar' => 'tunai'], $s['kasir']->id);

        $this->assertSame(0, JurnalPending::where('tipe_transaksi', 'sharing_fee_dokter')->count(), 'tanpa baris sharing fee lab -> tidak ada fee');

        SharingFee::create(['dokter_id' => $s['dokter']->id, 'kategori' => 'lab', 'persentase' => 10]);
        app(\App\Services\Akuntansi\SharingFeeService::class)->catatSharingFeeSusulan($tr);
        app(\App\Services\Akuntansi\SharingFeeService::class)->catatSharingFeeSusulan($tr);
        $this->assertSame(1, JurnalPending::where('tipe_transaksi', 'sharing_fee_dokter')->count(), 'idempoten');
    }

    public function test_obat_susulan_tanpa_fee_dan_stok_berkurang_saat_diserahkan(): void
    {
        $s = $this->skenario();
        SharingFee::create(['dokter_id' => $s['dokter']->id, 'kategori' => 'lab', 'persentase' => 10]);
        $obat = $this->barang();

        $tr = app(SusulanService::class)->buat([
            'pasien_id' => $s['pasien']->id, 'kunjungan_id' => $s['kunjungan']->id,
            'dokter_id' => $s['dokter']->id, 'jenis' => 'obat',
            'items' => [['barang_id' => $obat->id, 'jumlah' => 3]],
        ], $s['kasir']->id);

        $svc = app(ObatRitelService::class);
        $tr = $svc->prosesBayar($tr, ['total_bayar' => 3000, 'metode_bayar' => 'tunai'], $s['kasir']->id);
        $this->assertSame('dibayar', $tr->status, 'obat masih perlu penyerahan');
        $this->assertSame('4-1300', JurnalPending::where('sumber_id', $tr->id)->where('tipe_transaksi', 'penjualan_ritel')->value('kode_akun_kredit'));
        $this->assertSame(0, JurnalPending::where('tipe_transaksi', 'sharing_fee_dokter')->count(), 'obat susulan tanpa fee');

        $svc->serahkanObat($tr, $s['kasir']->id);
        $this->assertSame(47, $obat->fresh()->stok);
    }

    public function test_susulan_tidak_bisa_dibatalkan_setelah_dibayar(): void
    {
        $s = $this->skenario();
        $tr = app(SusulanService::class)->buat($this->dataLab($s, $this->itemLab()), $s['kasir']->id);
        $this->assertTrue($tr->bisaDibatalkan(), 'sebelum dibayar boleh dibatalkan');

        $tr = app(ObatRitelService::class)->prosesBayar($tr, ['total_bayar' => 85000, 'metode_bayar' => 'tunai'], $s['kasir']->id);
        $this->assertFalse($tr->bisaDibatalkan(), 'setelah dibayar tidak ada retur/batal');
    }
}

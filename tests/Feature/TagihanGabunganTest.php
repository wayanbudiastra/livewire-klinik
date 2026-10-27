<?php

namespace Tests\Feature;

use App\Models\Dokter;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\ItemPenunjang;
use App\Models\Kunjungan;
use App\Models\Pasien;
use App\Models\Poli;
use App\Models\SesiKas;
use App\Models\User;
use App\Services\Farmasi\ObatRitelService;
use App\Services\Farmasi\SusulanService;
use App\Services\Kasir\TagihanGabunganService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Tagihan Gabungan = billing asal + susulan (RefreshDatabase -> emr_test). */
class TagihanGabunganTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        $u = User::create([
            'nama' => $role . uniqid(), 'email' => $role . uniqid() . '@example.test',
            'password' => Hash::make('password'), 'is_active' => true,
        ]);
        $u->assignRole($role);
        return $u;
    }

    /** @return array{kasir:User, dokter:Dokter, billing:Invoice} */
    private function skenario(): array
    {
        $this->seed(RolePermissionSeeder::class);
        $kasir  = $this->user('kasir');
        SesiKas::create(['user_id' => $kasir->id, 'tanggal' => today(), 'dibuka_pada' => now(), 'saldo_awal' => 0, 'status' => 'buka']);
        $dokter = Dokter::create(['user_id' => $this->user('dokter')->id]);
        $poli   = Poli::create(['nama' => 'Poli ' . uniqid(), 'kode' => 'P' . rand(1000, 9999), 'is_active' => true]);
        $pasien = Pasien::create([
            'nomor_rm' => 'RM-' . uniqid(), 'nama' => 'Pasien Gabungan', 'tempat_lahir' => 'Denpasar',
            'tanggal_lahir' => '1990-01-01', 'jenis_kelamin' => 'L', 'alamat' => 'Jl. Test', 'telepon' => '08123',
        ]);
        $k = Kunjungan::create([
            'nomor_antrean' => 'W-' . rand(100, 999), 'pasien_id' => $pasien->id, 'dokter_id' => $dokter->id,
            'poli_id' => $poli->id, 'tanggal' => now()->subDay(), 'status' => 'selesai',
        ]);
        $billing = Invoice::create([
            'kunjungan_id' => $k->id, 'nomor_invoice' => 'INV-' . uniqid(),
            'total_tagihan' => 100000, 'total_bayar' => 100000, 'sisa' => 0, 'status' => 'lunas',
        ]);
        InvoiceItem::create(['billing_id' => $billing->id, 'jenis' => 'tindakan', 'nama_item' => 'Konsultasi', 'qty' => 1, 'harga_satuan' => 100000, 'diskon_item' => 0, 'subtotal' => 100000]);

        return compact('kasir', 'dokter', 'billing');
    }

    private function susulanLab(array $s, float $tarif): \App\Models\TransaksiRitel
    {
        $lab = ItemPenunjang::create(['kode' => 'L' . rand(1000, 9999), 'nama' => 'Lab ' . $tarif, 'kategori' => 'lab', 'tarif' => $tarif, 'is_active' => true]);
        return app(SusulanService::class)->buat([
            'pasien_id' => $s['billing']->kunjungan->pasien_id, 'kunjungan_id' => $s['billing']->kunjungan_id,
            'dokter_id' => $s['dokter']->id, 'jenis' => 'lab', 'items' => [['item_penunjang_id' => $lab->id]],
        ], $s['kasir']->id);
    }

    public function test_total_gabungan_billing_plus_susulan_dan_batal_tidak_dihitung(): void
    {
        $s = $this->skenario();
        $a = $this->susulanLab($s, 85000);
        $b = $this->susulanLab($s, 40000);
        app(ObatRitelService::class)->prosesBayar($a, ['total_bayar' => 85000, 'metode_bayar' => 'tunai'], $s['kasir']->id);
        app(ObatRitelService::class)->batalkan($b);

        $r = app(TagihanGabunganService::class)->susun($s['billing']);

        $this->assertCount(1, $r['susulan'], 'susulan dibatalkan tidak ikut');
        $this->assertEquals(100000, $r['total_billing']);
        $this->assertEquals(85000, $r['total_susulan']);
        $this->assertEquals(185000, $r['total_gabungan']);
        $this->assertEquals(185000, $r['total_dibayar']);
        $this->assertEquals(0, $r['sisa']);
    }

    public function test_susulan_belum_dibayar_masuk_sisa(): void
    {
        $s = $this->skenario();
        $this->susulanLab($s, 85000);

        $r = app(TagihanGabunganService::class)->susun($s['billing']);

        $this->assertEquals(185000, $r['total_gabungan']);
        $this->assertEquals(100000, $r['total_dibayar']);
        $this->assertEquals(85000, $r['sisa']);
    }

    public function test_tidak_mengubah_billing_asal(): void
    {
        $s = $this->skenario();
        $a = $this->susulanLab($s, 85000);
        app(ObatRitelService::class)->prosesBayar($a, ['total_bayar' => 85000, 'metode_bayar' => 'tunai'], $s['kasir']->id);

        app(TagihanGabunganService::class)->susun($s['billing']);

        $fresh = $s['billing']->fresh();
        $this->assertSame('lunas', $fresh->status);
        $this->assertEquals(100000, $fresh->total_tagihan);
        $this->assertEquals(100000, $fresh->total_bayar);
    }

    public function test_halaman_gabungan_hanya_untuk_yang_punya_permission(): void
    {
        $s = $this->skenario();
        $this->susulanLab($s, 85000);
        $url = route('kasir.billing.gabungan', $s['billing']);

        $this->actingAs($this->user('dokter'))->get($url)->assertForbidden();

        $this->actingAs($s['kasir'])->get($url)
            ->assertOk()->assertSee('TOTAL GABUNGAN')->assertSee('185.000');
    }
}

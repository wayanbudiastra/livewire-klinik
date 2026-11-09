<?php

namespace Tests\Feature;

use App\Livewire\Kasir\TagihanPasien;
use App\Models\AsesmenPerawat;
use App\Models\Dokter;
use App\Models\Invoice;
use App\Models\Kunjungan;
use App\Models\MasterTindakan;
use App\Models\Pasien;
use App\Models\PembayaranSplit;
use App\Models\Poli;
use App\Models\SesiKas;
use App\Models\Tindakan;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Permintaan user: kasir sudah bisa MENYUSUN tagihan begitu tanda vital
 * (AsesmenPerawat) diisi perawat -- tidak perlu menunggu dokter selesai
 * menulis SOAP Note dulu. Tapi saat kasir menekan "Bayar", muncul notif
 * bahwa SOAP Note belum disimpan dan pembayaran ditolak.
 *
 * Sebelumnya: kasir tidak bisa apa-apa sampai status kunjungan 'selesai',
 * jadi kasir tidak bisa menyiapkan/menghitung tagihan lebih awal walau
 * tindakan & tanda vital sudah tercatat.
 *
 * UPDATE 2026-11-09: gate pembayaran diganti dari SOAP final menjadi TTV
 * final oleh perawat (atau kunjungan sudah 'selesai'). Lihat
 * TtvFinalisasiTest untuk alur finalisasi lengkapnya.
 *
 * Pakai DatabaseTransactions -- bukan RefreshDatabase.
 */
class KasirTagihanSebelumSoapTest extends TestCase
{
    use DatabaseTransactions;

    private function buatKasir(): User
    {
        $user = User::create([
            'nama' => 'Kasir Test ' . uniqid(), 'email' => 'kasir-' . uniqid() . '@example.test',
            'password' => Hash::make('password'), 'is_active' => true,
        ]);
        $user->assignRole('kasir');
        return $user;
    }

    /**
     * Kunjungan yang masih 'dalam_pemeriksaan' -- dokter belum menekan
     * "Selesai", jadi SOAP Note-nya juga belum final.
     */
    private function buatKunjunganDalamPemeriksaan(): Kunjungan
    {
        $dokterUser = User::create([
            'nama' => 'Dr. Test ' . uniqid(), 'email' => 'dokter-' . uniqid() . '@example.test',
            'password' => Hash::make('password'), 'is_active' => true,
        ]);
        $dokterUser->assignRole('dokter');
        $dokter = Dokter::create(['user_id' => $dokterUser->id]);
        $poli   = Poli::create(['nama' => 'Poli Test ' . uniqid(), 'kode' => 'PT' . rand(1000, 9999), 'is_active' => true]);
        $pasien = Pasien::create([
            'nomor_rm' => 'RM-' . uniqid(), 'nama' => 'Pasien Test ' . uniqid(), 'tempat_lahir' => 'Denpasar',
            'tanggal_lahir' => '1990-01-01', 'jenis_kelamin' => 'L', 'alamat' => 'Jl. Test', 'telepon' => '08123',
        ]);

        return Kunjungan::create([
            'nomor_antrean' => 'W-' . rand(100, 999), 'pasien_id' => $pasien->id,
            'dokter_id' => $dokter->id, 'poli_id' => $poli->id,
            'tanggal' => now(), 'status' => 'dalam_pemeriksaan',
        ]);
    }

    private function isiTandaVital(Kunjungan $kunjungan): AsesmenPerawat
    {
        return AsesmenPerawat::create([
            'kunjungan_id'  => $kunjungan->id,
            'tekanan_darah' => '120/80',
            'nadi'          => 80,
            'suhu'          => 36.5,
        ]);
    }

    /** Tindakan supaya invoice punya isi, bukan tagihan Rp 0. */
    private function tambahTindakan(Kunjungan $kunjungan, float $tarif = 150000): Tindakan
    {
        $master = MasterTindakan::create([
            'kode' => 'TND-' . uniqid(), 'nama' => 'Tindakan Test ' . uniqid(),
            'tarif' => $tarif, 'is_active' => true,
        ]);

        return Tindakan::create([
            'kunjungan_id'       => $kunjungan->id,
            'master_tindakan_id' => $master->id,
            'jumlah'             => 1,
        ]);
    }

    private function bukaSesiKas(User $kasir): SesiKas
    {
        return SesiKas::create([
            'user_id' => $kasir->id, 'tanggal' => now()->toDateString(),
            'dibuka_pada' => now(), 'saldo_awal' => 0, 'status' => 'buka',
        ]);
    }

    // ── Penyusunan tagihan sudah boleh sejak tanda vital terisi ──────

    /** @test */
    public function tagihan_sudah_bisa_disusun_begitu_tanda_vital_terisi_walau_soap_belum_final(): void
    {
        $kasir     = $this->buatKasir();
        $kunjungan = $this->buatKunjunganDalamPemeriksaan();
        $this->isiTandaVital($kunjungan);
        $this->tambahTindakan($kunjungan, 150000);
        $this->bukaSesiKas($kasir);

        $this->actingAs($kasir);

        $component = Livewire::test(TagihanPasien::class)
            ->call('selectKunjungan', $kunjungan->id);

        $this->assertNotNull($component->get('invoiceId'),
            'Invoice harus sudah terbentuk walau kunjungan masih dalam_pemeriksaan, selama tanda vital sudah diisi.');

        $invoice = Invoice::where('kunjungan_id', $kunjungan->id)->first();
        $this->assertNotNull($invoice);
        $this->assertSame(150000.0, (float) $invoice->total_tagihan,
            'Tindakan yang sudah tercatat harus ikut terhitung di tagihan.');
    }

    /** @test */
    public function tanpa_tanda_vital_dan_belum_selesai_tagihan_tidak_dibuat(): void
    {
        $kasir     = $this->buatKasir();
        $kunjungan = $this->buatKunjunganDalamPemeriksaan();
        // sengaja TIDAK isi tanda vital
        $this->bukaSesiKas($kasir);

        $this->actingAs($kasir);

        $component = Livewire::test(TagihanPasien::class)
            ->call('selectKunjungan', $kunjungan->id);

        $this->assertNull($component->get('invoiceId'),
            'Tanpa tanda vital & belum selesai, invoice belum boleh dibuat.');
        $this->assertSame(0, Invoice::where('kunjungan_id', $kunjungan->id)->count());
    }

    /** @test */
    public function fetch_tagihan_manual_ditolak_dengan_pesan_tanda_vital_kalau_vital_belum_ada(): void
    {
        $kasir     = $this->buatKasir();
        $kunjungan = $this->buatKunjunganDalamPemeriksaan();
        $this->bukaSesiKas($kasir);

        $this->actingAs($kasir);

        Livewire::test(TagihanPasien::class)
            ->set('kunjunganId', $kunjungan->id)
            ->call('fetchTagihan');

        $this->assertSame(
            'Isi tanda vital terlebih dahulu sebelum dapat membuat tagihan.',
            session('error')
        );
        $this->assertSame(0, Invoice::where('kunjungan_id', $kunjungan->id)->count());
    }

    // ── Pembayaran tetap diblokir sampai SOAP final ──────────────────

    /** @test */
    public function pembayaran_ditolak_kalau_ttv_belum_difinalisasi(): void
    {
        $kasir     = $this->buatKasir();
        $kunjungan = $this->buatKunjunganDalamPemeriksaan();
        $this->isiTandaVital($kunjungan);
        $this->tambahTindakan($kunjungan, 150000);
        $this->bukaSesiKas($kasir);

        $this->actingAs($kasir);

        Livewire::test(TagihanPasien::class)
            ->call('selectKunjungan', $kunjungan->id)
            ->set('metodePembayaran', 'tunai')
            ->set('jumlahTunai', '150000')
            ->call('prosesPembayaran');

        $this->assertStringContainsString('Tanda vital belum difinalisasi', (string) session('error'),
            'Notif harus menyebut finalisasi tanda vital supaya kasir tahu apa yang ditunggu dari perawat.');

        $invoice = Invoice::where('kunjungan_id', $kunjungan->id)->first();
        $this->assertSame('belum_bayar', $invoice->fresh()->status);
        $this->assertSame(0, PembayaranSplit::where('billing_id', $invoice->id)->count(),
            'Tidak boleh ada pembayaran tercatat selama TTV belum final.');
    }

    /** @test */
    public function ttv_sudah_final_false_selama_ttv_belum_dikunci(): void
    {
        $kasir     = $this->buatKasir();
        $kunjungan = $this->buatKunjunganDalamPemeriksaan();
        $this->isiTandaVital($kunjungan);
        $this->bukaSesiKas($kasir);

        $this->actingAs($kasir);

        $component = Livewire::test(TagihanPasien::class)
            ->call('selectKunjungan', $kunjungan->id);

        $this->assertFalse($component->instance()->ttvSudahFinal(),
            'Tombol Bayar harus dikunci (ttvSudahFinal = false) selama TTV belum difinalisasi perawat.');
    }

    /** @test */
    public function pembayaran_berhasil_setelah_dokter_menyelesaikan_pemeriksaan(): void
    {
        $kasir     = $this->buatKasir();
        $kunjungan = $this->buatKunjunganDalamPemeriksaan();
        $this->isiTandaVital($kunjungan);
        $this->tambahTindakan($kunjungan, 150000);
        $this->bukaSesiKas($kasir);

        $this->actingAs($kasir);

        // Kasir menyusun tagihan lebih awal (SOAP belum final).
        $component = Livewire::test(TagihanPasien::class)
            ->call('selectKunjungan', $kunjungan->id);
        $invoiceId = $component->get('invoiceId');
        $this->assertNotNull($invoiceId);

        // Dokter memfinalisasi SOAP & menyelesaikan pemeriksaan -- status
        // 'selesai' hanya bisa dicapai lewat jalur itu (sudah ditest di
        // PemeriksaanAuditFixesTest), di sini cukup simulasikan hasilnya.
        $kunjungan->update(['status' => 'selesai']);

        Livewire::test(TagihanPasien::class)
            ->call('selectKunjungan', $kunjungan->id)
            ->set('metodePembayaran', 'tunai')
            ->set('jumlahTunai', '150000')
            ->call('prosesPembayaran')
            ->assertHasNoErrors();

        $invoice = Invoice::find($invoiceId);
        $this->assertSame('lunas', $invoice->fresh()->status,
            'Begitu pemeriksaan selesai (SOAP final), pembayaran harus bisa diproses.');
    }

    /** @test */
    public function kunjungan_selesai_tanpa_tanda_vital_tetap_bisa_ditagih(): void
    {
        // Jalur lama tidak boleh ikut terkunci oleh syarat tanda vital.
        $kasir     = $this->buatKasir();
        $kunjungan = $this->buatKunjunganDalamPemeriksaan();
        $this->tambahTindakan($kunjungan, 100000);
        $kunjungan->update(['status' => 'selesai']);
        $this->bukaSesiKas($kasir);

        $this->actingAs($kasir);

        $component = Livewire::test(TagihanPasien::class)
            ->call('selectKunjungan', $kunjungan->id);

        $this->assertNotNull($component->get('invoiceId'),
            'Kunjungan yang sudah selesai harus tetap bisa ditagih walau tanda vital tidak diisi.');
    }
}

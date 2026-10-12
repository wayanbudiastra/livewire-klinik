<?php

namespace Tests\Feature;

use App\Livewire\Kasir\TagihanPasien;
use App\Models\Dokter;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Kunjungan;
use App\Models\Pasien;
use App\Models\PembayaranSplit;
use App\Models\Poli;
use App\Models\SesiKas;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Permintaan user: pada pembayaran Non Tunai, Nama Bank & No. Kartu/
 * Referensi dibuat TIDAK wajib (sebelumnya required) -- beberapa metode
 * non-tunai (mis. QRIS/e-wallet) kadang tidak punya nomor referensi yang
 * praktis dicatat kasir saat itu juga.
 *
 * Pakai DatabaseTransactions -- bukan RefreshDatabase.
 */
class PembayaranNonTunaiOpsionalTest extends TestCase
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

    private function buatKunjunganSelesai(): Kunjungan
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
            'tanggal' => now(), 'status' => 'selesai',
        ]);
    }

    private function buatInvoice(Kunjungan $kunjungan, float $total): Invoice
    {
        $invoice = Invoice::create([
            'kunjungan_id' => $kunjungan->id, 'nomor_invoice' => 'INV-' . uniqid(),
            'total_tagihan' => $total, 'total_bayar' => 0, 'sisa' => $total, 'status' => 'belum_bayar',
        ]);
        InvoiceItem::create([
            'billing_id' => $invoice->id, 'jenis' => 'manual', 'nama_item' => 'Biaya Konsultasi',
            'qty' => 1, 'satuan' => 'layanan', 'harga_satuan' => $total, 'diskon_item' => 0, 'subtotal' => $total,
        ]);
        return $invoice;
    }

    private function bukaSesiKas(User $kasir): SesiKas
    {
        return SesiKas::create([
            'user_id' => $kasir->id, 'tanggal' => now()->toDateString(),
            'dibuka_pada' => now(), 'saldo_awal' => 0, 'status' => 'buka',
        ]);
    }

    /** @test */
    public function pembayaran_non_tunai_berhasil_tanpa_isi_nama_bank_dan_nomor_referensi(): void
    {
        $kasir     = $this->buatKasir();
        $kunjungan = $this->buatKunjunganSelesai();
        $invoice   = $this->buatInvoice($kunjungan, 100000);
        $this->bukaSesiKas($kasir);

        $this->actingAs($kasir);

        Livewire::test(TagihanPasien::class)
            ->call('selectKunjungan', $kunjungan->id)
            ->set('metodePembayaran', 'non_tunai')
            // sengaja TIDAK set bankNama / nomorReferensi sama sekali
            ->call('prosesPembayaran')
            ->assertHasNoErrors(['bankNama', 'nomorReferensi']);

        $this->assertSame('lunas', $invoice->fresh()->status);
        $split = PembayaranSplit::where('billing_id', $invoice->id)->first();
        $this->assertNotNull($split);
        $this->assertNull($split->referensi);
    }

    /** @test */
    public function pembayaran_non_tunai_tetap_bisa_isi_nama_bank_dan_referensi_kalau_mau(): void
    {
        $kasir     = $this->buatKasir();
        $kunjungan = $this->buatKunjunganSelesai();
        $invoice   = $this->buatInvoice($kunjungan, 100000);
        $this->bukaSesiKas($kasir);

        $this->actingAs($kasir);

        Livewire::test(TagihanPasien::class)
            ->call('selectKunjungan', $kunjungan->id)
            ->set('metodePembayaran', 'non_tunai')
            ->set('bankNama', 'BCA')
            ->set('nomorReferensi', 'TRX123456')
            ->call('prosesPembayaran')
            ->assertHasNoErrors();

        $this->assertSame('lunas', $invoice->fresh()->status);
        $split = PembayaranSplit::where('billing_id', $invoice->id)->first();
        $this->assertSame('TRX123456', $split->referensi);
    }

    /** @test */
    public function pembayaran_tunai_dan_asuransi_tetap_punya_validasi_wajib_sendiri(): void
    {
        $kasir     = $this->buatKasir();
        $kunjungan = $this->buatKunjunganSelesai();
        $invoice   = $this->buatInvoice($kunjungan, 100000);
        $this->bukaSesiKas($kasir);

        $this->actingAs($kasir);

        // Tunai tanpa jumlahTunai tetap wajib ditolak -- tidak berubah.
        Livewire::test(TagihanPasien::class)
            ->call('selectKunjungan', $kunjungan->id)
            ->set('metodePembayaran', 'tunai')
            ->call('prosesPembayaran')
            ->assertHasErrors(['jumlahTunai']);

        // Asuransi tanpa namaAsuransi tetap wajib ditolak -- tidak berubah.
        Livewire::test(TagihanPasien::class)
            ->call('selectKunjungan', $kunjungan->id)
            ->set('metodePembayaran', 'asuransi')
            ->call('prosesPembayaran')
            ->assertHasErrors(['namaAsuransi']);

        $this->assertSame(0, PembayaranSplit::where('billing_id', $invoice->id)->count());
    }
}

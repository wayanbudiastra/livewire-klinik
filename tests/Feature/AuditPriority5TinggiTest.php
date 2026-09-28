<?php

namespace Tests\Feature;

use App\Models\Asuransi;
use App\Models\Dokter;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Kunjungan;
use App\Models\Pasien;
use App\Models\PiutangAsuransi;
use App\Models\Poli;
use App\Models\SesiKas;
use App\Models\User;
use App\Services\Asuransi\CoverCalculatorService;
use App\Services\Asuransi\PembayaranAsuransiService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Regresi Audit Priority 5 (Asuransi/BPJS coverage calculation), temuan Tinggi.
 *
 * Catatan: CoverCalculatorService/PembayaranAsuransiService BELUM dipanggil
 * dari komponen Livewire atau route mana pun -- jalur pembayaran
 * "asuransi"/"bpjs" yang aktif sekarang (TagihanPasien/SplitPaymentForm)
 * memperlakukan itu sbg metode bayar manual biasa, tidak lewat kalkulasi
 * otomatis ini. Bug di bawah tidak berdampak nyata SEKARANG, tapi akan
 * langsung aktif begitu fitur ini di-wire ke UI kelak.
 *
 * 1. CoverCalculatorService::kumpulkanItem() mendeteksi item obat lewat
 *    kolom 'keterangan' yang TIDAK PERNAH ADA di InvoiceItem (kolomnya
 *    nama_item + jenis) -- kondisinya selalu false, obat/racikan TIDAK
 *    PERNAH masuk hitungan cover asuransi. Diperbaiki pakai kolom jenis.
 * 2. hitungCover() cuma menerapkan plafon_per_kunjungan, TIDAK PERNAH
 *    mengecek plafon_per_tahun. Diperbaiki dgn menghitung akumulasi
 *    PiutangAsuransi (yg belum ditolak) tahun berjalan.
 * 3. PembayaranAsuransiService::prosesPembayaranAsuransi() tidak ada
 *    pengecekan status invoice sama sekali. Diperbaiki dgn lockForUpdate()
 *    + re-check status DI DALAM transaksi, pola sama dgn jalur pembayaran
 *    lain yang sudah dibenahi.
 *
 * Pakai DatabaseTransactions -- bukan RefreshDatabase.
 */
class AuditPriority5TinggiTest extends TestCase
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

    private function buatInvoiceDenganObat(Kunjungan $kunjungan, float $totalObat = 100000): Invoice
    {
        $invoice = Invoice::create([
            'kunjungan_id' => $kunjungan->id, 'nomor_invoice' => 'INV-' . uniqid(),
            'total_tagihan' => $totalObat, 'total_bayar' => 0, 'sisa' => $totalObat, 'status' => 'belum_bayar',
        ]);
        InvoiceItem::create([
            'billing_id' => $invoice->id, 'jenis' => 'obat', 'nama_item' => 'Obat Test',
            'qty' => 1, 'satuan' => 'strip', 'harga_satuan' => $totalObat, 'diskon_item' => 0, 'subtotal' => $totalObat,
        ]);
        return $invoice;
    }

    private function buatAsuransi(float $coverPersen = 50, ?float $plafonTahun = null): Asuransi
    {
        return Asuransi::create([
            'kode' => 'AS-' . uniqid(), 'nama' => 'Asuransi Test ' . uniqid(), 'tipe' => 'swasta', 'is_active' => true,
            'cover_prosedur' => $coverPersen, 'cover_laboratorium' => $coverPersen,
            'cover_radiologi' => $coverPersen, 'cover_peralatan' => $coverPersen,
            'plafon_per_tahun' => $plafonTahun, 'term_pembayaran_hari' => 30,
        ]);
    }

    private function bukaSesiKas(User $kasir): SesiKas
    {
        return SesiKas::create([
            'user_id' => $kasir->id, 'tanggal' => now()->toDateString(),
            'dibuka_pada' => now(), 'saldo_awal' => 0, 'status' => 'buka',
        ]);
    }

    // ── #1: item obat ikut dihitung cover ────────────────────────────

    /** @test */
    public function item_obat_ikut_dihitung_cover_asuransi(): void
    {
        $kunjungan = $this->buatKunjunganSelesai();
        $invoice   = $this->buatInvoiceDenganObat($kunjungan, 100000);
        $asuransi  = $this->buatAsuransi(50); // cover_peralatan 50%

        $hitung = app(CoverCalculatorService::class)->hitungCover($invoice, $asuransi);

        $this->assertSame(50000.0, (float) $hitung['total_cover'],
            'Item obat harus ikut kena cover 50% (sebelumnya selalu 0 krn bug keterangan).');
        $this->assertSame(50000.0, (float) $hitung['total_pasien']);
    }

    // ── #2: plafon per tahun diterapkan ──────────────────────────────

    /** @test */
    public function plafon_per_tahun_membatasi_total_cover(): void
    {
        $kunjungan = $this->buatKunjunganSelesai();
        $pasien    = $kunjungan->pasien;
        $asuransi  = $this->buatAsuransi(50, plafonTahun: 60000);

        // Sudah pakai 40000 dari plafon tahun ini (piutang lain, belum ditolak).
        PiutangAsuransi::create([
            'nomor_piutang' => 'PIT-TEST-' . uniqid(), 'billing_id' => $this->buatInvoiceDenganObat($kunjungan, 1)->id,
            'asuransi_id' => $asuransi->id, 'pasien_id' => $pasien->id,
            'jumlah_piutang' => 40000, 'jumlah_dibayar' => 0, 'sisa_piutang' => 40000,
            'tanggal_piutang' => now(), 'status' => 'tertagih',
        ]);

        // Invoice baru, cover-nya seharusnya 50000 (50% dari 100000) tapi
        // sisa plafon tahun cuma 20000 (60000 - 40000).
        $invoiceBaru = $this->buatInvoiceDenganObat($kunjungan, 100000);
        $hitung = app(CoverCalculatorService::class)->hitungCover($invoiceBaru, $asuransi);

        $this->assertSame(20000.0, (float) $hitung['total_cover'],
            'Cover harus dibatasi ke sisa plafon tahun (20000), bukan 50000.');
        $this->assertSame(80000.0, (float) $hitung['total_pasien']);
    }

    /** @test */
    public function piutang_yang_ditolak_tidak_ikut_mengurangi_plafon_tahun(): void
    {
        $kunjungan = $this->buatKunjunganSelesai();
        $pasien    = $kunjungan->pasien;
        $asuransi  = $this->buatAsuransi(50, plafonTahun: 60000);

        PiutangAsuransi::create([
            'nomor_piutang' => 'PIT-TEST-' . uniqid(), 'billing_id' => $this->buatInvoiceDenganObat($kunjungan, 1)->id,
            'asuransi_id' => $asuransi->id, 'pasien_id' => $pasien->id,
            'jumlah_piutang' => 40000, 'jumlah_dibayar' => 0, 'sisa_piutang' => 40000,
            'tanggal_piutang' => now(), 'status' => 'ditolak',
        ]);

        $invoiceBaru = $this->buatInvoiceDenganObat($kunjungan, 100000);
        $hitung = app(CoverCalculatorService::class)->hitungCover($invoiceBaru, $asuransi);

        $this->assertSame(50000.0, (float) $hitung['total_cover'],
            'Piutang berstatus ditolak tidak boleh ikut mengurangi sisa plafon tahun.');
    }

    // ── #3: prosesPembayaranAsuransi() guard status ──────────────────

    /** @test */
    public function proses_pembayaran_asuransi_ditolak_kalau_invoice_sudah_lunas(): void
    {
        $kasir     = $this->buatKasir();
        $kunjungan = $this->buatKunjunganSelesai();
        $invoice   = $this->buatInvoiceDenganObat($kunjungan, 100000);
        $invoice->update(['status' => 'lunas', 'total_bayar' => 100000, 'sisa' => 0]);
        $sesiKas   = $this->bukaSesiKas($kasir);
        $asuransi  = $this->buatAsuransi(50);

        try {
            app(PembayaranAsuransiService::class)->prosesPembayaranAsuransi(
                $invoice, $asuransi, [['metode' => 'tunai', 'jumlah' => 50000]], $kasir->id, $sesiKas
            );
            $this->fail('Harusnya ditolak krn invoice sudah lunas.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('sudah lunas', $e->getMessage());
        }

        $this->assertSame(0, PiutangAsuransi::where('billing_id', $invoice->id)->count());
    }

    /** @test */
    public function proses_pembayaran_asuransi_normal_tetap_berhasil(): void
    {
        $kasir     = $this->buatKasir();
        $kunjungan = $this->buatKunjunganSelesai();
        $invoice   = $this->buatInvoiceDenganObat($kunjungan, 100000);
        $sesiKas   = $this->bukaSesiKas($kasir);
        $asuransi  = $this->buatAsuransi(50);

        app(PembayaranAsuransiService::class)->prosesPembayaranAsuransi(
            $invoice, $asuransi, [['metode' => 'tunai', 'jumlah' => 50000]], $kasir->id, $sesiKas
        );

        $this->assertSame('lunas', $invoice->fresh()->status);
        $this->assertSame(1, PiutangAsuransi::where('billing_id', $invoice->id)->count());
        $this->assertSame(50000.0, (float) PiutangAsuransi::where('billing_id', $invoice->id)->value('jumlah_piutang'));
    }
}

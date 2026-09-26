<?php

namespace Tests\Feature;

use App\Livewire\Farmasi\ResepFarmasi;
use App\Livewire\Kasir\TagihanPasien;
use App\Models\BahanRacikan;
use App\Models\Barang;
use App\Models\Dokter;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\ItemResep;
use App\Models\Kunjungan;
use App\Models\MutasiStok;
use App\Models\Pasien;
use App\Models\PembayaranSplit;
use App\Models\Poli;
use App\Models\Racikan;
use App\Models\Resep;
use App\Models\SesiKas;
use App\Models\StokOpname;
use App\Models\StokOpnameItem;
use App\Models\User;
use App\Services\Inventory\StokOpnameService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regresi untuk temuan Tinggi dari audit Inventory/Resep Obat/Data Transaksi:
 *
 * 1. [Tinggi] TagihanPasien::prosesPembayaran() cek status invoice
 *    (lunas/dibatalkan) di luar transaksi tanpa lock -- klik dobel/2
 *    request bersamaan bisa sama-sama lolos dan mencatat pembayaran
 *    penuh dua kali utk invoice yang sama. Diperbaiki dengan kunci ulang
 *    + cek ulang status invoice DI DALAM transaksi (lockForUpdate()).
 *    -> app/Livewire/Kasir/TagihanPasien.php
 *
 * 2. [Tinggi] ResepFarmasi::konfirmasi() cek stok cukup sebelum transaksi
 *    lalu potong stok tanpa lock -- 2 resep beda yang pakai obat sama
 *    dikonfirmasi bersamaan bisa lolos cek stok cukup padahal digabung
 *    sudah tidak cukup. Diperbaiki dengan pakai Barang::pastikanCukup()
 *    (pola yang sudah benar dipakai ObatRitelService) di dalam transaksi.
 *    -> app/Livewire/Farmasi/ResepFarmasi.php
 *
 * ...dan temuan Sedang:
 *
 * 3. [Sedang] ResepFarmasi::saveEditItem()/saveEditRacikan() tidak
 *    mengecek is_locked di server (beda dari hapusItem()/hapusRacikan()
 *    yang sudah benar) -- sekarang ditambahkan.
 * 4. [Sedang] ResepFarmasi::batalkanKonfirmasi() mengembalikan stok tapi
 *    tidak pernah mencatat MutasiStok utk pengembaliannya -- sekarang
 *    tiap pengembalian dicatat (tipe penyesuaian_masuk).
 * 5. [Sedang] StokOpnameService::verifikasi() menimpa stok langsung ke
 *    stok_fisik (hasil hitung lama), bukan menerapkan selisihnya ke
 *    stok terkini -- transaksi lain yang terjadi di jeda
 *    input-fisik-ke-verifikasi bisa tertimpa hilang. Sekarang selisih
 *    diterapkan ke stok terkini (yang sudah dikunci row-nya).
 *
 * Pakai DatabaseTransactions -- bukan RefreshDatabase (lihat catatan yang
 * sama di SensitiveActionAuthorizationTest.php).
 */
class TransaksiAuditFixesTest extends TestCase
{
    use DatabaseTransactions;

    // ── Fixtures bersama ───────────────────────────────────────────

    private function buatKasir(): User
    {
        $user = User::create([
            'nama' => 'Kasir Test ' . uniqid(), 'email' => 'kasir-' . uniqid() . '@example.test',
            'password' => Hash::make('password'), 'is_active' => true,
        ]);
        $user->assignRole('kasir');
        return $user;
    }

    private function buatApoteker(): User
    {
        $user = User::create([
            'nama' => 'Apoteker Test ' . uniqid(), 'email' => 'apoteker-' . uniqid() . '@example.test',
            'password' => Hash::make('password'), 'is_active' => true,
        ]);
        $user->assignRole('apoteker');
        return $user;
    }

    private function buatPasien(): Pasien
    {
        return Pasien::create([
            'nomor_rm' => 'RM-' . uniqid(), 'nama' => 'Pasien Test ' . uniqid(), 'tempat_lahir' => 'Denpasar',
            'tanggal_lahir' => '1990-01-01', 'jenis_kelamin' => 'L', 'alamat' => 'Jl. Test', 'telepon' => '08123',
        ]);
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

        return Kunjungan::create([
            'nomor_antrean' => 'W-' . rand(100, 999), 'pasien_id' => $this->buatPasien()->id,
            'dokter_id' => $dokter->id, 'poli_id' => $poli->id,
            'tanggal' => now(), 'status' => 'selesai',
        ]);
    }

    private function buatBarang(int $stok = 10): Barang
    {
        return Barang::create([
            'kode' => 'OBT-' . uniqid(), 'nama' => 'Obat Test ' . uniqid(),
            'satuan' => 'Tablet', 'jenis' => 'obat', 'stok' => $stok,
            'harga_jual' => 5000, 'harga_pokok' => 3000,
        ]);
    }

    // ── #1: Double-payment guard di TagihanPasien ────────────────

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
    public function pembayaran_normal_tetap_berhasil_melunasi_invoice(): void
    {
        $kasir     = $this->buatKasir();
        $kunjungan = $this->buatKunjunganSelesai();
        $invoice   = $this->buatInvoice($kunjungan, 100000);
        $this->bukaSesiKas($kasir);

        $this->actingAs($kasir);

        Livewire::test(TagihanPasien::class)
            ->call('selectKunjungan', $kunjungan->id)
            ->set('metodePembayaran', 'tunai')
            ->set('jumlahTunai', '100000')
            ->call('prosesPembayaran');

        $this->assertSame('lunas', $invoice->fresh()->status);
        $this->assertSame(1, PembayaranSplit::where('billing_id', $invoice->id)->count());
    }

    /** @test */
    public function pembayaran_ditolak_kalau_invoice_sudah_lunas(): void
    {
        $kasir     = $this->buatKasir();
        $kunjungan = $this->buatKunjunganSelesai();
        $invoice   = $this->buatInvoice($kunjungan, 100000);
        $invoice->update(['status' => 'lunas', 'total_bayar' => 100000, 'sisa' => 0]);
        $this->bukaSesiKas($kasir);

        $this->actingAs($kasir);

        // Invoice sudah lunas SEBELUM component dibuat -- computed
        // property invoice() akan baca status lunas ini, jadi
        // prosesPembayaran() harus berhenti tanpa mencatat apa pun.
        Livewire::test(TagihanPasien::class)
            ->call('selectKunjungan', $kunjungan->id)
            ->set('metodePembayaran', 'tunai')
            ->set('jumlahTunai', '100000')
            ->call('prosesPembayaran');

        $this->assertSame(0, PembayaranSplit::where('billing_id', $invoice->id)->count(),
            'Tidak boleh ada PembayaranSplit baru utk invoice yang sudah lunas.');
    }

    /** @test */
    public function pembayaran_tidak_membuat_split_ganda_kalau_status_berubah_lunas_tepat_sebelum_transaksi_kunci(): void
    {
        // Simulasikan race condition: invoice masih 'belum_bayar' saat
        // computed property invoice() dibaca komponen, TAPI sudah jadi
        // 'lunas' di DB pada saat blok transaksi benar-benar jalan
        // (dianalogikan dengan proses lain yang menang duluan). Guard
        // lockForUpdate() + cek ulang di dalam transaksi harus menangkap
        // ini dan TIDAK mencatat pembayaran kedua.
        $kasir     = $this->buatKasir();
        $kunjungan = $this->buatKunjunganSelesai();
        $invoice   = $this->buatInvoice($kunjungan, 100000);
        $this->bukaSesiKas($kasir);

        $this->actingAs($kasir);

        $component = Livewire::test(TagihanPasien::class)
            ->call('selectKunjungan', $kunjungan->id)
            ->set('metodePembayaran', 'tunai')
            ->set('jumlahTunai', '100000');

        // "Proses lain" melunasi invoice ini duluan, tepat sebelum kasir klik Bayar.
        $invoice->update(['status' => 'lunas', 'total_bayar' => 100000, 'sisa' => 0]);
        PembayaranSplit::create([
            'billing_id' => $invoice->id, 'sesi_kas_id' => $this->bukaSesiKas($this->buatKasir())->id,
            'user_id' => $kasir->id, 'metode' => 'tunai', 'jumlah' => 100000, 'tanggal_bayar' => now(),
        ]);

        $component->call('prosesPembayaran');

        $this->assertSame(1, PembayaranSplit::where('billing_id', $invoice->id)->count(),
            'Cuma boleh ada 1 PembayaranSplit (dari "proses lain" tsb) -- prosesPembayaran() tidak boleh menambah yang kedua.');
    }

    /** @test */
    public function pembayaran_ditolak_kalau_invoice_dibatalkan(): void
    {
        $kasir     = $this->buatKasir();
        $kunjungan = $this->buatKunjunganSelesai();
        $invoice   = $this->buatInvoice($kunjungan, 100000);
        $invoice->update(['status' => 'dibatalkan']);
        $this->bukaSesiKas($kasir);

        $this->actingAs($kasir);

        Livewire::test(TagihanPasien::class)
            ->call('selectKunjungan', $kunjungan->id)
            ->set('metodePembayaran', 'tunai')
            ->set('jumlahTunai', '100000')
            ->call('prosesPembayaran');

        $this->assertSame(0, PembayaranSplit::where('billing_id', $invoice->id)->count());
    }

    // ── #2: Stock race condition guard di ResepFarmasi ────────────

    /** @test */
    public function konfirmasi_resep_normal_tetap_memotong_stok_dan_mengunci_resep(): void
    {
        $apoteker = $this->buatApoteker();
        $kunjungan = $this->buatKunjunganSelesai();
        $barang = $this->buatBarang(10);

        $resep = Resep::create(['kunjungan_id' => $kunjungan->id, 'dokter_id' => $kunjungan->dokter_id, 'status' => 'menunggu']);
        ItemResep::create(['resep_id' => $resep->id, 'barang_id' => $barang->id, 'jumlah' => 3, 'aturan_pakai' => '3x1']);

        $this->actingAs($apoteker);

        Livewire::test(ResepFarmasi::class)->call('konfirmasi', $resep->id);

        $this->assertSame(7, $barang->fresh()->stok);
        $this->assertTrue($resep->fresh()->is_locked);
        $this->assertSame('siap', $resep->fresh()->status);
        $this->assertSame(1, MutasiStok::where('referensi_tipe', 'resep')->where('referensi_id', $resep->id)->count());
    }

    /** @test */
    public function konfirmasi_resep_dengan_racikan_memotong_stok_bahan(): void
    {
        $apoteker  = $this->buatApoteker();
        $kunjungan = $this->buatKunjunganSelesai();
        $bahan     = $this->buatBarang(20);

        $resep   = Resep::create(['kunjungan_id' => $kunjungan->id, 'dokter_id' => $kunjungan->dokter_id, 'status' => 'menunggu']);
        $racikan = Racikan::create(['resep_id' => $resep->id, 'nama_racikan' => 'Puyer Batuk', 'jumlah_sediaan' => 10]);
        BahanRacikan::create(['racikan_id' => $racikan->id, 'barang_id' => $bahan->id, 'jumlah' => 15, 'satuan' => 'tablet']);

        $this->actingAs($apoteker);

        Livewire::test(ResepFarmasi::class)->call('konfirmasi', $resep->id);

        $this->assertSame(5, $bahan->fresh()->stok);
        $this->assertTrue($resep->fresh()->is_locked);
    }

    /** @test */
    public function konfirmasi_resep_dengan_stok_tidak_cukup_ditolak_dan_tidak_mengubah_apa_pun(): void
    {
        $apoteker  = $this->buatApoteker();
        $kunjungan = $this->buatKunjunganSelesai();
        $barang    = $this->buatBarang(2); // cuma 2, diresepkan 5

        $resep = Resep::create(['kunjungan_id' => $kunjungan->id, 'dokter_id' => $kunjungan->dokter_id, 'status' => 'menunggu']);
        ItemResep::create(['resep_id' => $resep->id, 'barang_id' => $barang->id, 'jumlah' => 5, 'aturan_pakai' => '3x1']);

        $this->actingAs($apoteker);

        Livewire::test(ResepFarmasi::class)->call('konfirmasi', $resep->id);

        $this->assertSame(2, $barang->fresh()->stok, 'Stok tidak boleh berubah sama sekali kalau konfirmasi gagal.');
        $this->assertFalse($resep->fresh()->is_locked);
        $this->assertSame(0, MutasiStok::where('referensi_tipe', 'resep')->where('referensi_id', $resep->id)->count());
    }

    /** @test */
    public function konfirmasi_resep_yang_sudah_terkunci_di_db_ditolak_walau_objek_lama_belum_tahu(): void
    {
        // Simulasikan race condition: resep sudah dikonfirmasi (is_locked=true)
        // oleh "proses lain" TEPAT SETELAH request ini membaca datanya tapi
        // SEBELUM transaksi benar-benar berjalan. Guard lockForUpdate() +
        // cek ulang status di dalam transaksi harus menangkap ini.
        $apoteker  = $this->buatApoteker();
        $kunjungan = $this->buatKunjunganSelesai();
        $barang    = $this->buatBarang(10);

        $resep = Resep::create(['kunjungan_id' => $kunjungan->id, 'dokter_id' => $kunjungan->dokter_id, 'status' => 'menunggu']);
        ItemResep::create(['resep_id' => $resep->id, 'barang_id' => $barang->id, 'jumlah' => 3, 'aturan_pakai' => '3x1']);

        $this->actingAs($apoteker);

        $component = Livewire::test(ResepFarmasi::class);

        // "Proses lain" mengunci resep ini duluan (tanpa lewat komponen ini
        // -- cukup ubah statusnya langsung di DB utk mensimulasikan hasil
        // akhir race condition-nya).
        $resep->update(['is_locked' => true, 'locked_by' => $apoteker->id, 'locked_at' => now(), 'status' => 'siap']);

        $component->call('konfirmasi', $resep->id);

        $this->assertSame(10, $barang->fresh()->stok,
            'Stok tidak boleh dipotong sama sekali oleh percobaan konfirmasi kedua ini.');
        $this->assertSame(0, MutasiStok::where('referensi_tipe', 'resep')->where('referensi_id', $resep->id)->count(),
            'Percobaan konfirmasi kedua ini tidak boleh membuat mutasi stok apa pun.');
    }

    // ── #3: saveEditItem()/saveEditRacikan() harus cek is_locked ────

    /** @test */
    public function edit_item_resep_ditolak_kalau_resep_sudah_terkunci(): void
    {
        $apoteker  = $this->buatApoteker();
        $kunjungan = $this->buatKunjunganSelesai();
        $barang    = $this->buatBarang(10);

        $resep = Resep::create([
            'kunjungan_id' => $kunjungan->id, 'dokter_id' => $kunjungan->dokter_id,
            'status' => 'siap', 'is_locked' => true, 'locked_by' => $apoteker->id, 'locked_at' => now(),
        ]);
        $item = ItemResep::create(['resep_id' => $resep->id, 'barang_id' => $barang->id, 'jumlah' => 3, 'aturan_pakai' => '3x1']);

        $this->actingAs($apoteker);

        Livewire::test(ResepFarmasi::class)
            ->set('editingItemId', $item->id)
            ->set('editJumlah', 9)
            ->set('editSigna', '4x1')
            ->call('saveEditItem');

        $this->assertSame(3, $item->fresh()->jumlah, 'Jumlah item resep yang sudah terkunci tidak boleh berubah.');
    }

    /** @test */
    public function edit_racikan_ditolak_kalau_resep_sudah_terkunci(): void
    {
        $apoteker  = $this->buatApoteker();
        $kunjungan = $this->buatKunjunganSelesai();
        $bahan     = $this->buatBarang(20);

        $resep = Resep::create([
            'kunjungan_id' => $kunjungan->id, 'dokter_id' => $kunjungan->dokter_id,
            'status' => 'siap', 'is_locked' => true, 'locked_by' => $apoteker->id, 'locked_at' => now(),
        ]);
        $racikan = Racikan::create(['resep_id' => $resep->id, 'nama_racikan' => 'Puyer Batuk', 'jumlah_sediaan' => 10]);
        BahanRacikan::create(['racikan_id' => $racikan->id, 'barang_id' => $bahan->id, 'jumlah' => 15, 'satuan' => 'tablet']);

        $this->actingAs($apoteker);

        Livewire::test(ResepFarmasi::class)
            ->set('editingRacikanId', $racikan->id)
            ->set('editJumlahSediaan', 25)
            ->set('editAturanPakai', 'baru')
            ->call('saveEditRacikan');

        $this->assertSame(10, $racikan->fresh()->jumlah_sediaan, 'Racikan pada resep yang sudah terkunci tidak boleh berubah.');
    }

    /** @test */
    public function edit_item_resep_tetap_bisa_kalau_belum_terkunci(): void
    {
        $apoteker  = $this->buatApoteker();
        $kunjungan = $this->buatKunjunganSelesai();
        $barang    = $this->buatBarang(10);

        $resep = Resep::create(['kunjungan_id' => $kunjungan->id, 'dokter_id' => $kunjungan->dokter_id, 'status' => 'menunggu']);
        $item  = ItemResep::create(['resep_id' => $resep->id, 'barang_id' => $barang->id, 'jumlah' => 3, 'aturan_pakai' => '3x1']);

        $this->actingAs($apoteker);

        Livewire::test(ResepFarmasi::class)
            ->set('editingItemId', $item->id)
            ->set('editJumlah', 6)
            ->set('editSigna', '2x1')
            ->call('saveEditItem');

        $this->assertSame(6, $item->fresh()->jumlah);
    }

    // ── #4: batalkanKonfirmasi() harus mencatat MutasiStok ─────────

    /** @test */
    public function batalkan_konfirmasi_mengembalikan_stok_dan_mencatat_mutasi_stok(): void
    {
        $apoteker  = $this->buatApoteker();
        $kunjungan = $this->buatKunjunganSelesai();
        $barang    = $this->buatBarang(10);

        $resep = Resep::create(['kunjungan_id' => $kunjungan->id, 'dokter_id' => $kunjungan->dokter_id, 'status' => 'menunggu']);
        ItemResep::create(['resep_id' => $resep->id, 'barang_id' => $barang->id, 'jumlah' => 3, 'aturan_pakai' => '3x1']);

        $this->actingAs($apoteker);

        $component = Livewire::test(ResepFarmasi::class);
        $component->call('konfirmasi', $resep->id);
        $this->assertSame(7, $barang->fresh()->stok);

        $component->call('batalkanKonfirmasi', $resep->id);

        $this->assertSame(10, $barang->fresh()->stok, 'Stok harus kembali penuh setelah konfirmasi dibatalkan.');
        $this->assertFalse($resep->fresh()->is_locked);

        $mutasiPengembalian = MutasiStok::where('referensi_tipe', 'resep')
            ->where('referensi_id', $resep->id)
            ->where('tipe', 'penyesuaian_masuk')
            ->first();

        $this->assertNotNull($mutasiPengembalian, 'Harus ada MutasiStok yang mencatat pengembalian stok saat batal konfirmasi.');
        $this->assertSame(3, (int) $mutasiPengembalian->jumlah);
        $this->assertSame(7, (int) $mutasiPengembalian->stok_sebelum);
        $this->assertSame(10, (int) $mutasiPengembalian->stok_sesudah);

        // Total mutasi utk resep ini: 1 keluar_resep (konfirmasi) + 1 penyesuaian_masuk (batal).
        $this->assertSame(2, MutasiStok::where('referensi_tipe', 'resep')->where('referensi_id', $resep->id)->count());
    }

    // ── #5: StokOpnameService::verifikasi() menerapkan selisih, bukan menimpa ──

    /** @test */
    public function verifikasi_opname_menerapkan_selisih_ke_stok_terkini_bukan_menimpa_stok_fisik_lama(): void
    {
        $admin  = User::where('email', 'admin@emr.app')->firstOrFail();
        $barang = $this->buatBarang(50); // stok_sistem saat opname dibuat = 50

        $service = app(StokOpnameService::class);

        $opname = $service->buatOpname([
            'tanggal_opname' => now()->toDateString(),
        ], $admin->id);

        $item = StokOpnameItem::where('stok_opname_id', $opname->id)->where('barang_id', $barang->id)->firstOrFail();

        // Hitung fisik: ketemu 45 (selisih -5 dari stok_sistem=50).
        $service->inputStokFisik($item, 45);
        $service->submitUntukVerifikasi($opname->fresh());

        // Selama menunggu verifikasi, ada transaksi LAIN yang menambah stok
        // 20 (mis. barang baru datang dari pembelian) -- stok terkini
        // sekarang 70, BUKAN 50 lagi.
        $barang->increment('stok', 20);
        $this->assertSame(70, $barang->fresh()->stok);

        $service->verifikasi($opname->fresh(), $admin->id);

        // Selisih opname (-5) harus diterapkan ke stok TERKINI (70), bukan
        // menimpa langsung ke stok_fisik (45) yang akan menghilangkan
        // penambahan 20 unit yang masuk di tengah jalan.
        $this->assertSame(65, $barang->fresh()->stok,
            'Stok akhir harus 70 (terkini) - 5 (selisih opname) = 65, bukan ditimpa jadi 45.');
    }
}

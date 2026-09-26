<?php

namespace Tests\Feature;

use App\Livewire\Akuntansi\JurnalManualTable;
use App\Models\Akuntansi\ChartOfAccount;
use App\Models\Akuntansi\JurnalPending;
use App\Models\Akuntansi\JurnalUmum;
use App\Models\User;
use App\Services\Akuntansi\JurnalManualService;
use App\Services\Akuntansi\JurnalService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regresi Audit Priority 3 (Akuntansi/Jurnal otomatis), temuan Sedang & Rendah:
 *
 * [Sedang] JurnalManualTable::batalkan() sebelumnya tidak punya authorize()
 * sendiri -- cuma andalkan gate route permission:akuntansi.jurnal.view yang
 * lebih longgar dari akuntansi.jurnal_manual.create yang seharusnya utk
 * aksi tulis spt ini. Ditambahkan sbg lapis kedua (defense in depth).
 *
 * [Rendah]
 * 1. JurnalUmum::generateNomor() sekarang lockForUpdate(), pola sama dgn
 *    ReturResep/ReturGr/Deposit/Penagihan.
 * 2. JurnalService::abaikan() sekarang dikunci ulang DI DALAM transaksi --
 *    sebelumnya 2 klik "Abaikan" bersamaan pada baris yang sama bisa
 *    menambahkan teks "[Diabaikan: ...]" dobel ke kolom keterangan.
 *
 * [Tinggi] JurnalService::reversal() sebelumnya tidak menandai
 * sumber+tipe yang sudah direversal, sehingga rentan reversal dobel via
 * jalur Jurnal Manual (JurnalManualTable::batalkan() tidak py lock/guard
 * sendiri). Diperbaiki dgn cek dulu (lockForUpdate()) apakah baris
 * 'pembatalan_<tipe>' utk sumber ini sudah ada -- kalau ya, tolak dgn
 * \DomainException, mencegah reversal kedua yang bikin buku besar tidak
 * balance.
 *
 * Pakai DatabaseTransactions -- bukan RefreshDatabase.
 */
class AuditPriority3SedangRendahTest extends TestCase
{
    use DatabaseTransactions;

    private function buatUserDenganRole(string $role): User
    {
        $user = User::create([
            'nama' => ucfirst($role) . ' Test ' . uniqid(),
            'email' => strtolower(str_replace(' ', '', $role)) . '-' . uniqid() . '@example.test',
            'password' => Hash::make('password'), 'is_active' => true,
        ]);
        $user->assignRole($role);
        return $user;
    }

    private function buatAkun(string $kode, string $golongan = 'biaya', string $tipeNormal = 'debit'): ChartOfAccount
    {
        return ChartOfAccount::firstOrCreate(
            ['kode' => $kode],
            ['nama' => "Akun {$kode}", 'golongan' => $golongan, 'tipe_normal' => $tipeNormal, 'is_aktif' => true]
        );
    }

    // ── Sedang: JurnalManualTable::batalkan() authorize() ────────────────

    /** @test */
    public function role_tanpa_jurnal_manual_create_ditolak_batalkan_jurnal_manual(): void
    {
        $this->buatAkun('5-9001', 'biaya', 'debit');
        $this->buatAkun('1-9002', 'aset', 'debit');

        $keuangan = $this->buatUserDenganRole('keuangan');
        $jm = app(JurnalManualService::class)->buat([
            'tanggal' => now()->toDateString(), 'kategori' => null,
            'kode_akun_debit' => '5-9001', 'kode_akun_kredit' => '1-9002',
            'nominal' => 100000, 'keterangan' => 'Test jurnal manual utk audit',
            'dokumen_pendukung' => null,
        ], $keuangan->id);

        // 'dokter' tidak punya permission akuntansi apa pun.
        $dokter = $this->buatUserDenganRole('dokter');
        $this->actingAs($dokter);

        Livewire::test(JurnalManualTable::class)
            ->call('batalkan', $jm->id)
            ->assertForbidden();
    }

    /** @test */
    public function akuntan_tetap_bisa_batalkan_jurnal_manual(): void
    {
        $this->buatAkun('5-9003', 'biaya', 'debit');
        $this->buatAkun('1-9004', 'aset', 'debit');

        $keuangan = $this->buatUserDenganRole('keuangan');
        $jm = app(JurnalManualService::class)->buat([
            'tanggal' => now()->toDateString(), 'kategori' => null,
            'kode_akun_debit' => '5-9003', 'kode_akun_kredit' => '1-9004',
            'nominal' => 50000, 'keterangan' => 'Test jurnal manual utk audit 2',
            'dokumen_pendukung' => null,
        ], $keuangan->id);

        $akuntan = $this->buatUserDenganRole('akuntan');
        $this->actingAs($akuntan);

        Livewire::test(JurnalManualTable::class)
            ->call('batalkan', $jm->id)
            ->assertOk();
    }

    // ── Rendah #1: JurnalUmum::generateNomor() -- regresi setelah lock ──

    /** @test */
    public function posting_beberapa_baris_jurnal_tetap_menghasilkan_nomor_jurnal_urut_setelah_ditambah_lock(): void
    {
        $this->buatAkun('5-9101', 'biaya', 'debit');
        $this->buatAkun('1-9102', 'aset', 'debit');
        $userId = $this->buatUserDenganRole('akuntan')->id;

        $jurnal = app(JurnalService::class);
        $p1 = $jurnal->catat('test', 1, 'test_tipe', now(), '5-9101', '1-9102', 10000, 'Baris 1');
        $p2 = $jurnal->catat('test', 2, 'test_tipe', now(), '5-9101', '1-9102', 20000, 'Baris 2');

        $posted = $jurnal->posting([$p1->id, $p2->id], $userId);

        $this->assertCount(2, $posted);
        $nomorList = collect($posted)->pluck('nomor_jurnal')->values()->toArray();
        $this->assertNotSame($nomorList[0], $nomorList[1], 'Nomor jurnal tidak boleh sama.');
        $this->assertSame(2, JurnalUmum::whereIn('id', collect($posted)->pluck('id'))->count());
    }

    // ── Rendah #2: JurnalService::abaikan() -- lock & idempotency ───────

    /** @test */
    public function abaikan_dua_kali_pada_baris_yang_sama_tidak_menduplikasi_teks_keterangan(): void
    {
        $this->buatAkun('5-9201', 'biaya', 'debit');
        $this->buatAkun('1-9202', 'aset', 'debit');

        $jurnal = app(JurnalService::class);
        $pending = $jurnal->catat('test', 3, 'test_tipe', now(), '5-9201', '1-9202', 15000, 'Baris test abaikan');

        $jurnal->abaikan($pending->id, 'Alasan pertama');

        try {
            $jurnal->abaikan($pending->id, 'Alasan kedua (percobaan dobel)');
            $this->fail('Panggilan abaikan() kedua harusnya ditolak (status sudah bukan pending).');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('bukan berstatus pending', $e->getMessage());
        }

        $fresh = JurnalPending::find($pending->id);
        $this->assertSame('diabaikan', $fresh->status);
        $this->assertSame(1, substr_count($fresh->keterangan, '[Diabaikan:'),
            'Teks [Diabaikan: ...] cuma boleh muncul 1x, bukan dobel.');
    }

    // ── Tinggi: JurnalService::reversal() tidak boleh direversal 2x ─────

    /** @test */
    public function batalkan_jurnal_manual_kedua_kali_ditolak_dan_tidak_membuat_reversal_dobel(): void
    {
        $this->buatAkun('5-9301', 'biaya', 'debit');
        $this->buatAkun('1-9302', 'aset', 'debit');
        $userId = $this->buatUserDenganRole('akuntan')->id;

        $jm = app(JurnalManualService::class)->buat([
            'tanggal' => now()->toDateString(), 'kategori' => null,
            'kode_akun_debit' => '5-9301', 'kode_akun_kredit' => '1-9302',
            'nominal' => 75000, 'keterangan' => 'Test reversal dobel',
            'dokumen_pendukung' => null,
        ], $userId);

        $pending = JurnalPending::where('sumber_tipe', 'jurnal_manual')->where('sumber_id', $jm->id)->firstOrFail();
        app(JurnalService::class)->posting([$pending->id], $userId);

        $service = app(JurnalManualService::class);
        $service->batalkan($jm, $userId); // pertama kali -- berhasil normal

        try {
            $service->batalkan($jm, $userId); // kedua kali (mis. double-click) -- harus ditolak
            $this->fail('Panggilan batalkan() kedua harusnya ditolak (sudah pernah direversal).');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('sudah pernah', $e->getMessage());
        }

        $this->assertSame(1, JurnalPending::where('sumber_tipe', 'jurnal_manual')
            ->where('sumber_id', $jm->id)
            ->where('tipe_transaksi', 'pembatalan_jurnal_manual')
            ->count(), 'Cuma boleh ada 1 baris reversal, bukan dobel.');
        $this->assertSame(1, JurnalUmum::where('sumber_tipe', 'jurnal_manual')
            ->where('sumber_id', $jm->id)
            ->where('kode_akun_debit', '1-9302') // dibalik dari akun kredit asli
            ->count(), 'Cuma boleh ada 1 entri reversal di Jurnal Umum.');
    }
}

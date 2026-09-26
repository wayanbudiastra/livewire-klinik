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
 * (Temuan Tinggi -- JurnalService::reversal() tidak menandai baris asli
 * sbg sudah direversal, sehingga rentan reversal dobel via jalur Jurnal
 * Manual -- SENGAJA belum diperbaiki di sesi ini, menunggu konfirmasi user.)
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
}

<?php

namespace Tests\Feature;

use App\Livewire\Pemeriksaan\CetakSurat;
use App\Livewire\Pemeriksaan\Penunjang;
use App\Livewire\Pemeriksaan\ResepObat;
use App\Livewire\Pemeriksaan\SoapNote as SoapNoteLivewire;
use App\Models\Dokter;
use App\Models\Kunjungan;
use App\Models\Pasien;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regresi untuk temuan Kritikal dari audit modul Pemeriksaan SOAP Dokter:
 *
 * [Kritikal] Hampir seluruh komponen Livewire di modul Pemeriksaan
 * (SoapNote, ResepObat, Penunjang, CetakSurat::buka()/cetak()) TIDAK ADA
 * pengecekan otorisasi server-side sama sekali -- halaman /pemeriksaan
 * cuma digerbang permission asesmen.view yang dimiliki DOKTER MAUPUN
 * PERAWAT, padahal ada permission khusus (soap.view/create/edit,
 * resep.create, penunjang.create, surat.cetak) yang seharusnya membatasi
 * aksi-aksi ini ke dokter saja. Perawat sebelumnya bisa membuka tab
 * Medical Notes dan menulis/memfinalisasi SOAP Note, meresepkan obat
 * (tersimpan dengan dokter_id NULL), meminta lab/radiologi, dan
 * menerbitkan surat keterangan.
 *
 * Diperbaiki dengan menambah authorize() di titik yang tepat:
 * - SoapNote::mount() -> soap.view; SoapNote::doSimpan() (dipakai
 *   simpan()/finalisasi()/simpanRevisi()) -> soap.create/soap.edit.
 * - ResepObat::mount() (baru dibuat) -> resep.create.
 * - Penunjang::mount() (baru dibuat) -> penunjang.create.
 * - CetakSurat::buka() & CetakSurat::cetak() -> surat.cetak.
 * - Tab Medical Notes/Penunjang/Medication disembunyikan di
 *   detail-pemeriksaan.blade.php dari user yang tidak punya permission
 *   terkait (defense in depth, bukan pengganti authorize() di atas).
 *
 * Pakai DatabaseTransactions -- bukan RefreshDatabase (lihat catatan yang
 * sama di SensitiveActionAuthorizationTest.php).
 */
class PemeriksaanAuditFixesTest extends TestCase
{
    use DatabaseTransactions;

    private function buatKunjungan(): array
    {
        $dokterUser = User::create([
            'nama' => 'Dr. Test ' . uniqid(), 'email' => 'dokter-' . uniqid() . '@example.test',
            'password' => Hash::make('password'), 'is_active' => true,
        ]);
        $dokterUser->assignRole('dokter');
        $dokter = Dokter::create(['user_id' => $dokterUser->id]);

        $pasien = Pasien::create([
            'nomor_rm' => 'RM-' . uniqid(), 'nama' => 'Pasien Test', 'tempat_lahir' => 'Denpasar',
            'tanggal_lahir' => '1990-01-01', 'jenis_kelamin' => 'L', 'alamat' => 'Jl. Test', 'telepon' => '08123',
        ]);

        $kunjungan = Kunjungan::create([
            'nomor_antrean' => 'A-' . uniqid(), 'pasien_id' => $pasien->id, 'dokter_id' => $dokter->id,
            'status' => 'dalam_pemeriksaan', 'tanggal' => now(),
        ]);

        return compact('dokterUser', 'dokter', 'pasien', 'kunjungan');
    }

    private function buatPerawat(): User
    {
        $perawat = User::create([
            'nama' => 'Perawat Test ' . uniqid(), 'email' => 'perawat-' . uniqid() . '@example.test',
            'password' => Hash::make('password'), 'is_active' => true,
        ]);
        $perawat->assignRole('perawat');
        return $perawat;
    }

    // ── SoapNote ───────────────────────────────────────────────────

    /** @test */
    public function perawat_tidak_bisa_membuka_tab_soap_note(): void
    {
        $ctx = $this->buatKunjungan();
        $this->actingAs($this->buatPerawat());

        Livewire::test(SoapNoteLivewire::class, ['kunjunganId' => $ctx['kunjungan']->id])
            ->assertForbidden();
    }

    /** @test */
    public function dokter_tetap_bisa_membuka_dan_menyimpan_soap_note(): void
    {
        $ctx = $this->buatKunjungan();
        $this->actingAs($ctx['dokterUser']);

        Livewire::test(SoapNoteLivewire::class, ['kunjunganId' => $ctx['kunjungan']->id])
            ->set('sChiefComplaint', 'Demam')
            ->call('addDiagnosis', 'A09', 'Diarrhoea')
            ->call('simpan')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('soap_note', ['kunjungan_id' => $ctx['kunjungan']->id]);
    }

    // ── ResepObat ──────────────────────────────────────────────────

    /** @test */
    public function perawat_tidak_bisa_membuka_tab_resep_obat(): void
    {
        $ctx = $this->buatKunjungan();
        $this->actingAs($this->buatPerawat());

        Livewire::test(ResepObat::class, ['kunjunganId' => $ctx['kunjungan']->id])
            ->assertForbidden();
    }

    /** @test */
    public function dokter_tetap_bisa_membuka_tab_resep_obat(): void
    {
        $ctx = $this->buatKunjungan();
        $this->actingAs($ctx['dokterUser']);

        Livewire::test(ResepObat::class, ['kunjunganId' => $ctx['kunjungan']->id])
            ->assertOk();
    }

    // ── Penunjang ──────────────────────────────────────────────────

    /** @test */
    public function perawat_tidak_bisa_membuka_tab_penunjang(): void
    {
        $ctx = $this->buatKunjungan();
        $this->actingAs($this->buatPerawat());

        Livewire::test(Penunjang::class, ['kunjunganId' => $ctx['kunjungan']->id])
            ->assertForbidden();
    }

    /** @test */
    public function dokter_tetap_bisa_membuka_tab_penunjang(): void
    {
        $ctx = $this->buatKunjungan();
        $this->actingAs($ctx['dokterUser']);

        Livewire::test(Penunjang::class, ['kunjunganId' => $ctx['kunjungan']->id])
            ->assertOk();
    }

    // ── CetakSurat ─────────────────────────────────────────────────

    /** @test */
    public function perawat_tidak_bisa_membuka_form_cetak_surat(): void
    {
        $ctx = $this->buatKunjungan();
        $this->actingAs($this->buatPerawat());

        Livewire::test(CetakSurat::class, ['kunjunganId' => $ctx['kunjungan']->id])
            ->call('buka', 'keterangan_sehat')
            ->assertForbidden();
    }

    /** @test */
    public function dokter_tetap_bisa_membuka_form_cetak_surat(): void
    {
        $ctx = $this->buatKunjungan();
        $this->actingAs($ctx['dokterUser']);

        Livewire::test(CetakSurat::class, ['kunjunganId' => $ctx['kunjungan']->id])
            ->call('buka', 'keterangan_sehat')
            ->assertSet('showModal', true);
    }

    // ── Tab visibility di DetailPemeriksaan ──────────────────────────

    /** @test */
    public function perawat_tidak_melihat_tab_medical_notes_penunjang_medication(): void
    {
        $ctx = $this->buatKunjungan();
        $this->actingAs($this->buatPerawat());

        $response = $this->get(route('pemeriksaan.index', ['tab' => 'detail', 'kunjunganId' => $ctx['kunjungan']->id]));

        $response->assertDontSee('Medical Notes');
        $response->assertDontSee('Penunjang Medis');
        $response->assertDontSee('Medication');
    }

    /** @test */
    public function dokter_tetap_melihat_semua_tab(): void
    {
        $ctx = $this->buatKunjungan();
        $this->actingAs($ctx['dokterUser']);

        $response = $this->get(route('pemeriksaan.index', ['tab' => 'detail', 'kunjunganId' => $ctx['kunjungan']->id]));

        $response->assertSee('Medical Notes');
        $response->assertSee('Penunjang Medis');
        $response->assertSee('Medication');
    }
}

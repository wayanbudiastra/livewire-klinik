<?php

namespace Tests\Feature;

use App\Livewire\Pemeriksaan\CetakSurat;
use App\Models\Dokter;
use App\Models\Kunjungan;
use App\Models\Pasien;
use App\Models\SoapNote;
use App\Models\SuratKeterangan;
use App\Models\TujuanRujukan;
use App\Models\User;
use App\Services\Pemeriksaan\SuratKeteranganService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * Regresi untuk 4 permintaan fitur baru di modul Cetak Surat:
 * 1. Bilingual (EN) untuk Keterangan Sehat & Sakit.
 * 2. Kolom Buta Warna (Colour Blindness) di Keterangan Sehat.
 * 3. Edit surat yang sudah terbit (pola sama dgn revisi SOAP -- dokter-only,
 *    wajib alasan, audit log, nomor_surat tidak berubah, boleh kapan pun
 *    termasuk setelah kunjungan Selesai).
 * 4. Dropdown Tujuan Rujukan dari master data, create-on-the-fly.
 *
 * Pakai DatabaseTransactions -- lihat catatan yang sama di
 * SensitiveActionAuthorizationTest.php soal kenapa bukan RefreshDatabase.
 */
class SuratKeteranganFiturBaruTest extends TestCase
{
    use DatabaseTransactions;

    private function buatKunjungan(string $status = 'dalam_pemeriksaan'): array
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
            'status' => $status, 'tanggal' => now(),
        ]);

        SoapNote::create([
            'kunjungan_id' => $kunjungan->id,
            'icd_codes' => [['kode' => 'A09', 'nama' => 'Diarrhoea', 'is_primary' => true]],
            'is_final' => true,
        ]);

        $this->actingAs($dokterUser);

        return compact('dokterUser', 'dokter', 'pasien', 'kunjungan');
    }

    /** @test */
    public function keterangan_sehat_bisa_dicetak_dalam_bahasa_inggris_dengan_buta_warna(): void
    {
        $ctx = $this->buatKunjungan();

        Livewire::test(CetakSurat::class, ['kunjunganId' => $ctx['kunjungan']->id])
            ->call('buka', 'keterangan_sehat')
            ->set('dokterId', $ctx['dokter']->id)
            ->set('bahasa', 'en')
            ->set('butaWarna', 'normal')
            ->call('cetak')
            ->assertOk();

        $surat = SuratKeterangan::where('kunjungan_id', $ctx['kunjungan']->id)->where('tipe', 'keterangan_sehat')->first();
        $this->assertNotNull($surat);
        $this->assertSame('en', $surat->data['bahasa']);
        $this->assertSame('normal', $surat->data['buta_warna']);
    }

    /** @test */
    public function keterangan_sakit_bisa_dicetak_dalam_bahasa_inggris(): void
    {
        $ctx = $this->buatKunjungan();

        Livewire::test(CetakSurat::class, ['kunjunganId' => $ctx['kunjungan']->id])
            ->call('buka', 'keterangan_sakit')
            ->set('dokterId', $ctx['dokter']->id)
            ->set('bahasa', 'en')
            ->set('tanggalMulai', now()->toDateString())
            ->set('lamaHari', 3)
            ->call('cetak')
            ->assertOk();

        $surat = SuratKeterangan::where('kunjungan_id', $ctx['kunjungan']->id)->where('tipe', 'keterangan_sakit')->first();
        $this->assertSame('en', $surat->data['bahasa']);
    }

    /** @test */
    public function rujukan_membuat_tujuan_baru_otomatis_dari_combobox(): void
    {
        $ctx = $this->buatKunjungan();
        $this->assertSame(0, TujuanRujukan::count());

        Livewire::test(CetakSurat::class, ['kunjunganId' => $ctx['kunjungan']->id])
            ->call('buka', 'rujukan')
            ->set('dokterId', $ctx['dokter']->id)
            ->set('tujuanFasilitas', 'RSUP Sanglah Denpasar')
            ->set('indikasi', 'Perlu pemeriksaan lanjutan')
            ->call('cetak')
            ->assertOk();

        $this->assertSame(1, TujuanRujukan::count());
        $tujuan = TujuanRujukan::first();
        $this->assertSame('RSUP Sanglah Denpasar', $tujuan->nama);

        $surat = SuratKeterangan::where('kunjungan_id', $ctx['kunjungan']->id)->first();
        $this->assertSame($tujuan->id, $surat->tujuan_rujukan_id);
        $this->assertSame('RSUP Sanglah Denpasar', $surat->data['tujuan_fasilitas']);
    }

    /** @test */
    public function rujukan_reuse_tujuan_yang_sudah_ada_case_insensitive(): void
    {
        TujuanRujukan::create(['nama' => 'RSUP Sanglah Denpasar']);
        $ctx = $this->buatKunjungan();

        Livewire::test(CetakSurat::class, ['kunjunganId' => $ctx['kunjungan']->id])
            ->call('buka', 'rujukan')
            ->set('dokterId', $ctx['dokter']->id)
            ->set('tujuanFasilitas', 'rsup sanglah denpasar') // huruf kecil semua
            ->set('indikasi', 'Kontrol lanjutan')
            ->call('cetak')
            ->assertOk();

        // Tidak nambah baris baru -- reuse yang sudah ada.
        $this->assertSame(1, TujuanRujukan::count());
    }

    /** @test */
    public function dokter_bisa_edit_surat_yang_sudah_terbit_dengan_alasan(): void
    {
        $ctx = $this->buatKunjungan();
        $surat = app(SuratKeteranganService::class)->simpanSakit($ctx['kunjungan'], [
            'dokter_id' => $ctx['dokter']->id, 'tanggal_mulai' => now()->toDateString(), 'lama_hari' => 2,
        ], $ctx['dokterUser']->id);
        $nomorAsli = $surat->nomor_surat;
        $dicetakPadaAsli = $surat->dicetak_pada;

        Livewire::test(CetakSurat::class, ['kunjunganId' => $ctx['kunjungan']->id])
            ->call('mulaiEdit', $surat->id)
            ->assertSet('showRevisiPrompt', true)
            ->set('alasanRevisi', 'Salah jumlah hari istirahat, seharusnya 5 hari')
            ->call('konfirmasiRevisi')
            ->assertSet('showModal', true)
            ->assertSet('lamaHari', 2) // ke-load dari data lama
            ->set('lamaHari', 5)
            ->call('cetak')
            ->assertOk();

        $surat->refresh();
        $this->assertSame($nomorAsli, $surat->nomor_surat, 'Nomor surat tidak boleh berubah saat revisi');
        $this->assertEquals($dicetakPadaAsli->timestamp, $surat->dicetak_pada->timestamp, 'dicetak_pada asli tidak boleh berubah');
        $this->assertSame(5, $surat->data['lama_hari']);
        $this->assertSame(1, $surat->revision_count);
        $this->assertSame($ctx['dokterUser']->id, $surat->revised_by);
        $this->assertSame('Salah jumlah hari istirahat, seharusnya 5 hari', $surat->revision_reason);

        $log = Activity::where('log_name', 'surat_keterangan')->where('subject_id', $surat->id)->latest()->first();
        $this->assertNotNull($log, 'Revisi harus tercatat di activity_log');
    }

    /** @test */
    public function edit_surat_wajib_isi_alasan(): void
    {
        $ctx = $this->buatKunjungan();
        $surat = app(SuratKeteranganService::class)->simpanSakit($ctx['kunjungan'], [
            'dokter_id' => $ctx['dokter']->id, 'tanggal_mulai' => now()->toDateString(), 'lama_hari' => 2,
        ], $ctx['dokterUser']->id);

        Livewire::test(CetakSurat::class, ['kunjunganId' => $ctx['kunjungan']->id])
            ->call('mulaiEdit', $surat->id)
            ->set('alasanRevisi', '')
            ->call('konfirmasiRevisi')
            ->assertHasErrors(['alasanRevisi' => 'required'])
            ->assertSet('showModal', false);
    }

    /** @test */
    public function non_dokter_tidak_bisa_edit_surat_walau_punya_surat_cetak(): void
    {
        $ctx = $this->buatKunjungan();
        $surat = app(SuratKeteranganService::class)->simpanSakit($ctx['kunjungan'], [
            'dokter_id' => $ctx['dokter']->id, 'tanggal_mulai' => now()->toDateString(), 'lama_hari' => 2,
        ], $ctx['dokterUser']->id);

        $perawatUser = User::create([
            'nama' => 'Perawat Test ' . uniqid(), 'email' => 'perawat-' . uniqid() . '@example.test',
            'password' => Hash::make('password'), 'is_active' => true,
        ]);
        $perawatUser->assignRole('perawat');
        $perawatUser->givePermissionTo('surat.cetak'); // extra permission, tapi bukan surat.revisi
        $this->actingAs($perawatUser);

        Livewire::test(CetakSurat::class, ['kunjunganId' => $ctx['kunjungan']->id])
            ->call('mulaiEdit', $surat->id)
            ->assertSet('showRevisiPrompt', false); // ditolak -- prompt tidak kebuka

        $this->assertSame(0, $surat->fresh()->revision_count);
    }

    /** @test */
    public function edit_surat_boleh_walau_kunjungan_sudah_selesai(): void
    {
        $ctx = $this->buatKunjungan('selesai');
        $surat = app(SuratKeteranganService::class)->simpanSakit($ctx['kunjungan'], [
            'dokter_id' => $ctx['dokter']->id, 'tanggal_mulai' => now()->toDateString(), 'lama_hari' => 2,
        ], $ctx['dokterUser']->id);

        Livewire::test(CetakSurat::class, ['kunjunganId' => $ctx['kunjungan']->id])
            ->call('mulaiEdit', $surat->id)
            ->assertSet('showRevisiPrompt', true);
    }

    /** @test */
    public function edit_surat_rujukan_memperbarui_tujuan_rujukan_id(): void
    {
        $ctx = $this->buatKunjungan();
        $tujuanLama = TujuanRujukan::create(['nama' => 'RS Lama']);

        $surat = app(SuratKeteranganService::class)->simpanRujukan($ctx['kunjungan'], [
            'dokter_id' => $ctx['dokter']->id, 'tujuan_fasilitas' => 'RS Lama', 'indikasi' => 'Awal',
        ], $ctx['dokterUser']->id);
        $this->assertSame($tujuanLama->id, $surat->tujuan_rujukan_id);

        Livewire::test(CetakSurat::class, ['kunjunganId' => $ctx['kunjungan']->id])
            ->call('mulaiEdit', $surat->id)
            ->set('alasanRevisi', 'Salah tujuan rujukan, seharusnya RS Baru')
            ->call('konfirmasiRevisi')
            ->set('tujuanFasilitas', 'RS Baru Sekali')
            ->call('cetak')
            ->assertOk();

        $surat->refresh();
        $tujuanBaru = TujuanRujukan::where('nama', 'RS Baru Sekali')->first();
        $this->assertNotNull($tujuanBaru);
        $this->assertSame($tujuanBaru->id, $surat->tujuan_rujukan_id);
        $this->assertSame('RS Baru Sekali', $surat->data['tujuan_fasilitas']);
        $this->assertSame(1, $surat->revision_count);
    }

    /** @test */
    public function pencarian_tujuan_rujukan_suggestions_berfungsi(): void
    {
        TujuanRujukan::create(['nama' => 'RSUP Sanglah Denpasar']);
        TujuanRujukan::create(['nama' => 'RS Bali Mandara']);
        $ctx = $this->buatKunjungan();

        $component = Livewire::test(CetakSurat::class, ['kunjunganId' => $ctx['kunjungan']->id])
            ->call('buka', 'rujukan')
            ->set('tujuanFasilitas', 'Sanglah');

        $suggestions = $component->get('tujuanRujukanSuggestions');
        $this->assertCount(1, $suggestions);
        $this->assertSame('RSUP Sanglah Denpasar', $suggestions->first()->nama);
    }
}

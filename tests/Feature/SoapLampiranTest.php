<?php

namespace Tests\Feature;

use App\Livewire\Pemeriksaan\SoapNote as SoapNoteLivewire;
use App\Models\Dokter;
use App\Models\Kunjungan;
use App\Models\Pasien;
use App\Models\SoapLampiran;
use App\Models\SoapNote as SoapNoteModel;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Upload lampiran dokumen/foto di SOAP Note (permintaan user): foto luka,
 * foto hasil jahitan, gambaran radiologis, file ekspertise radiologi,
 * surat persetujuan tindakan. Format JPEG/PDF, maksimal 1 MB.
 *
 * File disimpan di disk 'local' (private) -- lihat migrasi
 * create_soap_lampiran_table & routes/web.php (pemeriksaan.lampiran.unduh).
 *
 * Pakai DatabaseTransactions -- bukan RefreshDatabase (lihat catatan yang
 * sama di SensitiveActionAuthorizationTest.php).
 */
class SoapLampiranTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

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

        $this->actingAs($dokterUser);

        return compact('dokterUser', 'dokter', 'pasien', 'kunjungan');
    }

    /** @test */
    public function foto_jpeg_berhasil_diupload_dan_tersimpan_di_disk_local(): void
    {
        $ctx = $this->buatKunjungan();

        Livewire::test(SoapNoteLivewire::class, ['kunjunganId' => $ctx['kunjungan']->id])
            ->set('activeSection', 'lamp')
            ->set('kategoriLampiran', 'foto_luka')
            ->set('keteranganLampiran', 'Luka kaki kanan')
            ->set('lampiranBaru', UploadedFile::fake()->image('luka.jpg', 100, 100)->size(500))
            ->call('uploadLampiran')
            ->assertHasNoErrors();

        $lampiran = SoapLampiran::where('kunjungan_id', $ctx['kunjungan']->id)->first();
        $this->assertNotNull($lampiran);
        $this->assertSame('foto_luka', $lampiran->kategori);
        $this->assertSame('Luka kaki kanan', $lampiran->keterangan);
        $this->assertSame($ctx['dokterUser']->id, $lampiran->uploaded_by);
        Storage::disk('local')->assertExists($lampiran->path);
    }

    /** @test */
    public function dokumen_pdf_berhasil_diupload(): void
    {
        $ctx = $this->buatKunjungan();

        Livewire::test(SoapNoteLivewire::class, ['kunjunganId' => $ctx['kunjungan']->id])
            ->set('kategoriLampiran', 'persetujuan_tindakan')
            ->set('lampiranBaru', UploadedFile::fake()->create('persetujuan.pdf', 300, 'application/pdf'))
            ->call('uploadLampiran')
            ->assertHasNoErrors();

        $lampiran = SoapLampiran::where('kunjungan_id', $ctx['kunjungan']->id)->first();
        $this->assertSame('persetujuan_tindakan', $lampiran->kategori);
        $this->assertFalse($lampiran->is_gambar);
    }

    /** @test */
    public function file_lebih_dari_1mb_ditolak(): void
    {
        $ctx = $this->buatKunjungan();

        // UploadedFile::fake()->image(...)->size() cuma menyetel angka ukuran
        // yang "dilaporkan", bukan byte fisik -- setelah roundtrip lewat
        // penyimpanan sementara Livewire, ukuran fisik asli (kecil) yang
        // dipakai, bukan angka fake-nya, jadi validasi max tidak pernah
        // ketemu ukuran besar. Pakai create() dengan string konten supaya
        // file fisiknya benar-benar >1MB dan bertahan lewat roundtrip.
        $fileBesar = UploadedFile::fake()->create('besar.pdf', str_repeat('x', 1200 * 1024));

        Livewire::test(SoapNoteLivewire::class, ['kunjunganId' => $ctx['kunjungan']->id])
            ->set('kategoriLampiran', 'foto_luka')
            ->set('lampiranBaru', $fileBesar)
            ->call('uploadLampiran')
            ->assertHasErrors(['lampiranBaru' => 'max']);

        $this->assertSame(0, SoapLampiran::where('kunjungan_id', $ctx['kunjungan']->id)->count());
    }

    /** @test */
    public function format_selain_jpeg_pdf_ditolak(): void
    {
        $ctx = $this->buatKunjungan();

        Livewire::test(SoapNoteLivewire::class, ['kunjunganId' => $ctx['kunjungan']->id])
            ->set('kategoriLampiran', 'foto_luka')
            ->set('lampiranBaru', UploadedFile::fake()->create('dokumen.docx', 200, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'))
            ->call('uploadLampiran')
            ->assertHasErrors(['lampiranBaru' => 'mimes']);

        $this->assertSame(0, SoapLampiran::where('kunjungan_id', $ctx['kunjungan']->id)->count());
    }

    /** @test */
    public function kategori_wajib_dipilih(): void
    {
        $ctx = $this->buatKunjungan();

        Livewire::test(SoapNoteLivewire::class, ['kunjunganId' => $ctx['kunjungan']->id])
            ->set('lampiranBaru', UploadedFile::fake()->image('luka.jpg')->size(200))
            ->call('uploadLampiran')
            ->assertHasErrors(['kategoriLampiran' => 'required']);
    }

    /** @test */
    public function lampiran_bisa_diupload_walau_soap_sudah_final(): void
    {
        $ctx = $this->buatKunjungan();
        SoapNoteModel::create([
            'kunjungan_id' => $ctx['kunjungan']->id,
            'is_final'     => true,
            'finalized_at' => now(),
            'finalized_by' => $ctx['dokterUser']->id,
            'icd_codes'    => [['kode' => 'A09', 'nama' => 'Diarrhoea', 'is_primary' => true]],
        ]);

        Livewire::test(SoapNoteLivewire::class, ['kunjunganId' => $ctx['kunjungan']->id])
            ->assertSet('isFinal', true)
            ->set('kategoriLampiran', 'radiologi')
            ->set('lampiranBaru', UploadedFile::fake()->image('rontgen.jpg')->size(200))
            ->call('uploadLampiran')
            ->assertHasNoErrors();

        $this->assertSame(1, SoapLampiran::where('kunjungan_id', $ctx['kunjungan']->id)->count());
    }

    /** @test */
    public function hapus_lampiran_menghapus_record_dan_file_di_disk(): void
    {
        $ctx = $this->buatKunjungan();

        $component = Livewire::test(SoapNoteLivewire::class, ['kunjunganId' => $ctx['kunjungan']->id])
            ->set('kategoriLampiran', 'foto_jahitan')
            ->set('lampiranBaru', UploadedFile::fake()->image('jahitan.jpg')->size(200))
            ->call('uploadLampiran');

        $lampiran = SoapLampiran::where('kunjungan_id', $ctx['kunjungan']->id)->first();
        Storage::disk('local')->assertExists($lampiran->path);

        $component->call('hapusLampiran', $lampiran->id);

        $this->assertSame(0, SoapLampiran::where('kunjungan_id', $ctx['kunjungan']->id)->count());
        Storage::disk('local')->assertMissing($lampiran->path);
    }

    /** @test */
    public function lampiran_kunjungan_lain_tidak_ikut_tampil(): void
    {
        $ctxA = $this->buatKunjungan();
        $ctxB = $this->buatKunjungan();

        SoapLampiran::create([
            'kunjungan_id' => $ctxB['kunjungan']->id, 'kategori' => 'foto_luka',
            'nama_file' => 'punya-b.jpg', 'path' => 'lampiran-soap/dummy-b.jpg',
            'mime_type' => 'image/jpeg', 'ukuran' => 1000, 'uploaded_by' => $ctxB['dokterUser']->id,
        ]);

        $daftar = Livewire::test(SoapNoteLivewire::class, ['kunjunganId' => $ctxA['kunjungan']->id])
            ->get('daftarLampiran');

        $this->assertCount(0, $daftar);
    }
}

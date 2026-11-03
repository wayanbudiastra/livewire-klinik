<?php

namespace Tests\Feature;

use App\Livewire\Pemeriksaan\SoapNote as SoapNoteLivewire;
use App\Models\AsesmenPerawat;
use App\Models\Dokter;
use App\Models\Kunjungan;
use App\Models\Pasien;
use App\Models\SoapNote as SoapNoteModel;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * Test revisi Tanda Vital (TTV) saat revisi SOAP Note (fitur: dokter bisa
 * merevisi TTV bersama SOAP atau TTV saja).
 *
 * - TTV tersimpan di asesmen_perawat (tabel terpisah dari soap_note)
 * - Saat revisi SOAP, TTV yang diubah di-update di asesmen_perawat
 * - Jejak audit mencatat ttv_sebelum/ttv_sesudah + alasan revisi
 * - Batal revisi → TTV balik ke nilai sebelumnya
 * - Perawat_id asesmen tetap dipertahankan (tidak ditimpa dokter yang merevisi)
 */
class RevisiSoapTtvTest extends TestCase
{
    use DatabaseTransactions;

    private function buatKunjunganDenganSoapFinalDanTtv(): array
    {
        $dokterUser = User::create([
            'nama'      => 'Dr. Test ' . uniqid(),
            'email'     => 'dokter-' . uniqid() . '@example.test',
            'password'  => Hash::make('password'),
            'is_active' => true,
        ]);
        $dokterUser->assignRole('dokter');
        $dokter = Dokter::create(['user_id' => $dokterUser->id]);

        $pasien = Pasien::create([
            'nomor_rm'      => 'RM-' . uniqid(),
            'nama'          => 'Pasien Test',
            'tempat_lahir'  => 'Denpasar',
            'tanggal_lahir' => '1990-01-01',
            'jenis_kelamin' => 'L',
            'alamat'        => 'Jl. Test No. 1',
            'telepon'       => '081234567890',
        ]);

        $kunjungan = Kunjungan::create([
            'nomor_antrean' => 'A-' . uniqid(),
            'pasien_id'     => $pasien->id,
            'dokter_id'     => $dokter->id,
            'status'        => 'dalam_pemeriksaan',
            'tanggal'       => now(),
        ]);

        // Buat SOAP final
        $soap = SoapNoteModel::create([
            'kunjungan_id' => $kunjungan->id,
            's_hpi'     => 'Demam 3 hari',
            'icd_codes'    => [['kode' => 'A09', 'nama' => 'Diare', 'is_primary' => true]],
            'is_final'     => true,
            'finalized_at' => now(),
            'finalized_by' => $dokterUser->id,
        ]);

        // Buat TTV di asesmen_perawat
        $perawat = User::create([
            'nama'      => 'Perawat Test ' . uniqid(),
            'email'     => 'perawat-' . uniqid() . '@example.test',
            'password'  => Hash::make('password'),
            'is_active' => true,
        ]);
        $perawat->assignRole('perawat');
        $perawatModel = \App\Models\Perawat::create(['user_id' => $perawat->id]);

        $asesmen = AsesmenPerawat::create([
            'kunjungan_id' => $kunjungan->id,
            'perawat_id'   => $perawatModel->id,
            'berat_badan'  => 65.5,
            'tinggi_badan' => 170.0,
            'tekanan_darah' => '120/80',
            'nadi'         => 78,
            'suhu'         => 36.8,
            'saturasi'     => 98,
            'gds'          => 110.5,
        ]);

        return compact('dokterUser', 'dokter', 'pasien', 'kunjungan', 'soap', 'asesmen', 'perawat', 'perawatModel');
    }

    /** @test */
    public function dokter_bisa_merevisi_ttv_saat_revisi_soap(): void
    {
        $data = $this->buatKunjunganDenganSoapFinalDanTtv();
        $this->actingAs($data['dokterUser']);

        Livewire::test(SoapNoteLivewire::class, ['kunjunganId' => $data['kunjungan']->id])
            ->call('mulaiRevisi')
            ->set('alasanRevisi', 'Koreksi TTV TD yang salah input')
            ->call('konfirmasiRevisi')
            // TTV ter-load ke form tanpa nol-ekor dari cast decimal
            ->assertSet('beratBadan', '65.5')
            ->assertSet('saturasi', '98')
            ->assertSet('tekananDarah', '120/80')
            // Ubah TTV
            ->set('tekananDarah', '130/90')
            ->set('suhu', '37.2')
            ->call('simpanRevisi')
            ->assertHasNoErrors()
            ->assertSet('sedangRevisi', false);

        // Verifikasi TTV tersimpan di asesmen_perawat
        $asesmen = $data['asesmen']->fresh();
        $this->assertSame('130/90', $asesmen->tekanan_darah);
        $this->assertEquals(37.2, (float) $asesmen->suhu);
        // TTV yang tidak disentuh tidak ikut berubah
        $this->assertEquals(65.5, (float) $asesmen->berat_badan);
        $this->assertSame(98, (int) $asesmen->saturasi);
        // Perawat_id dipertahankan
        $this->assertSame($data['perawatModel']->id, $asesmen->perawat_id);

        // Verifikasi SOAP tidak berubah (hanya yang di-ubah di SOAP)
        $soap = $data['soap']->fresh();
        $this->assertTrue($soap->is_final, 'is_final tetap true');
        $this->assertSame(1, $soap->revision_count);

        // Verifikasi jejak audit mencatat ttv_sebelum/ttv_sesudah
        $log = Activity::where('log_name', 'soap_note')
            ->where('subject_id', $soap->id)
            ->latest()
            ->first();
        $this->assertNotNull($log, 'Jejak audit harus ada');
        $this->assertSame('120/80', $log->properties['ttv_sebelum']['tekanan_darah']);
        $this->assertSame('130/90', $log->properties['ttv_sesudah']['tekanan_darah']);
        $this->assertEquals(36.8, (float) $log->properties['ttv_sebelum']['suhu']);
        $this->assertEquals(37.2, (float) $log->properties['ttv_sesudah']['suhu']);
    }

    /** @test */
    public function revisi_ttv_saja_tanpa_ubah_soap_fields(): void
    {
        $data = $this->buatKunjunganDenganSoapFinalDanTtv();
        $this->actingAs($data['dokterUser']);

        Livewire::test(SoapNoteLivewire::class, ['kunjunganId' => $data['kunjungan']->id])
            ->call('mulaiRevisi')
            ->set('alasanRevisi', 'Koreksi berat badan salah catat')
            ->call('konfirmasiRevisi')
            // Ubah hanya TTV, tidak ubah SOAP fields
            ->set('beratBadan', '70.0')
            ->call('simpanRevisi')
            ->assertHasNoErrors();

        $asesmen = $data['asesmen']->fresh();
        $this->assertEquals(70.0, (float) $asesmen->berat_badan);

        // SOAP fields tetap sama
        $soap = $data['soap']->fresh();
        $this->assertSame('Demam 3 hari', $soap->s_hpi);
    }

    /** @test */
    public function validasi_ttv_saat_simpan_revisi(): void
    {
        $data = $this->buatKunjunganDenganSoapFinalDanTtv();
        $this->actingAs($data['dokterUser']);

        Livewire::test(SoapNoteLivewire::class, ['kunjunganId' => $data['kunjungan']->id])
            ->call('mulaiRevisi')
            ->set('alasanRevisi', 'Tes validasi suhu')
            ->call('konfirmasiRevisi')
            // Suhu < 30 (invalid)
            ->set('suhu', '25')
            ->call('simpanRevisi')
            ->assertHasErrors(['suhu']);

        // TTV tidak berubah
        $this->assertEquals(36.8, (float) $data['asesmen']->fresh()->suhu);
    }

    /** @test */
    public function batal_revisi_ttv(): void
    {
        $data = $this->buatKunjunganDenganSoapFinalDanTtv();
        $this->actingAs($data['dokterUser']);

        Livewire::test(SoapNoteLivewire::class, ['kunjunganId' => $data['kunjungan']->id])
            ->call('mulaiRevisi')
            ->set('alasanRevisi', 'Tes batal revisi')
            ->call('konfirmasiRevisi')
            // Ubah TTV
            ->set('beratBadan', '75.0')
            ->set('tekananDarah', '150/100')
            // Batalkan
            ->call('batalRevisi')
            ->assertSet('sedangRevisi', false)
            ->assertSet('beratBadan', '65.5') // balik ke semula
            ->assertSet('tekananDarah', '120/80');

        // TTV di database tetap sama
        $asesmen = $data['asesmen']->fresh();
        $this->assertEquals(65.5, (float) $asesmen->berat_badan);
        $this->assertSame('120/80', $asesmen->tekanan_darah);

        // Activity log tidak ada (revisi dibatalkan)
        $log = Activity::where('log_name', 'soap_note')
            ->where('subject_id', $data['soap']->id)
            ->latest()
            ->first();
        $this->assertNull($log, 'Tidak ada jejak audit untuk revisi yang dibatalkan');
    }

    /** @test */
    public function dapat_isi_ttv_baru_jika_sebelumnya_kosong(): void
    {
        $dokterUser = User::create([
            'nama'      => 'Dr. Test ' . uniqid(),
            'email'     => 'dokter-' . uniqid() . '@example.test',
            'password'  => Hash::make('password'),
            'is_active' => true,
        ]);
        $dokterUser->assignRole('dokter');
        $dokter = Dokter::create(['user_id' => $dokterUser->id]);

        $pasien = Pasien::create([
            'nomor_rm'      => 'RM-' . uniqid(),
            'nama'          => 'Pasien Test',
            'tempat_lahir'  => 'Denpasar',
            'tanggal_lahir' => '1990-01-01',
            'jenis_kelamin' => 'L',
            'alamat'        => 'Jl. Test No. 1',
            'telepon'       => '081234567890',
        ]);

        $kunjungan = Kunjungan::create([
            'nomor_antrean' => 'A-' . uniqid(),
            'pasien_id'     => $pasien->id,
            'dokter_id'     => $dokter->id,
            'status'        => 'dalam_pemeriksaan',
            'tanggal'       => now(),
        ]);

        // SOAP final tanpa TTV
        $soap = SoapNoteModel::create([
            'kunjungan_id' => $kunjungan->id,
            's_hpi'     => 'Demam',
            'icd_codes'    => [['kode' => 'A09', 'nama' => 'Diare', 'is_primary' => true]],
            'is_final'     => true,
            'finalized_at' => now(),
            'finalized_by' => $dokterUser->id,
        ]);

        $this->actingAs($dokterUser);

        Livewire::test(SoapNoteLivewire::class, ['kunjunganId' => $kunjungan->id])
            ->call('mulaiRevisi')
            ->set('alasanRevisi', 'Tambah data TTV yang belum ada')
            ->call('konfirmasiRevisi')
            // Isi TTV baru
            ->set('beratBadan', '60')
            ->set('tinggiBadan', '165')
            ->set('tekananDarah', '120/80')
            ->set('nadi', '80')
            ->set('suhu', '36.5')
            ->set('saturasi', '98')
            ->call('simpanRevisi')
            ->assertHasNoErrors();

        // TTV baru tersimpan (create, bukan update)
        $asesmen = AsesmenPerawat::where('kunjungan_id', $kunjungan->id)->first();
        $this->assertNotNull($asesmen);
        $this->assertEquals(60, (float) $asesmen->berat_badan);
        $this->assertEquals(165, (float) $asesmen->tinggi_badan);
    }

    /** @test */
    public function ttv_null_tercatat_di_jejak_audit(): void
    {
        $data = $this->buatKunjunganDenganSoapFinalDanTtv();
        $this->actingAs($data['dokterUser']);

        Livewire::test(SoapNoteLivewire::class, ['kunjunganId' => $data['kunjungan']->id])
            ->call('mulaiRevisi')
            ->set('alasanRevisi', 'Revisi GDS menjadi kosong')
            ->call('konfirmasiRevisi')
            // Kosongkan GDS
            ->set('gds', '')
            ->call('simpanRevisi');

        $asesmen = $data['asesmen']->fresh();
        $this->assertNull($asesmen->gds);

        $log = Activity::where('log_name', 'soap_note')
            ->where('subject_id', $data['soap']->id)
            ->latest()
            ->first();
        $this->assertEquals(110.5, (float) $log->properties['ttv_sebelum']['gds']);
        $this->assertNull($log->properties['ttv_sesudah']['gds']);
    }
}

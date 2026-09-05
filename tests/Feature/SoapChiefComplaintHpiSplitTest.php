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
use Tests\TestCase;

/**
 * Pemisahan field "Chief Complaint & History of Present Illness (CC + HPI)"
 * (permintaan user) jadi 2 field terpisah: Chief Complaint (singkat) &
 * History of Present Illness (naratif). Chief Complaint auto-terisi dari
 * rantai yang sudah ada: kunjungan.keluhan -> asesmen_perawat.anamnesis_awal
 * -> SOAP Chief Complaint (bisa diedit di tiap tahap).
 *
 * Pakai DatabaseTransactions -- lihat catatan yang sama di
 * SensitiveActionAuthorizationTest.php soal kenapa bukan RefreshDatabase.
 */
class SoapChiefComplaintHpiSplitTest extends TestCase
{
    use DatabaseTransactions;

    private function buatKunjungan(?string $keluhan = null): array
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
            'status' => 'dalam_pemeriksaan', 'tanggal' => now(), 'keluhan' => $keluhan,
        ]);

        $this->actingAs($dokterUser);

        return compact('dokterUser', 'dokter', 'pasien', 'kunjungan');
    }

    /** @test */
    public function chief_complaint_soap_auto_terisi_dari_anamnesis_perawat(): void
    {
        $ctx = $this->buatKunjungan('Demam 3 hari');
        AsesmenPerawat::create([
            'kunjungan_id'   => $ctx['kunjungan']->id,
            'anamnesis_awal' => 'Demam 3 hari, disertai batuk pilek',
        ]);

        Livewire::test(SoapNoteLivewire::class, ['kunjunganId' => $ctx['kunjungan']->id])
            ->assertSet('sChiefComplaint', 'Demam 3 hari, disertai batuk pilek');
    }

    /** @test */
    public function chief_complaint_soap_fallback_ke_keluhan_pendaftaran_kalau_belum_ada_asesmen(): void
    {
        $ctx = $this->buatKunjungan('Nyeri dada');

        Livewire::test(SoapNoteLivewire::class, ['kunjunganId' => $ctx['kunjungan']->id])
            ->assertSet('sChiefComplaint', 'Nyeri dada');
    }

    /** @test */
    public function chief_complaint_dan_hpi_tersimpan_terpisah(): void
    {
        $ctx = $this->buatKunjungan();

        Livewire::test(SoapNoteLivewire::class, ['kunjunganId' => $ctx['kunjungan']->id])
            ->set('sChiefComplaint', 'Demam 3 hari')
            ->set('sHpi', 'Pasien datang dengan demam sejak 3 hari lalu, disertai batuk berdahak dan pilek.')
            ->call('addDiagnosis', 'A09', 'Diarrhoea')
            ->call('simpan');

        $soap = SoapNoteModel::where('kunjungan_id', $ctx['kunjungan']->id)->first();
        $this->assertSame('Demam 3 hari', $soap->s_chief_complaint);
        $this->assertSame('Pasien datang dengan demam sejak 3 hari lalu, disertai batuk berdahak dan pilek.', $soap->s_hpi);
    }

    /** @test */
    public function rekam_medis_lama_dengan_s_cc_hpi_tetap_tampil_sebagai_hpi(): void
    {
        $ctx = $this->buatKunjungan();
        SoapNoteModel::create([
            'kunjungan_id' => $ctx['kunjungan']->id,
            's_cc_hpi'     => 'Demam 3 hari sejak kemarin (data lama sebelum field dipisah)',
            'icd_codes'    => [['kode' => 'A09', 'nama' => 'Diarrhoea', 'is_primary' => true]],
        ]);

        Livewire::test(SoapNoteLivewire::class, ['kunjunganId' => $ctx['kunjungan']->id])
            ->assertSet('sHpi', 'Demam 3 hari sejak kemarin (data lama sebelum field dipisah)')
            ->assertSet('sChiefComplaint', ''); // tidak ada data lama utk CC, tidak difabrikasi
    }

    /** @test */
    public function chief_complaint_yang_sudah_diisi_tidak_ditimpa_auto_fill(): void
    {
        $ctx = $this->buatKunjungan('Keluhan dari pendaftaran');
        SoapNoteModel::create([
            'kunjungan_id'      => $ctx['kunjungan']->id,
            's_chief_complaint' => 'Chief complaint hasil isian dokter sebelumnya',
            'icd_codes'         => [['kode' => 'A09', 'nama' => 'Diarrhoea', 'is_primary' => true]],
        ]);

        Livewire::test(SoapNoteLivewire::class, ['kunjunganId' => $ctx['kunjungan']->id])
            ->assertSet('sChiefComplaint', 'Chief complaint hasil isian dokter sebelumnya');
    }
}

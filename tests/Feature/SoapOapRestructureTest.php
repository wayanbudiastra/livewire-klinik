<?php

namespace Tests\Feature;

use App\Livewire\Pemeriksaan\CetakSurat;
use App\Livewire\Pemeriksaan\DetailPemeriksaan;
use App\Livewire\Pemeriksaan\SoapNote as SoapNoteLivewire;
use App\Models\Barang;
use App\Models\Dokter;
use App\Models\ItemResep;
use App\Models\Kunjungan;
use App\Models\Pasien;
use App\Models\Resep;
use App\Models\SoapNote as SoapNoteModel;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Restrukturisasi Objective/Assessment/Planning di SOAP Note (permintaan
 * user): Objective -> Physical Examination + Supporting Examination;
 * Assessment -> Primary Diagnosis + Diagnosis ICD-10 (tetap) + Differential
 * Diagnosis; Planning -> Treatment + Prescription Medicine (ringkasan
 * read-only dari modul Resep) + Advice + Transportation + Escort + Notes.
 *
 * Field lama (o_systemic_exam, o_observation, o_other, a_problems,
 * a_progress_note, a_other, p_other) TIDAK dihapus dari DB, cuma tidak
 * ditulis/dipakai lagi di form -- lihat migrasi
 * 2026_09_05_195743_add_opa_planning_fields_to_soap_note_table.php.
 *
 * Pakai DatabaseTransactions -- bukan RefreshDatabase (lihat catatan yang
 * sama di SensitiveActionAuthorizationTest.php).
 */
class SoapOapRestructureTest extends TestCase
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

        $this->actingAs($dokterUser);

        return compact('dokterUser', 'dokter', 'pasien', 'kunjungan');
    }

    /** @test */
    public function field_opa_baru_tersimpan_dengan_benar(): void
    {
        $ctx = $this->buatKunjungan();

        Livewire::test(SoapNoteLivewire::class, ['kunjunganId' => $ctx['kunjungan']->id])
            ->set('oPhysicalExam', 'Keadaan umum baik')
            ->set('oSupportingExamination', 'Lab: Hb 13.5, Leukosit normal')
            ->set('aPrimaryDiagnosis', 'ISPA')
            ->set('aDifferentialDiagnosis', 'Faringitis akut')
            ->call('addDiagnosis', 'J06.9', 'Acute upper respiratory infection')
            ->set('pTreatment', 'Simptomatik, istirahat cukup')
            ->set('pTransportation', 'fit_to_fly')
            ->set('pEscort', 'no_escort')
            ->set('pNotes', 'Kontrol jika tidak membaik dalam 3 hari')
            ->call('simpan');

        $soap = SoapNoteModel::where('kunjungan_id', $ctx['kunjungan']->id)->first();

        $this->assertSame('Keadaan umum baik', $soap->o_physical_exam);
        $this->assertSame('Lab: Hb 13.5, Leukosit normal', $soap->o_supporting_examination);
        $this->assertSame('ISPA', $soap->a_primary_diagnosis);
        $this->assertSame('Faringitis akut', $soap->a_differential_diagnosis);
        $this->assertSame('Simptomatik, istirahat cukup', $soap->p_treatment);
        $this->assertSame('fit_to_fly', $soap->p_transportation);
        $this->assertSame('no_escort', $soap->p_escort);
        $this->assertSame('Kontrol jika tidak membaik dalam 3 hari', $soap->p_notes);

        // Field lama yang sudah diganti total tidak lagi ditulis dari form baru.
        $this->assertNull($soap->o_systemic_exam);
        $this->assertNull($soap->o_observation);
        $this->assertNull($soap->o_other);
        $this->assertNull($soap->a_problems);
        $this->assertNull($soap->a_progress_note);
        $this->assertNull($soap->a_other);
        $this->assertNull($soap->p_other);
    }

    /** @test */
    public function rekam_medis_lama_dengan_kolom_lama_tidak_terganggu(): void
    {
        $ctx = $this->buatKunjungan();
        SoapNoteModel::create([
            'kunjungan_id'    => $ctx['kunjungan']->id,
            'o_systemic_exam' => 'Data lama sebelum restrukturisasi',
            'a_problems'      => 'Problem lama',
            'icd_codes'       => [['kode' => 'A09', 'nama' => 'Diarrhoea', 'is_primary' => true]],
        ]);

        // Data lama tetap ada di DB walaupun form baru tidak menampilkannya lagi.
        $soap = SoapNoteModel::where('kunjungan_id', $ctx['kunjungan']->id)->first();
        $this->assertSame('Data lama sebelum restrukturisasi', $soap->o_systemic_exam);
        $this->assertSame('Problem lama', $soap->a_problems);

        Livewire::test(SoapNoteLivewire::class, ['kunjunganId' => $ctx['kunjungan']->id])
            ->assertSet('oSupportingExamination', '')
            ->assertSet('aPrimaryDiagnosis', '');
    }

    /** @test */
    public function dropdown_transportation_dan_escort_hanya_menerima_opsi_valid(): void
    {
        $opsiTransportation = SoapNoteModel::opsiTransportation();
        $opsiEscort         = SoapNoteModel::opsiEscort();

        $this->assertSame(['fit_to_fly' => 'Fit to Fly', 'not_fit_to_fly' => 'Not Fit to Fly'], $opsiTransportation);
        $this->assertSame([
            'medical_escort'     => 'Medical Escort',
            'non_medical_escort' => 'Non Medical Escort',
            'no_escort'          => 'No Escort',
        ], $opsiEscort);

        $soap = SoapNoteModel::create([
            'kunjungan_id'     => $this->buatKunjungan()['kunjungan']->id,
            'p_transportation' => 'not_fit_to_fly',
            'p_escort'         => 'medical_escort',
        ]);

        $this->assertSame('Not Fit to Fly', $soap->label_transportation);
        $this->assertSame('Medical Escort', $soap->label_escort);
    }

    /** @test */
    public function prescription_medicine_summary_hanya_tampilkan_resep_yang_sudah_dikunci(): void
    {
        $ctx = $this->buatKunjungan();

        $barang = Barang::create([
            'kode' => 'OBT-' . uniqid(), 'nama' => 'Paracetamol 500mg',
            'satuan' => 'Tablet', 'jenis' => 'obat', 'harga_jual' => 1000, 'harga_pokok' => 800,
        ]);

        $resepLocked = Resep::create([
            'kunjungan_id' => $ctx['kunjungan']->id, 'dokter_id' => $ctx['dokter']->id,
            'status' => 'diambil', 'is_locked' => true,
        ]);
        ItemResep::create([
            'resep_id' => $resepLocked->id, 'barang_id' => $barang->id,
            'jumlah' => 10, 'aturan_pakai' => '3x1 sesudah makan',
        ]);

        $resepBelumLocked = Resep::create([
            'kunjungan_id' => $ctx['kunjungan']->id, 'dokter_id' => $ctx['dokter']->id,
            'status' => 'menunggu', 'is_locked' => false,
        ]);
        ItemResep::create([
            'resep_id' => $resepBelumLocked->id, 'barang_id' => $barang->id,
            'jumlah' => 5, 'aturan_pakai' => 'draft, belum di-charge',
        ]);

        $component = Livewire::test(SoapNoteLivewire::class, ['kunjunganId' => $ctx['kunjungan']->id]);
        $summary = $component->get('resepSummary');

        $this->assertCount(1, $summary);
        $this->assertSame('Paracetamol 500mg', $summary[0]['nama']);
        $this->assertSame('3x1 sesudah makan', $summary[0]['aturan_pakai']);
        $this->assertSame(10, $summary[0]['jumlah']);
    }

    /** @test */
    public function link_kelola_resep_memicu_pindah_tab_medication_di_detail_pemeriksaan(): void
    {
        $ctx = $this->buatKunjungan();

        Livewire::test(DetailPemeriksaan::class, ['kunjunganId' => $ctx['kunjungan']->id])
            ->assertSet('activeSection', 'identitas')
            ->call('switchSection', 'obat')
            ->assertSet('activeSection', 'obat');
    }

    /** @test */
    public function resume_medis_auto_terisi_dari_transportation_dan_escort_soap(): void
    {
        $ctx = $this->buatKunjungan();
        SoapNoteModel::create([
            'kunjungan_id'     => $ctx['kunjungan']->id,
            'p_transportation' => 'fit_to_fly',
            'p_escort'         => 'non_medical_escort',
            'icd_codes'        => [['kode' => 'A09', 'nama' => 'Diarrhoea', 'is_primary' => true]],
        ]);

        Livewire::test(CetakSurat::class, ['kunjunganId' => $ctx['kunjungan']->id])
            ->call('buka', 'resume_medis')
            ->assertSet('recommendation', 'Fit to Fly')
            ->assertSet('escorted', 'Non Medical Escort');
    }

    /** @test */
    public function resume_medis_tetap_bisa_diedit_manual_setelah_auto_fill(): void
    {
        $ctx = $this->buatKunjungan();
        SoapNoteModel::create([
            'kunjungan_id'     => $ctx['kunjungan']->id,
            'p_transportation' => 'fit_to_fly',
            'p_escort'         => 'no_escort',
            'icd_codes'        => [['kode' => 'A09', 'nama' => 'Diarrhoea', 'is_primary' => true]],
        ]);

        Livewire::test(CetakSurat::class, ['kunjunganId' => $ctx['kunjungan']->id])
            ->call('buka', 'resume_medis')
            ->set('escorted', 'Diedit manual oleh dokter')
            ->assertSet('escorted', 'Diedit manual oleh dokter');
    }
}

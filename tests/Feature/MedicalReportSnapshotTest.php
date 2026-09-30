<?php

namespace Tests\Feature;

use App\Models\AsesmenPerawat;
use App\Models\Dokter;
use App\Models\Kunjungan;
use App\Models\Pasien;
use App\Models\SoapNote as SoapNoteModel;
use App\Models\User;
use App\Services\Pemeriksaan\SuratKeteranganService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Bug report user: "download Medical Report... banyak yg gak keluar walaupun
 * sudah diisi". Root cause: SuratKeteranganService::buildDataResumeMedis()
 * cuma mengambil segelintir kolom SOAP Note lama (subjektif/objektif/plan
 * gabungan), padahal SOAP Note sudah direstrukturisasi jadi banyak kolom
 * granular (s_past_medical, s_past_surgical, s_allergies, s_other,
 * o_supporting_examination, a_primary_diagnosis, a_differential_diagnosis,
 * p_treatment, p_notes) yang sekarang jadi field aktif di form -- builder
 * laporan tidak pernah diperbarui mengikutinya, jadi semua isian itu hilang
 * dari PDF walau sudah lengkap diisi dokter.
 *
 * Pakai DatabaseTransactions -- bukan RefreshDatabase.
 */
class MedicalReportSnapshotTest extends TestCase
{
    use DatabaseTransactions;

    private function buatKunjunganDenganSoapLengkap(): array
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

        AsesmenPerawat::create([
            'kunjungan_id' => $kunjungan->id, 'anamnesis_awal' => 'Keluhan awal dari perawat',
        ]);

        SoapNoteModel::create([
            'kunjungan_id'              => $kunjungan->id,
            's_chief_complaint'         => 'Keluhan utama versi dokter',
            's_hpi'                     => 'Riwayat penyakit sekarang',
            's_past_medical'            => 'Riwayat hipertensi 5 tahun',
            's_past_surgical'           => 'Apendektomi 2019',
            's_allergies'               => 'Alergi penisilin',
            's_other'                   => 'Catatan subjektif lainnya',
            'o_physical_exam'           => 'Keadaan umum baik',
            'o_supporting_examination'  => 'Rontgen thorax dalam batas normal',
            'a_primary_diagnosis'       => 'ISPA',
            'a_differential_diagnosis'  => 'Faringitis akut',
            'icd_codes'                 => [['kode' => 'J06.9', 'nama' => 'ISPA', 'is_primary' => true]],
            'p_advice'                  => 'Istirahat cukup, banyak minum',
            'p_treatment'               => 'Paracetamol 3x500mg',
            'p_notes'                   => 'Kontrol jika demam berlanjut 3 hari',
            'is_final'                  => true,
        ]);

        return compact('dokterUser', 'dokter', 'pasien', 'kunjungan');
    }

    /** @test */
    public function snapshot_resume_medis_menangkap_semua_field_soap_terstruktur_yang_sudah_diisi(): void
    {
        $ctx = $this->buatKunjunganDenganSoapLengkap();

        $surat = app(SuratKeteranganService::class)->simpanResumeMedis(
            $ctx['kunjungan'],
            ['dokter_id' => $ctx['dokter']->id, 'bahasa' => 'id'],
            $ctx['dokterUser']->id
        );

        $d = $surat->data;

        // Sebelum perbaikan: SEMUA field granular ini absen dari snapshot
        // (cuma s_hpi/objektif/plan gabungan & icd_codes yang keambil).
        $this->assertSame('Keluhan utama versi dokter', $d['chief_complaint_snapshot']);
        $this->assertSame('Riwayat penyakit sekarang', $d['subjektif_snapshot']);
        $this->assertSame('Riwayat hipertensi 5 tahun', $d['s_past_medical_snapshot']);
        $this->assertSame('Apendektomi 2019', $d['s_past_surgical_snapshot']);
        $this->assertSame('Alergi penisilin', $d['s_allergies_snapshot']);
        $this->assertSame('Catatan subjektif lainnya', $d['s_other_snapshot']);
        $this->assertSame('Keadaan umum baik', $d['objektif_snapshot']);
        $this->assertSame('Rontgen thorax dalam batas normal', $d['o_supporting_exam_snapshot']);
        $this->assertSame('ISPA', $d['a_primary_diagnosis_snapshot']);
        $this->assertSame('Faringitis akut', $d['a_differential_diagnosis_snapshot']);
        $this->assertSame('Istirahat cukup, banyak minum', $d['plan_snapshot']);
        $this->assertSame('Paracetamol 3x500mg', $d['p_treatment_snapshot']);
        $this->assertSame('Kontrol jika demam berlanjut 3 hari', $d['p_notes_snapshot']);
    }

    /** @test */
    public function chief_complaint_pakai_versi_dokter_bukan_versi_perawat_kalau_dokter_sudah_mengisi(): void
    {
        $ctx = $this->buatKunjunganDenganSoapLengkap();

        $surat = app(SuratKeteranganService::class)->simpanResumeMedis(
            $ctx['kunjungan'],
            ['dokter_id' => $ctx['dokter']->id, 'bahasa' => 'id'],
            $ctx['dokterUser']->id
        );

        // SOAP s_chief_complaint dokter ("Keluhan utama versi dokter") harus
        // menang atas anamnesis_awal perawat ("Keluhan awal dari perawat").
        $this->assertSame('Keluhan utama versi dokter', $surat->data['chief_complaint_snapshot']);
    }

    /** @test */
    public function chief_complaint_fallback_ke_anamnesis_perawat_kalau_dokter_belum_mengisi(): void
    {
        $ctx = $this->buatKunjunganDenganSoapLengkap();
        SoapNoteModel::where('kunjungan_id', $ctx['kunjungan']->id)->update(['s_chief_complaint' => null]);

        $surat = app(SuratKeteranganService::class)->simpanResumeMedis(
            $ctx['kunjungan'],
            ['dokter_id' => $ctx['dokter']->id, 'bahasa' => 'id'],
            $ctx['dokterUser']->id
        );

        $this->assertSame('Keluhan awal dari perawat', $surat->data['chief_complaint_snapshot']);
    }

    /** @test */
    public function pdf_medical_report_tetap_berhasil_dirender_dengan_field_lengkap(): void
    {
        $ctx = $this->buatKunjunganDenganSoapLengkap();

        $service = app(SuratKeteranganService::class);
        $surat = $service->simpanResumeMedis(
            $ctx['kunjungan'],
            ['dokter_id' => $ctx['dokter']->id, 'bahasa' => 'en'],
            $ctx['dokterUser']->id
        );

        $pdf = $service->pdfOutput($surat);

        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertGreaterThan(1000, strlen($pdf));
    }
}

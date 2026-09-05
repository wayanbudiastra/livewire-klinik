<?php

namespace Tests\Feature;

use App\Livewire\Pemeriksaan\SoapNote as SoapNoteLivewire;
use App\Models\Dokter;
use App\Models\Invoice;
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
 * Fitur revisi SOAP Note setelah difinalisasi (permintaan user):
 * - Boleh direvisi kapan pun, TERMASUK setelah billing lunas/closed --
 *   tidak digate status billing (user ralat requirement awal).
 * - Cuma role dokter (permission soap.revisi) yang boleh -- perawat/role
 *   lain yang kebetulan punya soap.edit TIDAK otomatis dapat soap.revisi.
 * - Wajib isi alasan revisi.
 * - is_final/finalized_at/finalized_by tidak berubah; yang di-update cuma
 *   isi field + revised_at/revised_by/revision_count/revision_reason.
 * - Tercatat ke activity_log (log_name 'soap_note') dengan before/after +
 *   alasan.
 *
 * Pakai DatabaseTransactions -- lihat catatan yang sama di
 * SensitiveActionAuthorizationTest.php soal kenapa bukan RefreshDatabase.
 * Livewire::test()->call() tidak melempar AuthorizationException mentah ke
 * PHPUnit (RequestBroker menangkapnya jadi response biasa) -- makanya kasus
 * "harus ditolak" dicek dari efek sampingnya (state tidak berubah), bukan
 * expectException().
 */
class RevisiSoapNoteTest extends TestCase
{
    use DatabaseTransactions;

    private function buatKunjunganDenganSoapFinal(): array
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

        $soap = SoapNoteModel::create([
            'kunjungan_id' => $kunjungan->id,
            's_hpi'     => 'Demam 3 hari',
            'icd_codes'    => [['kode' => 'A09', 'nama' => 'Diare', 'is_primary' => true]],
            'is_final'     => true,
            'finalized_at' => now(),
            'finalized_by' => $dokterUser->id,
        ]);

        return compact('dokterUser', 'dokter', 'pasien', 'kunjungan', 'soap');
    }

    /** @test */
    public function dokter_bisa_merevisi_soap_yang_sudah_final(): void
    {
        $data = $this->buatKunjunganDenganSoapFinal();
        $this->actingAs($data['dokterUser']);

        Livewire::test(SoapNoteLivewire::class, ['kunjunganId' => $data['kunjungan']->id])
            ->call('mulaiRevisi')
            ->assertSet('showRevisiPrompt', true)
            ->set('alasanRevisi', 'Koreksi CC/HPI setelah cross-check rekam medis')
            ->call('konfirmasiRevisi')
            ->assertSet('sedangRevisi', true)
            ->assertSet('showRevisiPrompt', false)
            ->set('sHpi', 'Demam 5 hari, direvisi')
            ->call('simpanRevisi')
            ->assertSet('sedangRevisi', false);

        $soap = $data['soap']->fresh();
        $this->assertSame('Demam 5 hari, direvisi', $soap->s_hpi);
        $this->assertTrue($soap->is_final, 'is_final harus tetap true setelah revisi');
        $this->assertSame(1, $soap->revision_count);
        $this->assertSame($data['dokterUser']->id, $soap->revised_by);
        $this->assertNotNull($soap->revised_at);
        $this->assertSame('Koreksi CC/HPI setelah cross-check rekam medis', $soap->revision_reason);

        $log = Activity::where('log_name', 'soap_note')->where('subject_id', $soap->id)->latest()->first();
        $this->assertNotNull($log, 'Revisi harus tercatat di activity_log');
        $this->assertSame('Demam 3 hari', $log->properties['sebelum']['s_hpi']);
        $this->assertSame('Demam 5 hari, direvisi', $log->properties['sesudah']['s_hpi']);
    }

    /** @test */
    public function alasan_revisi_wajib_diisi(): void
    {
        $data = $this->buatKunjunganDenganSoapFinal();
        $this->actingAs($data['dokterUser']);

        Livewire::test(SoapNoteLivewire::class, ['kunjunganId' => $data['kunjungan']->id])
            ->call('mulaiRevisi')
            ->set('alasanRevisi', '')
            ->call('konfirmasiRevisi')
            ->assertHasErrors(['alasanRevisi' => 'required'])
            ->assertSet('sedangRevisi', false);
    }

    /** @test */
    public function perawat_tidak_bisa_merevisi_soap_walau_punya_soap_view(): void
    {
        $data = $this->buatKunjunganDenganSoapFinal();

        $perawatUser = User::create([
            'nama'      => 'Perawat Test ' . uniqid(),
            'email'     => 'perawat-' . uniqid() . '@example.test',
            'password'  => Hash::make('password'),
            'is_active' => true,
        ]);
        $perawatUser->assignRole('perawat');
        $this->actingAs($perawatUser);

        Livewire::test(SoapNoteLivewire::class, ['kunjunganId' => $data['kunjungan']->id])
            ->call('mulaiRevisi')
            ->assertSet('showRevisiPrompt', false); // ditolak -- prompt tidak pernah kebuka

        // Bahkan kalau state sedangRevisi/showRevisiPrompt dipaksa true langsung
        // (skip mulaiRevisi), simpanRevisi tetap harus authorize() ulang & menolak.
        $soapSebelum = $data['soap']->s_hpi;
        Livewire::test(SoapNoteLivewire::class, ['kunjunganId' => $data['kunjungan']->id])
            ->set('sedangRevisi', true)
            ->set('sHpi', 'Harusnya tidak tersimpan')
            ->call('simpanRevisi');

        $this->assertSame($soapSebelum, $data['soap']->fresh()->s_hpi, 'Perawat tidak boleh berhasil menyimpan revisi');
    }

    /** @test */
    public function revisi_tetap_bisa_walau_billing_sudah_lunas(): void
    {
        $data = $this->buatKunjunganDenganSoapFinal();

        Invoice::create([
            'kunjungan_id'  => $data['kunjungan']->id,
            'nomor_invoice' => 'INV-' . uniqid(),
            'total_tagihan' => 100000,
            'total_bayar'   => 100000,
            'sisa'          => 0,
            'status'        => 'lunas',
        ]);

        $this->actingAs($data['dokterUser']);

        Livewire::test(SoapNoteLivewire::class, ['kunjunganId' => $data['kunjungan']->id])
            ->call('mulaiRevisi')
            ->assertSet('showRevisiPrompt', true);
    }

    /** @test */
    public function batal_revisi_membuang_perubahan_yang_belum_disimpan(): void
    {
        $data = $this->buatKunjunganDenganSoapFinal();
        $this->actingAs($data['dokterUser']);

        Livewire::test(SoapNoteLivewire::class, ['kunjunganId' => $data['kunjungan']->id])
            ->call('mulaiRevisi')
            ->set('alasanRevisi', 'Alasan percobaan revisi')
            ->call('konfirmasiRevisi')
            ->set('sHpi', 'Perubahan yang akan dibatalkan')
            ->call('batalRevisi')
            ->assertSet('sedangRevisi', false)
            ->assertSet('sHpi', 'Demam 3 hari'); // balik ke data tersimpan

        $this->assertSame('Demam 3 hari', $data['soap']->fresh()->s_hpi);
        $this->assertSame(0, $data['soap']->fresh()->revision_count);
    }
}

<?php

namespace Tests\Feature;

use App\Livewire\Pasien\PasienForm;
use App\Models\SumberInformasi;
use App\Services\PasienService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class PasienKontakDaruratMandatoryTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        SumberInformasi::create([
            'kode' => 'TST',
            'nama' => 'Test Source',
            'icon' => '📝',
            'kategori' => 'digital',
            'urutan' => 1,
            'is_active' => true,
        ]);
    }

    /** @test */
    public function kontak_darurat_wajib_tidak_boleh_kosong(): void
    {
        $sumber = SumberInformasi::first();

        Livewire::test(PasienForm::class)
            ->set('nama', 'Test Pasien')
            ->set('tempat_lahir', 'Jakarta')
            ->set('tanggal_lahir', '1990-01-01')
            ->set('jenis_kelamin', 'L')
            ->set('tipe_pasien', 'WNI')
            ->set('nik', '1234567890123456')
            ->set('alamat', 'Jl. Test')
            ->set('telepon', '081234567890')
            ->set('sumberInformasiId', $sumber->id)
            ->set('kontak', [])
            ->call('save')
            ->assertHasErrors('kontak');
    }

    /** @test */
    public function kontak_darurat_dengan_data_lengkap_berhasil(): void
    {
        $sumber = SumberInformasi::first();

        Livewire::test(PasienForm::class)
            ->set('nama', 'Test Pasien')
            ->set('tempat_lahir', 'Jakarta')
            ->set('tanggal_lahir', '1990-01-01')
            ->set('jenis_kelamin', 'L')
            ->set('tipe_pasien', 'WNI')
            ->set('nik', '1234567890123456')
            ->set('alamat', 'Jl. Test')
            ->set('telepon', '081234567890')
            ->set('sumberInformasiId', $sumber->id)
            ->set('kontak', [
                [
                    'nama' => 'Istri',
                    'nomor_hp' => '081234567891',
                    'hubungan' => 'istri',
                    'alamat' => '',
                    'is_primary' => true,
                ]
            ])
            ->call('save')
            ->assertHasNoErrors();
    }

    /** @test */
    public function nama_kontak_wajib_diisi(): void
    {
        $sumber = SumberInformasi::first();

        Livewire::test(PasienForm::class)
            ->set('nama', 'Test Pasien')
            ->set('tempat_lahir', 'Jakarta')
            ->set('tanggal_lahir', '1990-01-01')
            ->set('jenis_kelamin', 'L')
            ->set('tipe_pasien', 'WNI')
            ->set('nik', '1234567890123456')
            ->set('alamat', 'Jl. Test')
            ->set('telepon', '081234567890')
            ->set('sumberInformasiId', $sumber->id)
            ->set('kontak', [
                [
                    'nama' => '',
                    'nomor_hp' => '081234567891',
                    'hubungan' => 'istri',
                    'alamat' => '',
                    'is_primary' => true,
                ]
            ])
            ->call('save')
            ->assertHasErrors('kontak.0.nama');
    }

    /** @test */
    public function nomor_hp_kontak_wajib_diisi(): void
    {
        $sumber = SumberInformasi::first();

        Livewire::test(PasienForm::class)
            ->set('nama', 'Test Pasien')
            ->set('tempat_lahir', 'Jakarta')
            ->set('tanggal_lahir', '1990-01-01')
            ->set('jenis_kelamin', 'L')
            ->set('tipe_pasien', 'WNI')
            ->set('nik', '1234567890123456')
            ->set('alamat', 'Jl. Test')
            ->set('telepon', '081234567890')
            ->set('sumberInformasiId', $sumber->id)
            ->set('kontak', [
                [
                    'nama' => 'Istri',
                    'nomor_hp' => '',
                    'hubungan' => 'istri',
                    'alamat' => '',
                    'is_primary' => true,
                ]
            ])
            ->call('save')
            ->assertHasErrors('kontak.0.nomor_hp');
    }

    /** @test */
    public function multiple_kontak_darurat_diperbolehkan(): void
    {
        $sumber = SumberInformasi::first();

        Livewire::test(PasienForm::class)
            ->set('nama', 'Test Pasien')
            ->set('tempat_lahir', 'Jakarta')
            ->set('tanggal_lahir', '1990-01-01')
            ->set('jenis_kelamin', 'L')
            ->set('tipe_pasien', 'WNI')
            ->set('nik', '1234567890123456')
            ->set('alamat', 'Jl. Test')
            ->set('telepon', '081234567890')
            ->set('sumberInformasiId', $sumber->id)
            ->set('kontak', [
                [
                    'nama' => 'Istri',
                    'nomor_hp' => '081234567891',
                    'hubungan' => 'istri',
                    'alamat' => '',
                    'is_primary' => true,
                ],
                [
                    'nama' => 'Ibu',
                    'nomor_hp' => '081234567892',
                    'hubungan' => 'ibu',
                    'alamat' => '',
                    'is_primary' => false,
                ]
            ])
            ->call('save')
            ->assertHasNoErrors();
    }

    /** @test */
    public function service_membuat_pasien_dengan_kontak(): void
    {
        $service = app(PasienService::class);

        $pasien = $service->create([
            'nama' => 'Test Service',
            'tempat_lahir' => 'Jakarta',
            'tanggal_lahir' => '1990-01-01',
            'jenis_kelamin' => 'L',
            'tipe_pasien' => 'WNI',
            'nik' => '9876543210123456',
            'alamat' => 'Jl. Test',
            'telepon' => '081234567890',
        ], [
            ['nama' => 'Istri', 'nomor_hp' => '081234567899', 'hubungan' => 'istri'],
        ]);

        $this->assertNotNull($pasien->id);
        $this->assertCount(1, $pasien->kontakDarurat);
        $this->assertTrue($pasien->kontakDarurat->first()->is_primary);
    }

    /** @test */
    public function nomor_hp_kontak_tidak_boleh_sama_dengan_nomor_pasien(): void
    {
        $sumber = SumberInformasi::first();

        Livewire::test(PasienForm::class)
            ->set('nama', 'Test Pasien')
            ->set('tempat_lahir', 'Jakarta')
            ->set('tanggal_lahir', '1990-01-01')
            ->set('jenis_kelamin', 'L')
            ->set('tipe_pasien', 'WNI')
            ->set('nik', '1234567890123456')
            ->set('alamat', 'Jl. Test')
            ->set('telepon', '081234567890')
            ->set('sumberInformasiId', $sumber->id)
            ->set('kontak', [
                [
                    'nama' => 'Istri',
                    'nomor_hp' => '081234567890',  // SAME AS PATIENT PHONE!
                    'hubungan' => 'istri',
                    'alamat' => '',
                    'is_primary' => true,
                ]
            ])
            ->call('save')
            ->assertHasErrors('kontak.0.nomor_hp');
    }

    /** @test */
    public function tidak_bisa_hapus_kontak_terakhir(): void
    {
        $sumber = SumberInformasi::first();

        Livewire::test(PasienForm::class)
            ->set('nama', 'Test Pasien')
            ->set('tempat_lahir', 'Jakarta')
            ->set('tanggal_lahir', '1990-01-01')
            ->set('jenis_kelamin', 'L')
            ->set('tipe_pasien', 'WNI')
            ->set('nik', '1234567890123456')
            ->set('alamat', 'Jl. Test')
            ->set('telepon', '081234567890')
            ->set('sumberInformasiId', $sumber->id)
            ->set('kontak', [
                [
                    'nama' => 'Istri',
                    'nomor_hp' => '081234567891',
                    'hubungan' => 'istri',
                    'alamat' => '',
                    'is_primary' => true,
                ]
            ])
            ->call('removeKontak', 0)
            ->assertHasErrors('kontak');
    }

    /** @test */
    public function bisa_hapus_kontak_jika_ada_lebih_dari_satu(): void
    {
        $sumber = SumberInformasi::first();

        $component = Livewire::test(PasienForm::class)
            ->set('nama', 'Test Pasien')
            ->set('tempat_lahir', 'Jakarta')
            ->set('tanggal_lahir', '1990-01-01')
            ->set('jenis_kelamin', 'L')
            ->set('tipe_pasien', 'WNI')
            ->set('nik', '1234567890123456')
            ->set('alamat', 'Jl. Test')
            ->set('telepon', '081234567890')
            ->set('sumberInformasiId', $sumber->id)
            ->set('kontak', [
                [
                    'nama' => 'Istri',
                    'nomor_hp' => '081234567891',
                    'hubungan' => 'istri',
                    'alamat' => '',
                    'is_primary' => true,
                ],
                [
                    'nama' => 'Ibu',
                    'nomor_hp' => '081234567892',
                    'hubungan' => 'ibu',
                    'alamat' => '',
                    'is_primary' => false,
                ]
            ]);

        $this->assertCount(2, $component->get('kontak'));
        
        $component->call('removeKontak', 1)
            ->assertHasNoErrors();
        
        $this->assertCount(1, $component->get('kontak'));
    }

    /** @test */
    public function duplikasi_nama_kontak_ditolak(): void
    {
        $sumber = SumberInformasi::first();

        Livewire::test(PasienForm::class)
            ->set('nama', 'Test Pasien')
            ->set('tempat_lahir', 'Jakarta')
            ->set('tanggal_lahir', '1990-01-01')
            ->set('jenis_kelamin', 'L')
            ->set('tipe_pasien', 'WNI')
            ->set('nik', '1234567890123456')
            ->set('alamat', 'Jl. Test')
            ->set('telepon', '081234567890')
            ->set('sumberInformasiId', $sumber->id)
            ->set('kontak', [
                [
                    'nama' => 'Istri',
                    'nomor_hp' => '081234567891',
                    'hubungan' => 'istri',
                    'alamat' => '',
                    'is_primary' => true,
                ],
                [
                    'nama' => 'Istri',  // DUPLIKAT NAMA!
                    'nomor_hp' => '081234567892',
                    'hubungan' => 'ibu',
                    'alamat' => '',
                    'is_primary' => false,
                ]
            ])
            ->call('save')
            ->assertHasErrors('kontak.1.nama');
    }

    /** @test */
    public function duplikasi_nomor_hp_kontak_ditolak(): void
    {
        $sumber = SumberInformasi::first();

        Livewire::test(PasienForm::class)
            ->set('nama', 'Test Pasien')
            ->set('tempat_lahir', 'Jakarta')
            ->set('tanggal_lahir', '1990-01-01')
            ->set('jenis_kelamin', 'L')
            ->set('tipe_pasien', 'WNI')
            ->set('nik', '1234567890123456')
            ->set('alamat', 'Jl. Test')
            ->set('telepon', '081234567890')
            ->set('sumberInformasiId', $sumber->id)
            ->set('kontak', [
                [
                    'nama' => 'Istri',
                    'nomor_hp' => '081234567891',
                    'hubungan' => 'istri',
                    'alamat' => '',
                    'is_primary' => true,
                ],
                [
                    'nama' => 'Ibu',
                    'nomor_hp' => '081234567891',  // DUPLIKAT NO HP!
                    'hubungan' => 'ibu',
                    'alamat' => '',
                    'is_primary' => false,
                ]
            ])
            ->call('save')
            ->assertHasErrors('kontak.1.nomor_hp');
    }

    /** @test */
    public function konfirmasi_hapus_kontak_dapat_dibatalkan(): void
    {
        $sumber = SumberInformasi::first();

        $component = Livewire::test(PasienForm::class)
            ->set('nama', 'Test Pasien')
            ->set('tempat_lahir', 'Jakarta')
            ->set('tanggal_lahir', '1990-01-01')
            ->set('jenis_kelamin', 'L')
            ->set('tipe_pasien', 'WNI')
            ->set('nik', '1234567890123456')
            ->set('alamat', 'Jl. Test')
            ->set('telepon', '081234567890')
            ->set('sumberInformasiId', $sumber->id)
            ->set('kontak', [
                [
                    'nama' => 'Istri',
                    'nomor_hp' => '081234567891',
                    'hubungan' => 'istri',
                    'alamat' => '',
                    'is_primary' => true,
                ],
                [
                    'nama' => 'Ibu',
                    'nomor_hp' => '081234567892',
                    'hubungan' => 'ibu',
                    'alamat' => '',
                    'is_primary' => false,
                ]
            ]);

        // Click konfirmasi delete
        $component->call('confirmDeleteKontak', 1)
            ->assertSet('showDeleteConfirm', true)
            ->assertSet('deleteKontakIndex', 1);

        // Cancel delete
        $component->call('cancelDeleteKontak')
            ->assertSet('showDeleteConfirm', false)
            ->assertSet('deleteKontakIndex', null);

        // Kontak masih ada (tidak dihapus)
        $this->assertCount(2, $component->get('kontak'));
    }

    /** @test */
    public function copy_nomor_hp_kontak_menandai_sebagai_copied(): void
    {
        $sumber = SumberInformasi::first();

        $component = Livewire::test(PasienForm::class)
            ->set('nama', 'Test Pasien')
            ->set('tempat_lahir', 'Jakarta')
            ->set('tanggal_lahir', '1990-01-01')
            ->set('jenis_kelamin', 'L')
            ->set('tipe_pasien', 'WNI')
            ->set('nik', '1234567890123456')
            ->set('alamat', 'Jl. Test')
            ->set('telepon', '081234567890')
            ->set('sumberInformasiId', $sumber->id)
            ->set('kontak', [
                [
                    'nama' => 'Istri',
                    'nomor_hp' => '081234567891',
                    'hubungan' => 'istri',
                    'alamat' => '',
                    'is_primary' => true,
                ]
            ]);

        // Initial state: no copied phone
        $this->assertNull($component->get('copiedPhoneIndex'));

        // Copy phone number
        $component->call('copyPhoneNumber', 0)
            ->assertSet('copiedPhoneIndex', 0);
    }
}

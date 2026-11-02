<?php

namespace Tests\Feature;

use App\Livewire\Pemeriksaan\Penunjang;
use App\Livewire\Pemeriksaan\Prosedur;
use App\Livewire\Pemeriksaan\SoapNote as SoapNoteLivewire;
use App\Models\Barang;
use App\Models\Dokter;
use App\Models\Invoice;
use App\Models\ItemPenunjang;
use App\Models\Kunjungan;
use App\Models\Pasien;
use App\Models\PemakaianAlkes;
use App\Models\PermintaanPenunjang;
use App\Models\SoapNote as SoapNoteModel;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Setelah bill lunas, dokter hanya boleh merevisi data yang TIDAK berhubungan
 * dengan billing: isi SOAP tetap bisa direvisi, tetapi tindakan/alkes/lab/
 * radiologi (yang membentuk tagihan) terkunci.
 *
 * Pakai DatabaseTransactions -- bukan RefreshDatabase.
 */
class RevisiSoapBillLunasTest extends TestCase
{
    use DatabaseTransactions;

    private User $dokterUser;
    private Kunjungan $kunjungan;
    private SoapNoteModel $soap;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dokterUser = User::create([
            'nama' => 'Dr. Test ' . uniqid(), 'email' => 'dokter-' . uniqid() . '@example.test',
            'password' => Hash::make('password'), 'is_active' => true,
        ]);
        $this->dokterUser->assignRole('dokter');
        $dokter = Dokter::create(['user_id' => $this->dokterUser->id]);
        $pasien = Pasien::create([
            'nomor_rm' => 'RM-' . uniqid(), 'nama' => 'Pasien Test', 'tempat_lahir' => 'Denpasar',
            'tanggal_lahir' => '1990-01-01', 'jenis_kelamin' => 'L', 'alamat' => 'Jl. Test', 'telepon' => '08123',
        ]);
        $this->kunjungan = Kunjungan::create([
            'nomor_antrean' => 'A-' . uniqid(), 'pasien_id' => $pasien->id, 'dokter_id' => $dokter->id,
            'status' => 'selesai', 'tanggal' => now(),
        ]);
        $this->soap = SoapNoteModel::create([
            'kunjungan_id' => $this->kunjungan->id, 's_hpi' => 'Demam 3 hari',
            'icd_codes' => [['kode' => 'A09', 'nama' => 'Diare', 'is_primary' => true]],
            'is_final' => true, 'finalized_at' => now(), 'finalized_by' => $this->dokterUser->id,
        ]);
        $this->actingAs($this->dokterUser);
    }

    private function lunasi(): void
    {
        Invoice::create([
            'kunjungan_id' => $this->kunjungan->id, 'nomor_invoice' => 'INV-' . uniqid(),
            'total_tagihan' => 100000, 'total_bayar' => 100000, 'sisa' => 0, 'status' => 'lunas',
        ]);
    }

    /** @test */
    public function isi_soap_tetap_bisa_direvisi_setelah_bill_lunas(): void
    {
        $this->lunasi();

        Livewire::test(SoapNoteLivewire::class, ['kunjunganId' => $this->kunjungan->id])
            ->call('mulaiRevisi')
            ->set('alasanRevisi', 'Koreksi anamnesis setelah cross-check')
            ->call('konfirmasiRevisi')
            ->set('sHpi', 'Demam 5 hari')
            ->call('simpanRevisi');

        $this->assertSame('Demam 5 hari', $this->soap->fresh()->s_hpi);
    }

    /** @test */
    public function alkes_tidak_bisa_ditambah_setelah_bill_lunas_tetapi_bisa_sebelumnya(): void
    {
        $barang = Barang::create([
            'kode' => 'ALK' . rand(1000, 9999), 'nama' => 'Kasa Steril', 'jenis' => 'alkes',
            'satuan' => 'pcs', 'stok' => 50, 'harga_pokok' => 1000, 'harga_jual' => 2000, 'is_active' => true,
        ]);
        $jalankan = fn () => Livewire::test(Prosedur::class, ['kunjunganId' => $this->kunjungan->id])
            ->call('pilihAlkes', $barang->id, $barang->nama, 'pcs')
            ->call('simpanAlkes');

        $this->lunasi();
        $jalankan();
        $this->assertSame(0, PemakaianAlkes::where('kunjungan_id', $this->kunjungan->id)->count());

        Invoice::where('kunjungan_id', $this->kunjungan->id)->update(['status' => 'belum_bayar']);
        $jalankan();
        $this->assertSame(1, PemakaianAlkes::where('kunjungan_id', $this->kunjungan->id)->count());
    }

    /** @test */
    public function order_lab_tidak_bisa_ditambah_setelah_bill_lunas(): void
    {
        $item = ItemPenunjang::create([
            'kode' => 'LAB' . rand(1000, 9999), 'nama' => 'Darah Lengkap', 'kategori' => 'lab', 'tarif' => 85000, 'is_active' => true,
        ]);
        $this->lunasi();

        Livewire::test(Penunjang::class, ['kunjunganId' => $this->kunjungan->id])
            ->call('addToCartLab', $item->id, $item->kode, $item->nama, '85000')
            ->call('submitLab');

        $this->assertSame(0, PermintaanPenunjang::where('kunjungan_id', $this->kunjungan->id)->count());
    }

    /** @test */
    public function radiologi_dipesan_tidak_bisa_dibatalkan_setelah_bill_lunas(): void
    {
        $rad = ItemPenunjang::create([
            'kode' => 'RAD' . rand(1000, 9999), 'nama' => 'Rontgen Thorax', 'kategori' => 'radiologi', 'tarif' => 150000, 'is_active' => true,
        ]);
        $order = PermintaanPenunjang::create([
            'kunjungan_id' => $this->kunjungan->id, 'item_penunjang_id' => $rad->id,
            'jumlah' => 1, 'status' => 'dipesan', 'ordered_by' => $this->dokterUser->id,
        ]);
        $this->lunasi();

        Livewire::test(Penunjang::class, ['kunjunganId' => $this->kunjungan->id])
            ->call('batalkan', $order->id);

        $this->assertSame('dipesan', $order->fresh()->status);
    }
}

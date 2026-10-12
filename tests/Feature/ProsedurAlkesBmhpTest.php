<?php

namespace Tests\Feature;

use App\Livewire\Pemeriksaan\Prosedur;
use App\Models\Barang;
use App\Models\Dokter;
use App\Models\Kunjungan;
use App\Models\Pasien;
use App\Models\PemakaianAlkes;
use App\Models\Poli;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Bug laporan dokter (screenshot Internal Server Error): mengetik di field
 * "Nama Alat / BMHP" pada tab Prosedur langsung Internal Server Error --
 * SQLSTATE[42S02] "Table 'emr_db.obat' doesn't exist".
 *
 * Root cause: Prosedur::suggestionsAlkes()/simpanAlkes() masih menunjuk ke
 * App\Models\Obat (tabel 'obat'), padahal tabel itu sudah DIHAPUS TOTAL
 * sejak migrasi penggabungan obat->barang (2026_05_27_300002) -- seluruh
 * modul lain sudah migrasi ke App\Models\Barang, cuma Prosedur.php yg
 * terlewat. Bug kedua yg ikut ketemu: simpanAlkes() kirim key 'obat_id' ke
 * PemakaianAlkes::create(), padahal kolom itu sudah diganti 'barang_id'
 * (NOT NULL) di migrasi yang sama -- 'obat_id' bukan $fillable lagi, jadi
 * diam2 diabaikan Eloquent & insert selalu gagal krn barang_id tidak
 * pernah terisi. Praktis seluruh fitur "Tambah Pemakaian Alkes/BMHP"
 * tidak pernah berfungsi sejak migrasi itu (baik cari maupun simpan).
 *
 * Pakai DatabaseTransactions -- bukan RefreshDatabase.
 */
class ProsedurAlkesBmhpTest extends TestCase
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
        $poli   = Poli::create(['nama' => 'Poli Test ' . uniqid(), 'kode' => 'PT' . rand(1000, 9999), 'is_active' => true]);
        $pasien = Pasien::create([
            'nomor_rm' => 'RM-' . uniqid(), 'nama' => 'Pasien Test ' . uniqid(), 'tempat_lahir' => 'Denpasar',
            'tanggal_lahir' => '1990-01-01', 'jenis_kelamin' => 'L', 'alamat' => 'Jl. Test', 'telepon' => '08123',
        ]);
        $kunjungan = Kunjungan::create([
            'nomor_antrean' => 'W-' . rand(100, 999), 'pasien_id' => $pasien->id,
            'dokter_id' => $dokter->id, 'poli_id' => $poli->id,
            'tanggal' => now(), 'status' => 'dalam_pemeriksaan',
        ]);

        $this->actingAs($dokterUser);

        return compact('dokterUser', 'dokter', 'kunjungan');
    }

    /** @test */
    public function mengetik_nama_alkes_bmhp_tidak_lagi_error_tabel_obat(): void
    {
        $ctx = $this->buatKunjungan();

        $alkes = Barang::create([
            'kode' => 'ALK' . rand(1000, 9999), 'nama' => 'Verban Elastis', 'jenis' => 'alkes',
            'satuan' => 'Rol', 'stok' => 20, 'harga_pokok' => 5000, 'harga_jual' => 8000, 'is_active' => true,
        ]);

        Livewire::test(Prosedur::class, ['kunjunganId' => $ctx['kunjungan']->id])
            ->set('searchAlkes', 'verban')
            ->assertSuccessful()
            ->assertSee('Verban Elastis');
    }

    /** @test */
    public function pencarian_alkes_bmhp_mencakup_jenis_alkes_dan_bahan_habis_pakai(): void
    {
        $ctx = $this->buatKunjungan();

        $alkes = Barang::create([
            'kode' => 'ALK' . rand(1000, 9999), 'nama' => 'Sarung Tangan Steril', 'jenis' => 'alkes',
            'satuan' => 'Pasang', 'stok' => 50, 'harga_pokok' => 2000, 'harga_jual' => 3000, 'is_active' => true,
        ]);
        $bhp = Barang::create([
            'kode' => 'BHP' . rand(1000, 9999), 'nama' => 'Sarung Tangan Lateks BHP', 'jenis' => 'bahan_habis_pakai',
            'satuan' => 'Pasang', 'stok' => 50, 'harga_pokok' => 1000, 'harga_jual' => 1500, 'is_active' => true,
        ]);
        $obatLain = Barang::create([
            'kode' => 'OBT' . rand(1000, 9999), 'nama' => 'Sarung Tangan Tidak Boleh Muncul', 'jenis' => 'obat',
            'satuan' => 'Tablet', 'stok' => 50, 'harga_pokok' => 500, 'harga_jual' => 1000, 'is_active' => true,
        ]);

        $hasil = Livewire::test(Prosedur::class, ['kunjunganId' => $ctx['kunjungan']->id])
            ->set('searchAlkes', 'sarung tangan')
            ->get('suggestionsAlkes');

        $ids = $hasil->pluck('id');
        $this->assertTrue($ids->contains($alkes->id), 'Jenis alkes harus ikut muncul.');
        $this->assertTrue($ids->contains($bhp->id), 'Jenis bahan_habis_pakai (BMHP) harus ikut muncul.');
        $this->assertFalse($ids->contains($obatLain->id), 'Jenis obat biasa tidak boleh ikut muncul di pencarian Alkes/BMHP.');
    }

    /** @test */
    public function simpan_pemakaian_alkes_berhasil_tersimpan_dengan_barang_id_benar(): void
    {
        $ctx = $this->buatKunjungan();

        $alkes = Barang::create([
            'kode' => 'ALK' . rand(1000, 9999), 'nama' => 'Kasa Steril', 'jenis' => 'alkes',
            'satuan' => 'Pcs', 'stok' => 30, 'harga_pokok' => 1000, 'harga_jual' => 2000, 'is_active' => true,
        ]);

        Livewire::test(Prosedur::class, ['kunjunganId' => $ctx['kunjungan']->id])
            ->call('pilihAlkes', $alkes->id, $alkes->nama, $alkes->satuan)
            ->set('jumlahAlkes', 3)
            ->call('simpanAlkes')
            ->assertSuccessful();

        $row = PemakaianAlkes::where('kunjungan_id', $ctx['kunjungan']->id)->first();
        $this->assertNotNull($row, 'PemakaianAlkes harus berhasil tersimpan (sebelumnya selalu gagal krn barang_id NULL).');
        $this->assertSame($alkes->id, $row->barang_id);
        $this->assertSame(3, $row->jumlah);
    }

    /** @test */
    public function simpan_pemakaian_alkes_memberi_peringatan_kalau_stok_kurang(): void
    {
        $ctx = $this->buatKunjungan();

        $alkes = Barang::create([
            'kode' => 'ALK' . rand(1000, 9999), 'nama' => 'Masker Bedah', 'jenis' => 'alkes',
            'satuan' => 'Pcs', 'stok' => 2, 'harga_pokok' => 500, 'harga_jual' => 1000, 'is_active' => true,
        ]);

        Livewire::test(Prosedur::class, ['kunjunganId' => $ctx['kunjungan']->id])
            ->call('pilihAlkes', $alkes->id, $alkes->nama, $alkes->satuan)
            ->set('jumlahAlkes', 5)
            ->call('simpanAlkes')
            ->assertDispatched('notify', type: 'warning');
    }
}

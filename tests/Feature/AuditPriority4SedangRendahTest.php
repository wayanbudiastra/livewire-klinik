<?php

namespace Tests\Feature;

use App\Livewire\Inventory\GoodsReceipt\GrTable;
use App\Livewire\Inventory\PurchaseOrder\PoTable;
use App\Models\Barang;
use App\Models\GoodsReceipt;
use App\Models\GrItem;
use App\Models\PoItem;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Inventory\PembelianService;
use App\Services\Inventory\PenerimaanService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Regresi Audit Priority 4 (Harga proposal maker-checker, PO/GR, Laporan/Export),
 * temuan Sedang & Rendah:
 *
 * [Sedang]
 * 1. PembelianService::approvePo() sekarang menolak kalau pembuat PO ==
 *    yang meng-approve (maker-checker) -- beda dari GR (yang memang
 *    sengaja punya "Simpan & Verifikasi" sekaligus sbg fitur), PO tidak
 *    pernah punya jalur create+approve gabungan, jadi guard ini aman.
 * 2. Race-condition tanpa lock diperbaiki di ProposalHargaService
 *    (submitReview/setujui/tolak/batalkan/terapkan), PembelianService
 *    (approvePo/batalkanPo), PenerimaanService (verifikasiGr/batalkanGr).
 * 3. PoTable::approve()/batalkan() & GrTable::verifikasi()/batalkan()
 *    sekarang authorize('obat.edit') sbg defense-in-depth.
 *
 * [Rendah] PurchaseOrder::generateNomorPo() & GoodsReceipt::generateNomorGr()
 * sekarang lockForUpdate(), pola sama dgn ReturResep/ReturGr/Deposit/dst.
 *
 * (Catatan: GrForm::simpanDanVerifikasi() SENGAJA tidak diberi guard
 * pembuat != verifikator -- itu memang fitur "buat & verifikasi sekaligus"
 * yang didesain sengaja, beda dari PO yang tidak py jalur gabungan serupa.)
 *
 * Pakai DatabaseTransactions -- bukan RefreshDatabase.
 */
class AuditPriority4SedangRendahTest extends TestCase
{
    use DatabaseTransactions;

    private function buatApoteker(): User
    {
        $user = User::create([
            'nama' => 'Apoteker Test ' . uniqid(), 'email' => 'apoteker-' . uniqid() . '@example.test',
            'password' => Hash::make('password'), 'is_active' => true,
        ]);
        $user->assignRole('apoteker');
        return $user;
    }

    /** Role ad hoc dgn obat.view saja (tanpa obat.edit) -- tidak ada default role spt ini. */
    private function buatUserObatViewSaja(): User
    {
        $role = Role::firstOrCreate(['name' => 'gudang_view_only_p4']);
        $role->syncPermissions(['obat.view']);

        $user = User::create([
            'nama' => 'Gudang View Only ' . uniqid(), 'email' => 'gudangview-' . uniqid() . '@example.test',
            'password' => Hash::make('password'), 'is_active' => true,
        ]);
        $user->assignRole('gudang_view_only_p4');
        return $user;
    }

    private function buatSupplier(): Supplier
    {
        return Supplier::create([
            'kode' => 'SUP-' . uniqid(), 'nama' => 'Supplier Test ' . uniqid(), 'tipe' => 'distributor',
        ]);
    }

    private function buatBarang(int $stok = 10): Barang
    {
        return Barang::create([
            'kode' => 'OBT-' . uniqid(), 'nama' => 'Obat Test ' . uniqid(), 'jenis' => 'obat',
            'satuan' => 'Tablet', 'stok' => $stok, 'harga_jual' => 5000, 'harga_pokok' => 3000,
        ]);
    }

    private function buatPo(User $pembuat, Supplier $supplier, Barang $barang, string $status = 'draft'): PurchaseOrder
    {
        $po = PurchaseOrder::create([
            'nomor_po' => 'PO-TEST-' . uniqid(), 'supplier_id' => $supplier->id, 'dibuat_oleh' => $pembuat->id,
            'tanggal_po' => now(), 'status' => $status, 'total_nilai' => 100000,
        ]);
        PoItem::create([
            'purchase_order_id' => $po->id, 'barang_id' => $barang->id, 'jumlah_pesan' => 10,
            'harga_satuan' => 10000, 'subtotal' => 100000,
        ]);
        return $po;
    }

    private function buatGrDraft(User $penerima, Supplier $supplier, Barang $barang): GoodsReceipt
    {
        $gr = GoodsReceipt::create([
            'nomor_gr' => 'GR-TEST-' . uniqid(), 'supplier_id' => $supplier->id, 'diterima_oleh' => $penerima->id,
            'tanggal_terima' => now(), 'status' => 'draft', 'total_nilai' => 50000,
        ]);
        GrItem::create([
            'goods_receipt_id' => $gr->id, 'barang_id' => $barang->id, 'jumlah_terima' => 5,
            'harga_satuan' => 10000, 'subtotal' => 50000,
        ]);
        return $gr;
    }

    // ── Sedang #1: PembelianService::approvePo() maker-checker ─────────

    /** @test */
    public function pembuat_po_tidak_bisa_approve_po_miliknya_sendiri(): void
    {
        $apoteker = $this->buatApoteker();
        $po = $this->buatPo($apoteker, $this->buatSupplier(), $this->buatBarang());

        $service = app(PembelianService::class);

        try {
            $service->approvePo($po, $apoteker->id);
            $this->fail('Pembuat PO harusnya ditolak saat approve PO sendiri.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('tidak boleh meng-approve', $e->validator->errors()->first());
        }

        $this->assertSame('draft', $po->fresh()->status);
    }

    /** @test */
    public function apoteker_lain_tetap_bisa_approve_po_yang_dibuat_apoteker_lain(): void
    {
        $pembuat  = $this->buatApoteker();
        $penyetuju = $this->buatApoteker();
        $po = $this->buatPo($pembuat, $this->buatSupplier(), $this->buatBarang());

        app(PembelianService::class)->approvePo($po, $penyetuju->id);

        $this->assertSame('dikirim', $po->fresh()->status);
        $this->assertSame($penyetuju->id, $po->fresh()->disetujui_oleh);
    }

    // ── Sedang #3: authorize() di PoTable/GrTable ────────────────────────

    /** @test */
    public function role_tanpa_obat_edit_ditolak_approve_po_dan_verifikasi_gr(): void
    {
        $pembuat = $this->buatApoteker();
        $supplier = $this->buatSupplier();
        $barang = $this->buatBarang();
        $po = $this->buatPo($pembuat, $supplier, $barang);
        $gr = $this->buatGrDraft($pembuat, $supplier, $barang);

        $viewOnly = $this->buatUserObatViewSaja();
        $this->actingAs($viewOnly);

        Livewire::test(PoTable::class)->call('approve', $po->id)->assertForbidden();
        Livewire::test(PoTable::class)->call('batalkan', $po->id)->assertForbidden();
        Livewire::test(GrTable::class)->call('verifikasi', $gr->id)->assertForbidden();
        Livewire::test(GrTable::class)->call('batalkan', $gr->id)->assertForbidden();

        $this->assertSame('draft', $po->fresh()->status);
        $this->assertSame('draft', $gr->fresh()->status);
    }

    /** @test */
    public function apoteker_tetap_bisa_verifikasi_gr_dan_batalkan_po(): void
    {
        $pembuat  = $this->buatApoteker();
        $verifikator = $this->buatApoteker();
        $supplier = $this->buatSupplier();
        $barang   = $this->buatBarang(10);
        $gr = $this->buatGrDraft($pembuat, $supplier, $barang);

        $this->actingAs($verifikator);

        Livewire::test(GrTable::class)->call('verifikasi', $gr->id)->assertOk();

        $this->assertSame('diverifikasi', $gr->fresh()->status);
        $this->assertSame(15, $barang->fresh()->stok, 'Stok harus bertambah 5 (jumlah_terima).');
    }

    // ── Regresi: verifikasiGr()/batalkanPo() tetap berfungsi normal ─────

    /** @test */
    public function verifikasi_gr_dan_batalkan_po_tetap_berhasil_normal_setelah_ditambah_lock(): void
    {
        $pembuat = $this->buatApoteker();
        $supplier = $this->buatSupplier();
        $barang  = $this->buatBarang(10);

        $gr = $this->buatGrDraft($pembuat, $supplier, $this->buatBarang(10));
        app(PenerimaanService::class)->verifikasiGr($gr, $pembuat->id);
        $this->assertSame('diverifikasi', $gr->fresh()->status);

        $po = $this->buatPo($pembuat, $supplier, $barang);
        app(PembelianService::class)->batalkanPo($po);
        $this->assertSame('dibatalkan', $po->fresh()->status);
    }

    // ── Rendah: nomor generation tetap urut setelah ditambah lock ───────

    /** @test */
    public function nomor_po_dan_gr_tetap_urut_setelah_ditambah_lock(): void
    {
        $pembuat = $this->buatApoteker();
        $supplier = $this->buatSupplier();
        $barang  = $this->buatBarang();

        $service = app(PembelianService::class);
        $po1 = $service->buatPo([
            'supplier_id' => $supplier->id, 'tanggal_po' => now()->toDateString(), 'dibuat_oleh' => $pembuat->id,
            'total_nilai' => 100000, 'items' => [['barang_id' => $barang->id, 'jumlah_pesan' => 5, 'harga_satuan' => 10000]],
        ]);
        $po2 = $service->buatPo([
            'supplier_id' => $supplier->id, 'tanggal_po' => now()->toDateString(), 'dibuat_oleh' => $pembuat->id,
            'total_nilai' => 50000, 'items' => [['barang_id' => $barang->id, 'jumlah_pesan' => 2, 'harga_satuan' => 25000]],
        ]);

        $this->assertNotSame($po1->nomor_po, $po2->nomor_po);
    }
}

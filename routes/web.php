<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));

Route::middleware(['auth', 'active'])->group(function () {

    // Dashboard
    Route::get('/dashboard', fn () => view('dashboard'))->name('dashboard');

    // Manajemen Pasien
    Route::prefix('pasien')->name('pasien.')->group(function () {

        // ⚠ Route statis WAJIB sebelum wildcard {pasien}
        Route::middleware('permission:pasien.view')
             ->get('/', fn () => view('pasien.index'))->name('index');

        Route::middleware('permission:pasien.create')
             ->get('/create', fn () => view('pasien.create'))->name('create');

        // Wildcard — harus paling bawah
        Route::middleware('permission:pasien.view')
             ->get('/{pasien}', function ($pasien) {
                $p = \App\Models\Pasien::with(['kontakDarurat',
                    'kunjungan' => fn ($q) => $q->with(['poli:id,nama','dokter.user:id,nama'])->take(10)
                ])->findOrFail($pasien);
                return view('pasien.show', ['pasien' => $p]);
             })->name('show');

        Route::middleware('permission:pasien.edit')
             ->get('/{pasien}/edit', function ($pasien) {
                $p = \App\Models\Pasien::findOrFail($pasien);
                return view('pasien.edit', ['pasien' => $p]);
             })->name('edit');
    });

    // Pendaftaran & Kunjungan
    Route::prefix('kunjungan')->name('kunjungan.')->middleware('permission:kunjungan.view')->group(function () {
        Route::get('/', fn () => view('kunjungan.index'))->name('index');
        Route::get('/pendaftaran', fn () => redirect()->route('kunjungan.index', ['tab' => 'pendaftaran']))->name('pendaftaran');
    });

    // Pemeriksaan
    Route::get('/pemeriksaan', fn () => view('pemeriksaan.index'))
        ->middleware('permission:asesmen.view')
        ->name('pemeriksaan.index');

    // Rawat Inap (dalam pengembangan)
    Route::get('/rawat-inap', fn () => view('coming-soon', [
        'modul'      => 'Rawat Inap',
        'deskripsi'  => 'Modul admisi pasien, manajemen kamar & bed, CPPT, dan discharge planning.',
        'progress'   => 5,
        'roadmap'    => ['Admisi pasien rawat inap', 'Manajemen kamar & bed', 'CPPT harian', 'Discharge planning', 'Surat keterangan rawat inap'],
    ]))->name('rawat-inap.index');

    // Farmasi
    Route::prefix('farmasi')->name('farmasi.')->middleware('permission:obat.view')->group(function () {

        // Stok Obat & Master Data → IMPLEMENTASI
        Route::get('/stok-obat', fn () => view('farmasi.index'))->name('stok.index');

        // Resep
        Route::get('/resep', fn () => view('farmasi.resep'))->name('resep.index');

        // Penjualan Ritel
        Route::prefix('ritel')->name('ritel.')->group(function () {
            Route::get('/', fn () => view('farmasi.ritel.index'))->name('index');

            Route::get('/create', fn () => view('farmasi.ritel.create'))
                 ->name('create')
                 ->middleware('permission:obat.create');

            Route::get('/{id}', function ($id) {
                $tr = \App\Models\TransaksiRitel::with(['items.barang', 'apoteker', 'kasir', 'pasien'])
                        ->findOrFail($id);
                return view('farmasi.ritel.show', compact('tr'));
            })->name('show');

            Route::get('/{id}/edit', function ($id) {
                $tr = \App\Models\TransaksiRitel::findOrFail($id);
                abort_unless($tr->status === 'draft', 403, 'Hanya transaksi draft yang bisa diedit.');
                return view('farmasi.ritel.edit', compact('tr'));
            })->name('edit')
              ->middleware('permission:obat.create');

            Route::get('/{id}/print', function ($id) {
                $tr = \App\Models\TransaksiRitel::with(['items.barang', 'apoteker', 'kasir', 'pasien'])
                      ->findOrFail($id);
                abort_unless(in_array($tr->status, ['dibayar', 'selesai']), 403, 'Struk hanya tersedia untuk transaksi yang sudah dibayar.');
                return view('farmasi.ritel.invoice-print', compact('tr'));
            })->name('print');
        });
    });

    // Billing & Kasir
    Route::get('/billing', fn () => view('kasir.index'))->name('billing.index');
    Route::get('/billing/invoice/{billing}/print', function (\App\Models\Invoice $billing) {
        $invoice = $billing->load([
            'kunjungan.pasien',
            'kunjungan.dokter',
            'kunjungan.poli',
            'items',
            'pembayaran',
            'shift.user',
        ]);
        return view('kasir.invoice-print', compact('invoice'));
    })->name('invoice.print');

    // Kasir v2 — Billing Detail, Split Payment, Cetak
    Route::prefix('kasir')->name('kasir.')->group(function () {
        Route::get('/billing', fn () => view('kasir.index'))->name('billing.index');

        Route::get('/billing/{billing}', function (\App\Models\Invoice $billing) {
            $billing->load('kunjungan.pasien');
            return view('kasir.billing-show', compact('billing'));
        })->name('billing.show');

        Route::get('/billing/{billing}/split-payment', function (\App\Models\Invoice $billing) {
            $billing->load('kunjungan.pasien');
            return view('kasir.split-payment', compact('billing'));
        })->name('billing.split-payment');

        Route::get('/billing/{billing}/cetak', function (\App\Models\Invoice $billing) {
            $service = app(\App\Services\Kasir\CetakInvoiceService::class);
            return $service->cetak($billing, auth()->id());
        })->name('billing.cetak');
    });

    // ── Laporan ─────────────────────────────────────────────
    Route::prefix('laporan')->name('laporan.')->group(function () {

        Route::get('/', function () {
            $user = auth()->user();
            if ($user->can('laporan.registrasi.view')) return redirect()->route('laporan.registrasi.kunjungan');
            if ($user->can('laporan.pemeriksaan.view')) return redirect()->route('laporan.pemeriksaan.diagnosa');
            if ($user->can('laporan.kasir.view'))       return redirect()->route('laporan.kasir.transaksi');
            if ($user->can('laporan.pharmacy.view'))    return redirect()->route('laporan.pharmacy.resep');
            abort(403);
        })->name('index');

        // Registrasi
        Route::middleware('permission:laporan.registrasi.view')->prefix('registrasi')->name('registrasi.')->group(function () {
            Route::get('/kunjungan',        [\App\Http\Controllers\Laporan\LaporanRegistrasiController::class, 'kunjungan'])->name('kunjungan');
            Route::get('/batal',            [\App\Http\Controllers\Laporan\LaporanRegistrasiController::class, 'batal'])->name('batal');
            Route::get('/appointment',      [\App\Http\Controllers\Laporan\LaporanRegistrasiController::class, 'appointment'])->name('appointment');
            Route::get('/warga-negara',     [\App\Http\Controllers\Laporan\LaporanRegistrasiController::class, 'wargaNegara'])->name('warga-negara');
            Route::get('/sumber-informasi', [\App\Http\Controllers\Laporan\LaporanRegistrasiController::class, 'sumberInformasi'])->name('sumber-informasi');
        });

        // Pemeriksaan
        Route::middleware('permission:laporan.pemeriksaan.view')->prefix('pemeriksaan')->name('pemeriksaan.')->group(function () {
            Route::get('/diagnosa', [\App\Http\Controllers\Laporan\LaporanPemeriksaanController::class, 'diagnosa'])->name('diagnosa');
            Route::get('/tindakan', [\App\Http\Controllers\Laporan\LaporanPemeriksaanController::class, 'tindakan'])->name('tindakan');
            Route::get('/poli',     [\App\Http\Controllers\Laporan\LaporanPemeriksaanController::class, 'poli'])->name('poli');
            Route::get('/dokter',   [\App\Http\Controllers\Laporan\LaporanPemeriksaanController::class, 'dokter'])->name('dokter');
        });

        // Kasir
        Route::middleware('permission:laporan.kasir.view')->prefix('kasir')->name('kasir.')->group(function () {
            Route::get('/transaksi',   [\App\Http\Controllers\Laporan\LaporanKasirController::class, 'transaksi'])->name('transaksi');
            Route::get('/cancel-bill', [\App\Http\Controllers\Laporan\LaporanKasirController::class, 'cancelBill'])->name('cancel-bill');
            Route::get('/deposit',     [\App\Http\Controllers\Laporan\LaporanKasirController::class, 'deposit'])->name('deposit');
        });

        // Pharmacy
        Route::middleware('permission:laporan.pharmacy.view')->prefix('pharmacy')->name('pharmacy.')->group(function () {
            Route::get('/resep',          [\App\Http\Controllers\Laporan\LaporanPharmacyController::class, 'resep'])->name('resep');
            Route::get('/fast-moving',    [\App\Http\Controllers\Laporan\LaporanPharmacyController::class, 'fastMoving'])->name('fast-moving');
            Route::get('/nilai-inventory',[\App\Http\Controllers\Laporan\LaporanPharmacyController::class, 'nilaiInventory'])->name('nilai-inventory');
        });
    });

    // ── Inventory ───────────────────────────────────────────
    Route::prefix('inventory')->name('inventory.')->middleware('permission:obat.view')->group(function () {
        Route::get('/', fn () => view('inventory.index'))->name('index');

        // PO
        Route::get('/po', fn () => view('inventory.index', ['tab' => 'po']))->name('po.index');
        Route::get('/po/create', fn () => view('inventory.po-create'))->name('po.create');

        // GR
        Route::get('/gr', fn () => view('inventory.index', ['tab' => 'gr']))->name('gr.index');
        Route::get('/gr/create', fn () => view('inventory.gr-create'))->name('gr.create');

        // Kartu Stok
        Route::prefix('kartu-stok')->name('kartu-stok.')->group(function () {
            Route::get('/', fn () => view('inventory.kartu-stok'))->name('index');
            Route::get('/export-pdf',   [\App\Http\Controllers\Inventory\KartuStokController::class, 'exportPdf'])->name('export-pdf');
            Route::get('/export-excel', [\App\Http\Controllers\Inventory\KartuStokController::class, 'exportExcel'])->name('export-excel');
        });

        // Pemakaian BHP
        Route::prefix('bhp')->name('bhp.')->group(function () {
            Route::get('/',        fn () => view('inventory.bhp.index'))->name('index');
            Route::get('/create',  fn () => view('inventory.bhp.create'))
                 ->name('create')
                 ->middleware('permission:obat.edit');
            Route::get('/{id}/edit', function ($id) {
                $bhp = \App\Models\PemakaianBhp::findOrFail($id);
                return view('inventory.bhp.edit', compact('bhp'));
            })->name('edit');
        });

        // Stok Opname
        Route::prefix('opname')->name('opname.')->group(function () {
            Route::get('/',       fn () => view('inventory.opname.index'))->name('index');
            Route::get('/create', fn () => view('inventory.opname.create'))
                 ->name('create')
                 ->middleware('permission:obat.edit');
            Route::get('/{id}',  function ($id) {
                $opname = \App\Models\StokOpname::with(['items.barang', 'pembuat', 'verifikator'])->findOrFail($id);
                return view('inventory.opname.show', compact('opname'));
            })->name('show');
        });
    });

    // ── Pengaturan ──────────────────────────────────────────
    Route::prefix('pengaturan')->name('pengaturan.')->group(function () {

        // Manajemen Pengguna — hanya super_admin via policy
        Route::get('/pengguna', fn () => view('pengaturan.user.index'))
             ->name('pengguna')
             ->middleware('can:viewAny,App\Models\User');

        // Master Data Klinis
        Route::get('/masterdata', fn () => view('pengaturan.masterdata.index'))
             ->name('masterdata')
             ->middleware('permission:masterdata.view');

        // Data ICD-10
        Route::middleware('permission:masterdata.view')->prefix('icd10')->name('icd.')->group(function () {
            Route::get('/', fn () => view('pengaturan.masterdata.icd'))->name('index');
            Route::get('/template', function () {
                $csv  = "kode,nama,kategori\r\n";
                $csv .= "A00,Kolera,BAB I - Penyakit Infeksi dan Parasit Tertentu\r\n";
                $csv .= "A00.0,Kolera akibat Vibrio cholerae 01 biotipe cholerae,BAB I - Penyakit Infeksi dan Parasit Tertentu\r\n";
                $csv .= "A00.1,Kolera akibat Vibrio cholerae 01 biotipe eltor,BAB I - Penyakit Infeksi dan Parasit Tertentu\r\n";
                $csv .= "A09,Gastroenteritis dan kolitis infeksi yang tidak ditentukan,BAB I - Penyakit Infeksi dan Parasit Tertentu\r\n";
                $csv .= "Z99.9,Ketergantungan pada peralatan atau perangkat penunjang tidak ditentukan,BAB XXI - Faktor yang Mempengaruhi Status Kesehatan\r\n";
                return response($csv, 200, [
                    'Content-Type'        => 'text/csv; charset=UTF-8',
                    'Content-Disposition' => 'attachment; filename="template_icd10.csv"',
                ]);
            })->name('template');
        });

        // Data Dokter
        Route::middleware('permission:masterdata.view')->group(function () {
            Route::get('/dokter', fn () => view('pengaturan.dokter.index'))
                 ->name('dokter');
            Route::get('/dokter/{dokter}', function ($dokter) {
                $d = \App\Models\Dokter::with(['user', 'poli', 'sharingFee', 'dokterPoli.poli'])
                    ->findOrFail($dokter);
                return view('pengaturan.dokter.show', ['dokter' => $d]);
            })->name('dokter.show');
        });

        // Master Sumber Informasi
        Route::get('/sumber-informasi', fn () => view('pengaturan.sumber-informasi.index'))
             ->name('sumber-informasi')
             ->middleware('permission:masterdata.create');

        // Asuransi & Penjamin
        Route::prefix('asuransi')->name('asuransi.')->group(function () {
            Route::get('/bpjs', fn () => view('pengaturan.asuransi.bpjs'))
                 ->name('bpjs')
                 ->middleware('permission:asuransi.config_bpjs');
            Route::get('/', fn () => view('pengaturan.asuransi.index'))
                 ->name('index')
                 ->middleware('permission:asuransi.master.view');
            Route::get('/create', fn () => view('pengaturan.asuransi.create'))
                 ->name('create')
                 ->middleware('permission:asuransi.master.manage');
            Route::get('/{id}/edit', function ($id) {
                $asuransi = \App\Models\Asuransi::findOrFail($id);
                return view('pengaturan.asuransi.edit', compact('asuransi'));
            })->name('edit')
              ->middleware('permission:asuransi.master.manage');
        });

        // Profil Klinik
        Route::get('/klinik', fn () => view('pengaturan.klinik'))
             ->name('klinik')
             ->middleware('permission:pengaturan.view');
    });

    // ── Keuangan ────────────────────────────────────────────
    Route::prefix('keuangan')->name('keuangan.')->middleware('permission:piutang.view')->group(function () {
        Route::get('/piutang', fn () => view('keuangan.piutang.index'))->name('piutang.index');

        Route::get('/penagihan', fn () => view('keuangan.penagihan.index'))->name('penagihan.index');
        Route::get('/penagihan/create', fn () => view('keuangan.penagihan.create'))
             ->name('penagihan.create')
             ->middleware('permission:piutang.tagih');
        Route::get('/penagihan/{id}', function ($id) {
            $penagihan = \App\Models\PenagihanAsuransi::findOrFail($id);
            return view('keuangan.penagihan.show', compact('penagihan'));
        })->name('penagihan.show');
    });

    // ── Akuntansi ───────────────────────────────────────────
    Route::prefix('akuntansi')->name('akuntansi.')->middleware('permission:akuntansi.jurnal.view')->group(function () {
        Route::get('/coa', fn () => view('akuntansi.coa'))
             ->name('coa')->middleware('permission:akuntansi.coa.manage');
        Route::get('/jurnal-pending', fn () => view('akuntansi.jurnal-pending'))->name('jurnal-pending');
        Route::get('/jurnal-umum', fn () => view('akuntansi.jurnal-umum'))->name('jurnal-umum');

        Route::middleware('permission:akuntansi.laporan.view')->group(function () {
            Route::get('/buku-besar', fn () => view('akuntansi.buku-besar'))->name('buku-besar');
            Route::get('/neraca-saldo', fn () => view('akuntansi.neraca-saldo'))->name('neraca-saldo');
            Route::get('/laba-rugi', fn () => view('akuntansi.laba-rugi'))->name('laba-rugi');
        });
    });

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

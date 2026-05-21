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

    // Kunjungan (placeholder)
    Route::prefix('kunjungan')->name('kunjungan.')->group(function () {
        Route::get('/', fn () => abort(404))->name('index');
        Route::get('/pendaftaran', fn () => abort(404))->name('pendaftaran');
    });

    // Pemeriksaan (placeholder)
    Route::get('/pemeriksaan', fn () => abort(404))->name('pemeriksaan.index');

    // Rawat Inap (placeholder)
    Route::get('/rawat-inap', fn () => abort(404))->name('rawat-inap.index');

    // Farmasi (placeholder)
    Route::prefix('farmasi')->name('farmasi.')->group(function () {
        Route::get('/resep', fn () => abort(404))->name('resep.index');
        Route::get('/stok-obat', fn () => abort(404))->name('stok.index');
    });

    // Billing (placeholder)
    Route::get('/billing', fn () => abort(404))->name('billing.index');

    // Laporan (placeholder)
    Route::get('/laporan', fn () => abort(404))->name('laporan.index');

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

        // Klinik (placeholder)
        Route::get('/klinik', fn () => abort(404))->name('klinik');
    });

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

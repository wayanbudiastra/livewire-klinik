<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));

Route::middleware(['auth', 'active'])->group(function () {

    // Dashboard
    Route::get('/dashboard', fn () => view('dashboard'))->name('dashboard');

    // Pasien (placeholder)
    Route::get('/pasien', fn () => abort(404))->name('pasien.index');
    Route::get('/pasien/create', fn () => abort(404))->name('pasien.create');
    Route::get('/pasien/{id}', fn () => abort(404))->name('pasien.show');

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

        // Klinik (placeholder)
        Route::get('/klinik', fn () => abort(404))->name('klinik');
    });

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

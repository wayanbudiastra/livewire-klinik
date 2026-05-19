<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::middleware('auth')->group(function () {

    // Dashboard
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    // Pasien
    Route::get('/pasien', fn() => abort(404))->name('pasien.index');
    Route::get('/pasien/create', fn() => abort(404))->name('pasien.create');
    Route::get('/pasien/{id}', fn() => abort(404))->name('pasien.show');

    // Kunjungan
    Route::prefix('kunjungan')->name('kunjungan.')->group(function () {
        Route::get('/', fn() => abort(404))->name('index');
        Route::get('/pendaftaran', fn() => abort(404))->name('pendaftaran');
    });

    // Pemeriksaan
    Route::get('/pemeriksaan', fn() => abort(404))->name('pemeriksaan.index');

    // Rawat Inap
    Route::get('/rawat-inap', fn() => abort(404))->name('rawat-inap.index');

    // Farmasi
    Route::prefix('farmasi')->name('farmasi.')->group(function () {
        Route::get('/resep', fn() => abort(404))->name('resep.index');
        Route::get('/stok-obat', fn() => abort(404))->name('stok.index');
    });

    // Billing
    Route::get('/billing', fn() => abort(404))->name('billing.index');

    // Laporan
    Route::get('/laporan', fn() => abort(404))->name('laporan.index');

    // Pengaturan
    Route::prefix('pengaturan')->name('pengaturan.')->group(function () {
        Route::get('/pengguna', fn() => abort(404))->name('pengguna');
        Route::get('/klinik', fn() => abort(404))->name('klinik');
    });

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

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

    // Pemeriksaan (dalam pengembangan)
    Route::get('/pemeriksaan', fn () => view('coming-soon', [
        'modul'      => 'Pemeriksaan',
        'deskripsi'  => 'Modul SOAP Note, asesmen perawat, diagnosa ICD-10, dan tindakan medis.',
        'progress'   => 10,
        'roadmap'    => ['Input tanda vital & asesmen perawat', 'SOAP Note dokter', 'Diagnosa ICD-10', 'Tindakan medis', 'Resep elektronik'],
    ]))->name('pemeriksaan.index');

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

        // Resep (dalam pengembangan)
        Route::get('/resep', fn () => view('coming-soon', [
            'modul'    => 'Farmasi — Resep',
            'deskripsi'=> 'Validasi & dispensing resep elektronik dari dokter.',
            'progress' => 10,
            'roadmap'  => ['Verifikasi resep dokter', 'Dispensing obat', 'Labeling obat', 'Retur resep'],
        ]))->name('resep.index');
    });

    // Billing (dalam pengembangan)
    Route::get('/billing', fn () => view('coming-soon', [
        'modul'      => 'Billing & Kasir',
        'deskripsi'  => 'Generate invoice, kalkulasi tarif tindakan + obat + kamar, dan proses pembayaran multi-metode.',
        'progress'   => 5,
        'roadmap'    => ['Generate invoice otomatis', 'Kalkulasi tarif + sharing fee dokter', 'Pembayaran (tunai, BPJS, transfer)', 'Cetak kwitansi & invoice PDF'],
    ]))->name('billing.index');

    // Laporan (dalam pengembangan)
    Route::get('/laporan', fn () => view('coming-soon', [
        'modul'      => 'Laporan',
        'deskripsi'  => 'Laporan kunjungan, 10 besar penyakit, pendapatan, farmasi, dan export PDF/Excel.',
        'progress'   => 0,
        'roadmap'    => ['Laporan kunjungan harian/bulanan', 'Laporan 10 besar penyakit ICD-10', 'Laporan pendapatan', 'Laporan farmasi', 'Export PDF & Excel'],
    ]))->name('laporan.index');

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

        // Konfigurasi Klinik (dalam pengembangan)
        Route::get('/klinik', fn () => view('coming-soon', [
            'modul'      => 'Konfigurasi Klinik',
            'deskripsi'  => 'Pengaturan profil fasilitas kesehatan, logo, kontak, dan konfigurasi sistem.',
            'progress'   => 0,
            'roadmap'    => ['Profil klinik & logo', 'Konfigurasi tarif layanan', 'Pengaturan BPJS & asuransi', 'Jam operasional'],
        ]))->name('klinik');
    });

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

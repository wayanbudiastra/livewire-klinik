# PRD: Segregation of Duties (SoD) & Perbaikan Otorisasi Role/Permission

## 1. Pendahuluan

### 1.1 Latar Belakang
Sistem EMR ini menggunakan `spatie/laravel-permission` dengan 11 role dan 60 permission (`database/seeders/RolePermissionSeeder.php`). Permission sudah didefinisikan cukup granular (mis. `harga.proposal` terpisah dari `harga.setujui`), tapi granularitas di level *data* tidak otomatis berarti granularitas di level *enforcement*. Dokumen ini mengaudit **kode yang benar-benar berjalan** — bukan cuma apa yang didefinisikan di seeder — untuk memastikan permission yang terlihat terpisah di database benar-benar dipaksakan terpisah saat runtime.

### 1.2 Metodologi
Semua temuan di dokumen ini diverifikasi langsung dari kode per 13 Agustus 2026:
- `database/seeders/RolePermissionSeeder.php` — definisi role & permission
- `routes/web.php` — middleware `permission:`/`role:` per route
- `app/Livewire/**/*.php` — pemanggilan `$this->authorize()` / `abort_unless()` di dalam method aksi (bukan cuma `mount()`)
- `app/Services/**/*.php` — pengecekan otorisasi di service layer
- `app/Policies/*.php` — policy classes
- `resources/views/**/*.blade.php` — directive `@can()`/`@canany()`

Setiap temuan mencantumkan path file agar bisa diverifikasi ulang kapan saja kode berubah.

### 1.3 Cakupan
Dokumen ini **hanya** membahas RBAC & SoD (siapa boleh melakukan apa, dan kombinasi kewenangan apa yang berisiko). Tidak membahas keamanan aplikasi secara umum (XSS, SQL injection, dll — di luar cakupan; lihat skill `security-review` untuk itu).

---

## 2. Inventarisasi Role & Permission Existing

### 2.1 Daftar Role (11 total)

| Role | Ada akun demo di `UserSeeder`? | Karakter akses |
|---|---|---|
| `super_admin` | Ya | **Bypass semua permission check** (`Gate::before` di `AppServiceProvider.php:30-34`) |
| `admin` | Ya | Akses administratif luas: pasien, kunjungan, user, laporan, pengaturan, masterdata, asuransi, piutang, **seluruh siklus harga & akuntansi** |
| `dokter` | Ya | Klinis: asesmen, SOAP, resep, tindakan, cetak surat |
| `perawat` | Ya | Klinis: pasien, kunjungan, asesmen, tindakan (tanpa SOAP/resep) |
| `apoteker` | Ya | Farmasi: resep, obat, laporan farmasi |
| `kasir` | Ya | Billing, pembayaran, **manajemen data asuransi pasien** |
| `keuangan` | **Tidak** | Piutang, **seluruh siklus review-setujui-terapkan harga**, jurnal (view+manual create) |
| `akuntan` | **Tidak** | CoA, posting jurnal, tutup periode, jurnal manual |
| `front_office` | **Tidak** | Subset pendaftaran pasien/kunjungan tanpa delete |
| `rekam_medis` | **Tidak** | Data pasien + rekam medis + laporan registrasi/pemeriksaan |
| `pasien` | **Tidak** | Role portal pasien (view kunjungan/rekam medis/billing sendiri) |

**Temuan 2.1-A:** 5 dari 11 role (`keuangan`, `akuntan`, `front_office`, `rekam_medis`, `pasien`) tidak punya akun demo di `UserSeeder.php`. Perlu diverifikasi ke tim: apakah role ini aktif dipakai di produksi (dibuat manual via Manajemen Pengguna), direncanakan untuk rilis mendatang, atau sisa dari iterasi desain awal yang sudah tidak relevan. Role yang tidak jelas statusnya adalah risiko tersendiri — permission menumpuk tanpa ada yang mengaudit siapa sebenarnya memegangnya.

### 2.2 Daftar Permission per Modul (60 total)

| Modul | Permission |
|---|---|
| Pasien | `pasien.view/create/edit/delete` |
| Kunjungan | `kunjungan.view/create/edit/delete` |
| Klinis | `asesmen.view/create/edit`, `soap.view/create/edit`, `resep.view/create/edit`, `tindakan.view/create`, `penunjang.view/create`, `peralatan.pakai` |
| Farmasi | `obat.view/create/edit/delete` |
| Billing/Kasir | `billing.view/create/edit`, `pembayaran.view/create` |
| Piutang | `piutang.view/tagih/lunas` |
| Laporan | `laporan.view/keuangan/farmasi/export`, `laporan.registrasi.view`, `laporan.pemeriksaan.view`, `laporan.kasir.view/view_all`, `laporan.pharmacy.view` |
| Rekam Medis | `rekammedis.view/create/edit` |
| Pengaturan | `pengaturan.view/edit/satusehat` |
| User | `user.view/create/edit/delete` |
| Master Data | `masterdata.view/create/edit/delete` |
| Asuransi | `asuransi.config_bpjs`, `asuransi.master.view/manage`, `asuransi.pasien.manage` |
| Akuntansi | `akuntansi.coa.manage`, `akuntansi.jurnal.posting/view`, `akuntansi.laporan.view`, `akuntansi.periode.tutup`, `akuntansi.jurnal_manual.create` |
| Update Harga | `harga.lihat/proposal/review/setujui/terapkan` |
| Surat | `surat.cetak` |

---

## 3. Peta Enforcement: Declared vs Actual

Idealnya setiap aksi sensitif dijaga di **3 lapis**: (1) route middleware — gerbang kasar per halaman, (2) `$this->authorize()`/`abort_unless()` di backend method yang benar-benar mengubah data — gerbang sesungguhnya, (3) `@can()` di Blade — cuma UI hint (sembunyikan tombol), **bukan pengaman**. Siapa pun yang tahu nama method Livewire tetap bisa memanggilnya langsung dari browser meski tombolnya disembunyikan, kalau lapis 2 tidak ada.

Audit ini menemukan **3 modul finansial** di mana permission granular yang didefinisikan di seeder **hanya dicek di lapis 3 (Blade), tidak pernah di lapis 2 (backend)** — meski route-nya sendiri (lapis 1) hanya mensyaratkan permission "lihat" yang jauh lebih longgar dari permission spesifik aksinya:

| Modul | Aksi | Permission yang *seharusnya* dicek | Lapis 1 (route) | Lapis 2 (backend method) | Lapis 3 (Blade) |
|---|---|---|---|---|---|
| Update Harga | Setujui/Tolak/Terapkan proposal harga | `harga.setujui`, `harga.terapkan` | Cuma `harga.lihat` (`routes/web.php:377`) | **Tidak ada** — `ProposalHargaDetail::setujui()/tolak()/terapkan()` (`app/Livewire/Harga/ProposalHargaDetail.php:101,118,147`) dan `ProposalHargaService` (`app/Services/Harga/ProposalHargaService.php`) tidak memanggil `authorize()`/`can()` sama sekali | Ya, `@can('harga.setujui')` dll (`resources/views/livewire/harga/proposal-harga-detail.blade.php:53,71,89`) |
| Akuntansi | Posting jurnal pending → buku besar | `akuntansi.jurnal.posting` | Cuma `akuntansi.jurnal.view` (`routes/web.php:352,355`) | **Tidak ada** — `JurnalPendingTable::postingTerpilih()` (`app/Livewire/Akuntansi/JurnalPendingTable.php:64`) tidak memanggil `authorize()` | Ya, `@can('akuntansi.jurnal.posting')` (`resources/views/livewire/akuntansi/jurnal-pending-table.blade.php:34,58,73`) |
| Piutang | Catat pelunasan piutang | `piutang.lunas` | Cuma `piutang.tagih` di route penagihan (`routes/web.php:344`); halaman detail sendiri tidak ber-middleware permission spesifik | **Tidak ada** — `PenagihanDetail::catatBayar()` (`app/Livewire/Keuangan/Penagihan/PenagihanDetail.php:46`) tidak memanggil `authorize()` | Ya, `@can('piutang.lunas')` (`resources/views/livewire/keuangan/penagihan/penagihan-detail.blade.php:112`) |

**Dampak konkret:** siapa pun yang login dengan permission "lihat" saja pada 3 modul ini (mis. role `keuangan` untuk piutang — walau `keuangan` kebetulan sudah dapat `piutang.lunas` juga di seeder saat ini, jadi *saat ini* tidak langsung tereksploitasi kecuali kombinasi role berubah) secara teknis bisa memicu aksi "setujui harga", "posting jurnal", atau "catat lunas" lewat pemanggilan Livewire action langsung (mis. via devtools/network tab), tanpa perlu tombolnya terlihat. Ini bukan celah teoretis — ini gap nyata antara apa yang didefinisikan di `RolePermissionSeeder.php` dan apa yang benar-benar dijalankan mesinnya.

**Sebagai pembanding, praktik yang SUDAH benar di codebase ini** (untuk konteks — supaya rekomendasi di §6 konsisten dengan pola yang sudah terbukti jalan):
- `BatalkanBillingModal::batalkan()` (`app/Livewire/Kasir/Billing/BatalkanBillingModal.php:31`) mensyaratkan **password super_admin** untuk membatalkan invoice — kontrol kompensasi yang kuat, bukan cuma RBAC.
- `UserPolicy::delete()`/`resetPassword()` (`app/Policies/UserPolicy.php`) mencegah non-super_admin menghapus/reset password akun super_admin, dan reset password dibatasi hanya untuk super_admin apa pun role targetnya.
- `HargaWna::simpanMarkup()/terapkanKeSemua()` (`app/Livewire/Pengaturan/HargaWna.php`) dan `LoginLog::mount()` (`app/Livewire/Pengaturan/LoginLog.php`) memanggil `abort_unless(...->hasRole('super_admin'))` di dalam method aksi itu sendiri — pola yang benar.

---

## 4. SoD Matrix

### 4.1 Definisi Conflict Pair
Pasangan kewenangan berikut dianggap konflik SoD kalau dipegang **role yang sama**, karena menghilangkan prinsip *maker-checker* (pembuat ≠ penyetuju) atau memisahkan *custody* (pegang uang/aset) dari *recording* (mencatat transaksi):

| # | Conflict Pair | Kenapa berisiko |
|---|---|---|
| C1 | Usulkan harga (`harga.proposal`) **+** Setujui harga (`harga.setujui`) | Satu orang bisa mengajukan sekaligus menyetujui perubahan tarif sendiri |
| C2 | Setujui harga (`harga.setujui`) **+** Terapkan harga (`harga.terapkan`) | Tidak ada jeda verifikasi independen antara "disetujui" dan "diberlakukan ke seluruh master data" |
| C3 | Input jurnal manual (`akuntansi.jurnal_manual.create`) **+** Posting jurnal (`akuntansi.jurnal.posting`) | Satu orang bisa membuat entri jurnal manual sekaligus mempostingnya sendiri ke buku besar |
| C4 | Kelola CoA (`akuntansi.coa.manage`) **+** Posting jurnal (`akuntansi.jurnal.posting`) | Bisa membuat akun baru sekaligus memposting transaksi ke akun itu tanpa kontrol independen |
| C5 | Tagih piutang (`piutang.tagih`) **+** Catat lunas (`piutang.lunas`) | Satu orang menagih sekaligus mencatat pelunasan — rawan manipulasi (mis. tandai lunas tanpa uang benar-benar diterima) |
| C6 | Input billing/pembayaran (`billing.create`/`pembayaran.create`) **+** Kelola data penjamin pasien (`asuransi.pasien.manage`) | Kasir bisa mengubah skema penjamin (tunai↔asuransi↔BPJS) sekaligus mencatat pembayarannya — celah manipulasi nominal tagihan |
| C7 | Kelola user (`user.create`/`user.edit`) **+** Kelola permission/role | Tidak relevan di sistem ini — pemberian role dilakukan lewat seeder/kode, bukan UI, jadi C7 secara struktural sudah aman (lihat §5, temuan positif) |
| C8 | Tutup periode akuntansi (`akuntansi.periode.tutup`) **+** Posting jurnal (`akuntansi.jurnal.posting`) | Bisa memposting transaksi lalu menutup periode sendiri tanpa rekonsiliasi independen |

### 4.2 Matrix Role × Conflict Pair

🔴 = role memegang **kedua sisi** conflict pair (pelanggaran SoD by design) · 🟡 = memegang salah satu sisi saja (aman secara desain, tapi cek §3 untuk gap enforcement) · ⚪ = tidak memegang keduanya

| Role | C1 | C2 | C3 | C4 | C5 | C6 | C8 |
|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| `super_admin` | 🔴* | 🔴* | 🔴* | 🔴* | 🔴* | 🔴* | 🔴* |
| `admin` | 🔴 | 🔴 | 🔴 | 🔴 | 🔴 | ⚪ | 🔴 |
| `keuangan` | ⚪ (tak punya `harga.proposal`) | 🔴 | 🟡 (`jurnal_manual.create` saja) | ⚪ | 🔴 | ⚪ | ⚪ |
| `akuntan` | ⚪ | ⚪ | 🔴 | 🔴 | ⚪ | ⚪ | 🔴 |
| `kasir` | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | 🔴 | ⚪ |
| `dokter`/`perawat`/`apoteker`/`front_office`/`rekam_medis`/`pasien` | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ |

\* `super_admin` selalu 🔴 di semua kolom karena bypass total — didaftarkan sebagai baseline referensi, bukan temuan baru (lihat §5 untuk rekomendasi kompensasi, bukan penghapusan bypass).

### 4.3 Ringkasan Pelanggaran per Role
- **`admin`** — memegang **6 dari 7** conflict pair yang relevan (C1,C2,C3,C4,C5,C8). Ini role administratif "all-in-one" yang jauh melebihi kebutuhan operasional harian — cocok untuk klinik kecil tahap awal, tapi jadi *single point of failure* untuk kontrol keuangan begitu skala klinik bertambah.
- **`keuangan`** — memegang C2 (review+setujui+terapkan harga jadi satu tangan) dan C5 (tagih+lunas piutang jadi satu tangan). Role ini secara nama seharusnya jadi "penyeimbang" `admin`, tapi kombinasi izinnya sendiri masih menyalahi SoD secara internal.
- **`akuntan`** — memegang C3 dan C8. Wajar untuk akuntan tunggal di klinik kecil, tapi kalau tim bertambah, sebaiknya dipecah.
- **`kasir`** — memegang C6, unik karena ini **satu-satunya role klinis/operasional harian** (bukan admin/keuangan) yang masuk conflict pair — layak diprioritaskan karena kasir berinteraksi dengan uang tunai setiap hari.

---

## 5. Temuan & Prioritas

| # | Severity | Temuan | Lokasi |
|---|---|---|---|
| F1 | **Critical** | Approve/tolak/terapkan proposal harga tidak diverifikasi di backend, hanya di Blade | §3, baris 1 |
| F2 | **Critical** | Posting jurnal ke buku besar tidak diverifikasi di backend, hanya di Blade | §3, baris 2 |
| F3 | **High** | Catat pelunasan piutang tidak diverifikasi di backend, hanya di Blade | §3, baris 3 |
| F4 | **High** | Role `admin` memegang siklus penuh harga (usul→review→setuju→terap) dan akuntansi (input→posting→tutup periode) — tak ada maker-checker sama sekali kalau cuma 1 admin aktif | §4.3 |
| F5 | **Medium** | Role `kasir` bisa ubah data penjamin (asuransi/BPJS/umum) pasien sekaligus mencatat pembayarannya sendiri | §4.1 (C6) |
| F6 | **Medium** | 5 role (`keuangan`,`akuntan`,`front_office`,`rekam_medis`,`pasien`) tidak punya akun demo/dokumentasi — status pemakaian di produksi tidak terverifikasi | §2.1-A |
| F7 | **Low** | Role `keuangan` sendiri menyalahi SoD (review+setujui+terapkan harga dalam 1 role) meski terpisah dari `admin` | §4.3 |
| F8 (positif) | — | `BatalkanBillingModal` dan `UserPolicy` sudah menerapkan kontrol kompensasi yang baik (re-auth password, larangan self-service pada akun super_admin) | §3 |
| F9 (positif) | — | Fitur Log Login User (dibangun sebelumnya di sistem ini) sudah jadi kontrol kompensasi awal untuk memantau aktivitas `super_admin` | — |

---

## 6. Rencana Perbaikan (PRD)

### 6.1 Tujuan
1. Menutup gap otorisasi backend (F1–F3) sehingga permission granular yang sudah didefinisikan benar-benar ditegakkan, bukan cuma dekorasi UI.
2. Menyediakan opsi *role assignment* yang memungkinkan klinik dengan lebih dari 1 staf keuangan/admin menjalankan maker-checker sungguhan (F4, F5, F7) — tanpa memaksa perubahan kalau kliniknya memang masih 1 orang per fungsi.
3. Memberi visibilitas ke tim developer/pemilik sistem soal role yang belum jelas status pemakaiannya (F6).

### 6.2 Non-Tujuan (Out of Scope)
- Tidak membangun modul approval baru dari nol (workflow proposal harga & jurnal pending **sudah ada** — ini murni soal menegakkan permission yang sudah ada di titik yang tepat).
- Tidak mengubah struktur permission/role di database (nama-nama permission sudah cukup baik; masalahnya ada di *enforcement*, bukan *desain data*).
- Tidak menghapus bypass `super_admin` — itu pola standar dan disengaja; kompensasinya lewat audit log (lihat 6.5), bukan penghilangan bypass.

### 6.3 Functional Requirements

**FR-1 — Backend authorization untuk workflow harga**
- `ProposalHargaDetail::submitReview()` → tambah `$this->authorize('harga.proposal')` atau setara di awal method (bukan cuma di route).
- `ProposalHargaDetail::setujui()` dan `tolak()` → tambah pengecekan permission `harga.setujui`/`harga.review` sebelum memanggil service.
- `ProposalHargaDetail::terapkan()` → tambah pengecekan permission `harga.terapkan`.
- `ProposalHargaDetail::saveEdit()`/`koreksiItem()` dan `toggleSkip()` → tambah pengecekan `harga.review` (koreksi item = bagian dari proses review).
- Alternatif teknis: gunakan Laravel Policy (`ProposalHargaPolicy`) dengan method per state-transition (`submit`, `approve`, `reject`, `apply`) agar aturan terpusat & mudah dites — lebih disarankan dibanding menyebar `authorize()` string literal di banyak method.

**FR-2 — Backend authorization untuk posting jurnal**
- `JurnalPendingTable::postingTerpilih()` → tambah `$this->authorize('akuntansi.jurnal.posting')` sebelum eksekusi posting massal.
- `JurnalPendingTable::abaikan()` (kalau "abaikan" berarti membatalkan/skip jurnal pending) → tinjau apakah perlu permission terpisah atau cukup `akuntansi.jurnal.posting`.

**FR-3 — Backend authorization untuk pelunasan piutang**
- `PenagihanDetail::catatBayar()` → tambah `$this->authorize('piutang.lunas')` sebelum mencatat pembayaran.

**FR-4 — Pemisahan role harga (opsional per klinik)**
- Sediakan role baru `harga_reviewer` (permission: `harga.lihat`, `harga.review`) terpisah dari `harga_approver` (permission: `harga.lihat`, `harga.setujui`, `harga.terapkan`), sebagai **opsi** yang bisa diassign kalau klinik punya ≥2 staf finance/manajemen. Role `admin`/`keuangan` existing tidak perlu dihapus permission-nya — ini strictly opsi tambahan, bukan migrasi paksa (klinik kecil dengan 1 admin tetap bisa pakai role lama).

**FR-5 — Pemisahan tagih vs lunas piutang (opsional)**
- Sama seperti FR-4: sediakan opsi role `piutang_kolektor` (`piutang.tagih`) terpisah dari `piutang_verifikator` (`piutang.lunas`) untuk klinik yang butuh.

**FR-6 — Audit trail pada aksi sensitif**
- Pastikan setiap aksi F1–F3 (setujui/tolak/terapkan harga, posting jurnal, catat lunas piutang) tercatat di `activity_log` (paket `spatie/laravel-activitylog` sudah dipakai di sistem ini untuk audit masterdata & login — tinggal diperluas cakupannya) dengan `causedBy()` user yang benar-benar melakukan aksi. Ini melengkapi F6 kalau suatu saat perlu investigasi "siapa yang approve harga X tanggal Y".

**FR-7 — Verifikasi status role tanpa akun**
- Tim project mengonfirmasi status 5 role di F6: aktif dipakai (buat dokumentasi & akun demo seperti 6 role lain), direncanakan (biarkan, tandai "planned" di seeder komentar), atau tidak relevan lagi (hapus dari `RolePermissionSeeder.php` supaya tidak jadi permission menumpuk tanpa pemilik jelas).

### 6.4 Non-Functional Requirements
- Perubahan FR-1–3 tidak boleh mengubah *default* behavior untuk role yang sudah correctly-permissioned saat ini (mis. `keuangan` yang sudah punya `harga.setujui` tetap bisa approve seperti biasa — cuma sekarang dicek beneran, bukan cuma sembunyi tombol).
- Setiap `authorize()` baru harus disertai pesan error yang jelas (403 dengan pesan, bukan generic Laravel exception page) — konsisten dengan pola existing (mis. `SuratKeteranganService::assertSoapFinal()` melempar `\RuntimeException` dengan pesan Indonesia yang jelas).
- Tidak menambah dependency baru — semua FR di atas bisa diimplementasi dengan `spatie/laravel-permission` dan `spatie/laravel-activitylog` yang sudah terpasang.

### 6.5 Fase Implementasi

| Fase | Isi | Alasan urutan |
|---|---|---|
| **Fase 1 (Quick win, low-risk)** | FR-1, FR-2, FR-3 — tambah `authorize()` di backend untuk 3 gap Critical/High. Tidak ada perubahan skema data, tidak ada perubahan role assignment existing. | Menutup celah keamanan nyata secepat mungkin dengan risiko regresi minimal — hanya menambah pengecekan, tidak mengubah logika bisnis |
| **Fase 2** | FR-6 — perluas audit log ke aksi finansial sensitif | Bergantung Fase 1 selesai supaya titik pencatatan log ditaruh di tempat yang sudah benar aman |
| **Fase 3 (opsional, atas keputusan klinik)** | FR-4, FR-5 — role baru untuk pemisahan tugas | Butuh keputusan bisnis (apakah klinik punya cukup staf untuk pisah tugas) — tidak dipaksakan |
| **Fase 4** | FR-7 — bersih-bersih role tanpa pemilik jelas | Butuh konfirmasi manual dari pemilik sistem, bukan keputusan teknis semata |

### 6.6 Acceptance Criteria
- [ ] User dengan permission `harga.lihat` saja (tanpa `harga.setujui`) **tidak bisa** memicu `ProposalHargaDetail::setujui()` meski memanggilnya langsung lewat Livewire action (diverifikasi lewat test, bukan cuma manual click UI).
- [ ] User dengan permission `akuntansi.jurnal.view` saja **tidak bisa** memicu `postingTerpilih()`.
- [ ] User tanpa `piutang.lunas` **tidak bisa** memicu `catatBayar()`.
- [ ] Role existing (`admin`, `keuangan`, `akuntan`) yang memang sudah py permission terkait tetap bisa menjalankan semua aksi seperti sebelumnya — tidak ada regresi fungsional.
- [ ] Aksi approve/reject/terapkan harga & posting jurnal & catat lunas piutang tercatat di `activity_log` dengan causer yang benar.
- [ ] Status 5 role tanpa akun (F6) terdokumentasi keputusannya (dipakai / direncanakan / dihapus).

### 6.7 Risiko & Mitigasi
| Risiko | Mitigasi |
|---|---|
| Menambah `authorize()` di backend mematahkan alur kerja user yang selama ini "kebetulan" bisa akses karena gap ini (mis. staf yang terbiasa approve harga padahal harusnya tidak berwenang) | Sebelum deploy Fase 1, audit siapa saja yang selama ini aktif approve/posting/catat-lunas di data produksi (via `activity_log` atau riwayat proposal/jurnal), pastikan role mereka sudah benar sebelum enforcement dinyalakan |
| Klinik kecil dengan 1 staf keuangan jadi tidak bisa kerja kalau role dipaksa dipisah | FR-4/FR-5 dibuat opsional (Fase 3), bukan migrasi wajib — role lama tetap berfungsi |
| Salah taruh `authorize()` string (typo nama permission) menyebabkan semua orang ke-block termasuk yang berwenang | Tulis test otomatis untuk tiap acceptance criteria di §6.6, jangan andalkan uji manual saja |

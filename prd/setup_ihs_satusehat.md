# PRD — Setup IHS SatuSehat (Persiapan Integrasi Data)

**Tanggal**: 2026-07-28
**Revisi**: v3 — 2026-07-28 (IHS disisipkan ke modul master data yang sudah ada)
**Status**: Draft
**Modul**: Pasien, Dokter, Nakkes (Perawat) — masing-masing di halaman yang sudah ada

---

## 1. Latar Belakang

Sebelum klinik dapat mengirimkan data klinis ke platform SatuSehat Kemkes dalam format FHIR R4, setiap subjek data — **Pasien**, **Dokter**, dan **Nakkes (Tenaga Kesehatan)** — harus memiliki **IHS ID** (Indonesia Health Services ID).

IHS ID diperoleh dengan meng-query API SatuSehat menggunakan NIK, dan wajib disimpan secara lokal agar bisa dicantumkan sebagai referensi FHIR (Patient, Practitioner resource).

Fitur Setup IHS **tidak dibuat sebagai halaman tersendiri**. Sebaliknya, fitur ini disisipkan langsung ke dalam modul master data yang sudah ada — halaman Pasien, Dokter, dan Nakkes — sehingga admin bisa mengelola IHS ID bersamaan dengan data utama entitas tersebut.

**Fitur hanya aktif ketika `ConfigSatuSehat::aktif() === true`.** Selama SatuSehat belum diaktifkan di Pengaturan, semua elemen UI terkait IHS disembunyikan.

---

## 2. Tujuan

1. Menyisipkan **status IHS** dan **tombol fetch IHS** pada halaman master data yang sudah ada (Pasien, Dokter, Nakkes), bukan halaman terpisah.
2. Memungkinkan admin mengambil IHS ID secara **per individu** (dari halaman detail) maupun **massal** (dari halaman daftar).
3. Menyimpan IHS ID ke database lokal agar modul pengiriman FHIR bisa memakainya.
4. Fitur tersembunyi otomatis jika SatuSehat belum diaktifkan.

---

## 3. Scope

### In-Scope
- **Pasien** → tambah kolom IHS Status di `pasien.index` + kartu IHS di `pasien.show`
- **Dokter** → tambah kolom IHS Status di `pengaturan.dokter` (index) + tab "IHS SatuSehat" di `pengaturan.dokter.show`
- **Nakkes** → tambah kolom `nik` dan IHS di halaman **Manajemen Pengguna** (`pengaturan.pengguna`) yang sudah ada, dengan filter role `perawat`
- Kolom database baru: `ihs_id`, `ihs_status`, `ihs_synced_at`, `ihs_error_msg` pada tabel `pasien`, `dokter`, `perawat`
- Kolom `nik` baru pada tabel `perawat`
- Service `SatuSehatIhsService` untuk query API dan parsing response FHIR
- Progress modal / progress bar saat bulk fetch
- Guard: semua elemen IHS disembunyikan jika `ConfigSatuSehat::aktif() === false`

### Out-of-Scope
- Halaman standalone "Setup IHS" — tidak dibuat
- Pengiriman data FHIR — modul terpisah
- Sinkronisasi otomatis/cron job

---

## 4. User Stories

| # | Sebagai | Saya ingin | Agar |
|---|---------|-----------|------|
| U1 | Admin | Melihat status IHS di kolom tabel pasien | Langsung tahu mana yang sudah/belum punya IHS ID |
| U2 | Admin | Klik "Ambil Semua IHS" dari halaman daftar pasien | Fetch massal tanpa buka satu per satu |
| U3 | Admin | Melihat kartu IHS di detail pasien, klik "Refresh IHS" | Update IHS untuk satu pasien spesifik |
| U4 | Admin | Melihat dan melakukan hal sama untuk Dokter | Fetch IHS dari halaman daftar dan detail dokter |
| U5 | Admin | Mengisi NIK perawat dan fetch IHS-nya dari User Management | Karena perawat dikelola lewat halaman manajemen pengguna |
| U6 | Admin | Tidak melihat elemen IHS sama sekali saat SatuSehat belum aktif | UI tetap bersih dan tidak membingungkan |
| U7 | Admin | Melihat progress saat bulk fetch berjalan | Tahu proses sudah sampai mana |

---

## 5. Perubahan Database

### 5.1 Tabel `pasien`
```sql
ALTER TABLE pasien
  ADD COLUMN ihs_id          VARCHAR(50)  NULL AFTER nik,
  ADD COLUMN ihs_status      ENUM('ditemukan','tidak_ditemukan','error') NULL AFTER ihs_id,
  ADD COLUMN ihs_synced_at   TIMESTAMP    NULL AFTER ihs_status,
  ADD COLUMN ihs_error_msg   TEXT         NULL AFTER ihs_synced_at;
```
> NIK sudah ada di tabel `pasien`.

### 5.2 Tabel `dokter`
```sql
ALTER TABLE dokter
  ADD COLUMN ihs_id          VARCHAR(50)  NULL AFTER no_sip,
  ADD COLUMN ihs_status      ENUM('ditemukan','tidak_ditemukan','error') NULL AFTER ihs_id,
  ADD COLUMN ihs_synced_at   TIMESTAMP    NULL AFTER ihs_status,
  ADD COLUMN ihs_error_msg   TEXT         NULL AFTER ihs_synced_at;
```
> NIK sudah ada di tabel `dokter`.

### 5.3 Tabel `perawat`
```sql
ALTER TABLE perawat
  ADD COLUMN nik             CHAR(16)     NULL UNIQUE AFTER nip,
  ADD COLUMN ihs_id          VARCHAR(50)  NULL AFTER nik,
  ADD COLUMN ihs_status      ENUM('ditemukan','tidak_ditemukan','error') NULL AFTER ihs_id,
  ADD COLUMN ihs_synced_at   TIMESTAMP    NULL AFTER ihs_status,
  ADD COLUMN ihs_error_msg   TEXT         NULL AFTER ihs_synced_at;
```
> Tabel `perawat` belum punya NIK — ditambahkan di sini.

---

## 6. Integrasi API SatuSehat

### 6.1 Autentikasi
```
POST {auth_url}?grant_type=client_credentials
Authorization: Basic base64(client_id:client_secret)
```
- Environment aktif dari `ConfigSatuSehat::config()->environment`
- Token di-cache di memory dalam satu sesi bulk fetch (tidak perlu DB cache)

### 6.2 Endpoint IHS Patient (Pasien)
```
GET {base_url}/fhir-r4/v1/Patient?identifier=https://fhir.kemkes.go.id/id/nik|{NIK}
Authorization: Bearer {access_token}
```
→ Response sukses: `entry[0].resource.id` → simpan ke `ihs_id`

### 6.3 Endpoint IHS Practitioner (Dokter & Nakkes)
```
GET {base_url}/fhir-r4/v1/Practitioner?identifier=https://fhir.kemkes.go.id/id/nik|{NIK}
Authorization: Bearer {access_token}
```
→ Response sukses: `entry[0].resource.id` → simpan ke `ihs_id`

### 6.4 Penanganan Error

| Kondisi | `ihs_status` | Pesan |
|---------|-------------|-------|
| `total >= 1` | `ditemukan` | IHS ID: `{id}` |
| `total == 0` | `tidak_ditemukan` | NIK tidak terdaftar di SatuSehat |
| NIK kosong/null | `error` | NIK belum diisi |
| HTTP 401 | `error` | Token tidak valid, cek konfigurasi SatuSehat |
| HTTP 429 | `error` | Rate limit, coba lagi nanti |
| HTTP 5xx / timeout | `error` | Server SatuSehat tidak merespons |
| Exception koneksi | `error` | Tidak bisa terhubung ke server SatuSehat |

### 6.5 Rate Limiting
Bulk fetch: jeda **200ms** antar request.

---

## 7. Detail Perubahan Per Modul

---

### 7.1 Modul Pasien

#### Halaman Daftar — `pasien.index` (`PasienTable.php`)

**Penambahan (hanya saat `ConfigSatuSehat::aktif()`):**

```
┌─────────────────────────────────────────────────────────────────────┐
│  Daftar Pasien                                          [+ Tambah]  │
│                                                                      │
│  [🔍 Cari...]  [Filter...]   [⬇ Ambil Semua IHS]  ← baru           │
│                                                                      │
│  ┌──────┬─────────────────┬──────────────────┬────────────┬───────┐ │
│  │ No.  │ Nama            │ NIK              │ IHS Status │ Aksi  │ │
│  ├──────┼─────────────────┼──────────────────┼────────────┼───────┤ │
│  │ 001  │ Budi Santoso    │ 5101...0001      │ ✅ IHS     │ ...   │ │
│  │ 002  │ Siti Rahayu     │ 5101...0002      │ ⏳ Belum   │ ...   │ │
│  │ 003  │ Ahmad Fauzi     │ (kosong)         │ ⚠️ NIK     │ ...   │ │
│  └──────┴─────────────────┴──────────────────┴────────────┴───────┘ │
└─────────────────────────────────────────────────────────────────────┘
```

- Kolom **IHS Status** muncul kondisional (`@if ConfigSatuSehat::aktif()`)
- Tombol **"Ambil Semua IHS"** memicu modal progress — fetch seluruh pasien dengan `ihs_status IS NULL OR ihs_status = 'error'`
- Kolom Aksi: tombol `🔄` per baris untuk fetch IHS individu

#### Halaman Detail — `pasien.show`

**Penambahan: kartu "IHS SatuSehat"** di kolom kiri (bawah kartu identitas), kondisional:

```
┌─────────────────────────────┐
│  🏥 IHS SatuSehat           │
├─────────────────────────────┤
│  Status   : ✅ Ditemukan    │
│  IHS ID   : P02029901234   │
│  Sync     : 28 Jul 2026    │
│             14:35:22       │
│                             │
│  [ 🔄 Refresh IHS ]        │
└─────────────────────────────┘
```

State lain:
- **Belum**: `Status: Belum diambil` + `[ Ambil IHS ]`
- **Tidak ditemukan**: `Status: Tidak ditemukan` (amber) + `[ Coba Lagi ]`
- **Error**: `Status: Error` + tooltip pesan + `[ Coba Lagi ]`
- **NIK kosong**: `Status: NIK belum diisi` (abu) + link ke halaman edit pasien

---

### 7.2 Modul Dokter

#### Halaman Daftar — `pengaturan.dokter` (`DokterTable.php`)

**Penambahan (hanya saat `ConfigSatuSehat::aktif()`):**

- Kolom **IHS Status** pada tabel
- Tombol **"Ambil Semua IHS"** di area header tabel, dengan modal progress

#### Halaman Detail — `pengaturan.dokter.show`

Halaman ini sudah memiliki tab-based navigation (`profil`, `poli`, `fee`, `jadwal`).

**Penambahan: tab baru `ihs`** → "IHS SatuSehat" (muncul kondisional setelah tab `jadwal`):

```
[ Profil Klinis ]  [ Mapping Poli ]  [ Sharing Fee ]  [ Jadwal ]  [ IHS SatuSehat ]
```

**Konten tab IHS:**

```
┌──────────────────────────────────────────────────────┐
│  IHS SatuSehat — Konfigurasi Identitas Dokter        │
├──────────────────────────────────────────────────────┤
│                                                      │
│  NIK Dokter   : 5101010101010001                     │
│  IHS ID       : N10000001                            │
│  Status       : ✅ Ditemukan                         │
│  Terakhir sync: 28 Jul 2026, 14:35                   │
│                                                      │
│  Lingkungan   : SANDBOX                              │
│                                                      │
│  [ 🔄 Refresh IHS ]                                  │
│                                                      │
│  ────────────────────────────────────────────────    │
│  ℹ️  IHS Practitioner ID ini digunakan sebagai       │
│  referensi Practitioner pada resource FHIR saat      │
│  pengiriman data kunjungan ke SatuSehat.             │
└──────────────────────────────────────────────────────┘
```

State jika NIK kosong → tampil form input NIK inline karena NIK dokter bisa saja belum terisi.

---

### 7.3 Modul Nakkes (Perawat)

Tabel `perawat` dikelola melalui halaman **Manajemen Pengguna** (`pengaturan.pengguna`). Ketika user dibuat/diedit dengan role `perawat`, record di tabel `perawat` otomatis dibuat/diupdate.

**Penambahan di `UserForm.php`**: Ketika role yang dipilih adalah `perawat`, tampilkan field **NIK** yang tersimpan ke `perawat.nik`.

**Penambahan di `UserTable.php`** (hanya saat `ConfigSatuSehat::aktif()` dan filter role = `perawat`):

- Kolom **IHS Status** pada tabel
- Tombol **"Ambil Semua IHS Perawat"** — hanya memproses user dengan role `perawat`

```
┌─────────────────────────────────────────────────────────────────────┐
│  Manajemen Pengguna                                                  │
│                                                                      │
│  [🔍 Cari...]  [Filter Role: Perawat ▼]   [⬇ Ambil Semua IHS]      │
│                ↑ filter ini memunculkan tombol IHS                   │
│  ┌──────┬───────────────┬──────────────────┬────────────┬─────────┐ │
│  │ No.  │ Nama          │ NIK              │ IHS Status │ Aksi    │ │
│  ├──────┼───────────────┼──────────────────┼────────────┼─────────┤ │
│  │  1   │ Ni Wayan Dewi │ 5101...0010      │ ✅ IHS     │ Edit 🔄 │ │
│  │  2   │ I Kadek Artha │ (kosong)         │ ⚠️ NIK     │ Edit —  │ │
│  └──────┴───────────────┴──────────────────┴────────────┴─────────┘ │
└─────────────────────────────────────────────────────────────────────┘
```

> **Kenapa di User Management?** Karena perawat tidak memiliki halaman master data tersendiri — tabel `perawat` dibuat otomatis saat user dengan role `perawat` dibuat. Pengelolaan NIK perawat paling logis dilakukan di halaman yang sama tempat data user dikelola.

---

## 8. Guard — SatuSehat Tidak Aktif

Semua elemen UI terkait IHS (kolom status, tombol fetch, kartu IHS, tab IHS) dibungkus dengan kondisi:

```blade
@if(\App\Models\ConfigSatuSehat::aktif())
    {{-- elemen IHS --}}
@endif
```

Tidak ada halaman error atau guard screen tersendiri. Elemen cukup **tidak muncul** ketika SatuSehat tidak aktif.

**Catatan performa**: `ConfigSatuSehat::aktif()` memanggil DB query. Untuk mencegah query berulang di setiap request, gunakan cache 60 detik:

```php
// Di ConfigSatuSehat model
public static function aktif(): bool
{
    return Cache::remember('satusehat.aktif', 60, function () {
        return (bool) static::value('is_active');
    });
}
```

Cache di-invalidate setiap kali `ConfigSatuSehat` disimpan (di `simpan()` Livewire):
```php
Cache::forget('satusehat.aktif');
```

---

## 9. Arsitektur Implementasi

### 9.1 Service Layer

**`app/Services/SatuSehat/SatuSehatIhsService.php`**

```php
class SatuSehatIhsService
{
    private ?string $accessToken = null;

    /** Fetch IHS ID untuk satu pasien. Return array hasil. */
    public function fetchPatientIhs(Pasien $pasien): array

    /** Fetch IHS ID untuk satu dokter atau perawat. */
    public function fetchPractitionerIhs(object $entity): array

    /** Ambil token OAuth2. Di-cache di property selama satu sesi. */
    private function getAccessToken(): string

    /** Parse FHIR Bundle response → ambil entry[0].resource.id. */
    private function parseFhirBundle(array $response, string $resourceType): array
}
```

### 9.2 Perubahan Livewire Components

| File | Perubahan |
|------|-----------|
| `app/Livewire/Pasien/PasienTable.php` | + `fetchIhsSatu(int $id)`, `fetchIhsSemua()` |
| `app/Livewire/Pasien/PasienForm.php` | Tidak berubah (NIK sudah dikelola di sini) |
| `app/Livewire/Pengaturan/Dokter/DokterTable.php` | + `fetchIhsSatu(int $id)`, `fetchIhsSemua()` |
| `app/Livewire/Pengaturan/Dokter/DokterIhsForm.php` | **Baru** — Livewire component untuk tab IHS dokter |
| `app/Livewire/Pengaturan/User/UserTable.php` | + `fetchIhsSatu(int $userId)`, `fetchIhsSemua()` (hanya untuk role perawat) |
| `app/Livewire/Pengaturan/User/UserForm.php` | + field `nik` yang tampil ketika role = `perawat` |
| `app/Models/ConfigSatuSehat.php` | Update `aktif()` dengan cache + `clearCache()` |
| `app/Livewire/Pengaturan/ConfigSatuSehat.php` | + `Cache::forget('satusehat.aktif')` saat `simpan()` |

### 9.3 Perubahan Views

| File | Perubahan |
|------|-----------|
| `resources/views/livewire/pasien/pasien-table.blade.php` | + kolom IHS Status + tombol "Ambil Semua IHS" + progress modal |
| `resources/views/pasien/show.blade.php` | + kartu IHS di kolom kiri |
| `resources/views/livewire/pengaturan/dokter/dokter-table.blade.php` | + kolom IHS Status + tombol "Ambil Semua IHS" |
| `resources/views/pengaturan/dokter/show.blade.php` | + tab `ihs` kondisional |
| `resources/views/livewire/pengaturan/dokter/dokter-ihs-form.blade.php` | **Baru** — konten tab IHS dokter |
| `resources/views/livewire/pengaturan/user/user-table.blade.php` | + kolom IHS Status (kondisional role=perawat) + tombol "Ambil Semua IHS Perawat" |
| `resources/views/livewire/pengaturan/user/user-form.blade.php` | + field NIK (kondisional role=perawat) |

---

## 10. Modal Progress Bulk Fetch

Dipakai di semua 3 modul (Pasien, Dokter, Nakkes), diimplementasikan sebagai shared component Alpine.js + Livewire:

```
┌────────────────────────────────────────────────┐
│  Mengambil IHS ID Pasien...                    │
│                                                │
│  ████████████████░░░░░░░░  68%                │
│  850 / 1.245 diproses                          │
│                                                │
│  ✅ Berhasil    : 812                          │
│  ❌ Tidak ditemukan: 24                        │
│  ⚠️  Error      : 14                           │
│                                                │
│  [ Batalkan ]                 [ Selesai ]      │
│  ↑ hanya saat proses          ↑ muncul setelah │
│    berjalan                     selesai        │
└────────────────────────────────────────────────┘
```

Progress update via `wire:stream` atau `$dispatch` event per iterasi.

---

## 11. Migration Files

| File | Isi |
|------|-----|
| `YYYY_MM_DD_add_ihs_columns_to_pasien_table.php` | `ihs_id`, `ihs_status`, `ihs_synced_at`, `ihs_error_msg` ke `pasien` |
| `YYYY_MM_DD_add_ihs_columns_to_dokter_table.php` | Kolom yang sama ke `dokter` |
| `YYYY_MM_DD_add_nik_ihs_to_perawat_table.php` | `nik` + kolom IHS ke `perawat` |

---

## 12. Checklist Implementasi

### Fase 1 — Database & Service
- [ ] Migration: tambah kolom IHS ke `pasien`
- [ ] Migration: tambah kolom IHS ke `dokter`
- [ ] Migration: tambah `nik` + kolom IHS ke `perawat`
- [ ] Update Model `Pasien`, `Dokter`, `Perawat` — fillable + casts
- [ ] Update `ConfigSatuSehat::aktif()` dengan Cache + `clearCache()` method
- [ ] Update `ConfigSatuSehat` Livewire `simpan()` — panggil `Cache::forget('satusehat.aktif')`
- [ ] Buat `app/Services/SatuSehat/SatuSehatIhsService.php`

### Fase 2 — Modul Pasien
- [ ] `PasienTable.php`: `fetchIhsSatu()`, `fetchIhsSemua()` + progress tracking
- [ ] `pasien-table.blade.php`: kolom IHS Status kondisional + modal progress
- [ ] `pasien/show.blade.php`: kartu IHS kondisional di kolom kiri

### Fase 3 — Modul Dokter
- [ ] `DokterTable.php`: `fetchIhsSatu()`, `fetchIhsSemua()` + progress
- [ ] `dokter-table.blade.php`: kolom IHS Status kondisional + modal progress
- [ ] Buat `DokterIhsForm.php` Livewire component
- [ ] `pengaturan/dokter/show.blade.php`: tambah tab `ihs` kondisional
- [ ] Buat `livewire/pengaturan/dokter/dokter-ihs-form.blade.php`

### Fase 4 — Modul Nakkes (User Management)
- [ ] `UserForm.php`: tambah field `nik` (tampil saat role = `perawat`)
- [ ] `UserTable.php`: `fetchIhsSatu()`, `fetchIhsSemua()` (scope perawat)
- [ ] `user-table.blade.php`: kolom IHS kondisional + modal progress
- [ ] `user-form.blade.php`: field NIK kondisional

### Fase 5 — QA & Commit
- [ ] Guard: semua elemen IHS tersembunyi saat `is_active = false`
- [ ] Guard: elemen muncul kembali setelah SatuSehat diaktifkan (cache clear)
- [ ] Test fetch individu: Pasien, Dokter, Nakkes
- [ ] Test bulk fetch dengan NIK dummy sandbox
- [ ] Test edge case: NIK kosong, rate limit, token expired
- [ ] Commit & push

---

## 13. Dependensi

| Komponen | Status |
|---------|--------|
| `config_satusehat` table + Model + `aktif()` | ✅ Sudah ada |
| Permission `pasien.view`, `masterdata.view` | ✅ Sudah ada |
| Kolom `nik` di `pasien` | ✅ Sudah ada |
| Kolom `nik` di `dokter` | ✅ Sudah ada |
| Kolom `nik` di `perawat` | ❌ Perlu migration |
| Kolom `ihs_id` di semua tabel | ❌ Perlu migration |
| Cache (Laravel default driver) | ✅ Sudah ada |

---

## 14. Catatan Teknis

1. **Token caching per sesi**: Pada `fetchIhsSemua()`, simpan token di property `private ?string $accessToken = null` di service. Ambil sekali, reuse sampai bulk selesai. Jika response 401 di tengah, refresh sekali.

2. **NIK Pasien WNA**: Pasien WNA pakai `no_paspor`. SatuSehat mendukung `https://fhir.kemkes.go.id/id/paspor|{no_paspor}`. Bisa ditambahkan sebagai fallback jika NIK kosong tapi `no_paspor` ada (enhancement fase berikutnya).

3. **NIK Perawat — kenapa di `UserForm`**: Karena tidak ada halaman Perawat tersendiri, dan data perawat selalu dibuat/diupdate bersamaan dengan data User. Menyimpan NIK di `UserForm` adalah tempat yang paling alami.

4. **Kolom IHS di tabel daftar**: Kolom ini bisa membuat tabel terlalu lebar jika layar kecil. Pertimbangkan menggunakan tooltip atau popover untuk menampilkan detail IHS daripada kolom penuh, terutama untuk Pasien yang bisa memiliki ribuan baris.

5. **Bulk fetch hanya yang belum**: Default `fetchIhsSemua()` hanya proses `ihs_status IS NULL OR ihs_status = 'error'`. Tambahkan opsi "Ulangi semua termasuk yang sudah berhasil" untuk re-sync paksa.

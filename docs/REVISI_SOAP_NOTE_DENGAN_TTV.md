# Fitur: Revisi SOAP Note Sekaligus Tanda Vital (TTV)

**Tanggal Implementasi:** November 2026  
**Status:** ✅ Selesai & siap testing  
**Permintaan User:** "Saat revisi SOAP, sebaiknya bisa sekalian revisi TTV juga Pak, bila sedang masuk menu revisi SOAP, bisa revisi TTV juga"

---

## Deskripsi Fitur

Sebelumnya, saat dokter merevisi SOAP Note yang sudah difinalisasi, Tanda Vital (TTV) dari asesmen perawat hanya bisa dilihat read-only. Kini dokter bisa sekaligus merevisi TTV jika ada kesalahan pencatatan.

### Workflow

1. **Buka SOAP Note Final** → TTV ditampilkan read-only di kartu di atas tab SOAP
2. **Klik "Revisi SOAP Note"** → Modal alasan revisi muncul
3. **Isi alasan & konfirmasi** → Mode revisi aktif
4. **Go to Tab Objective** → Form edit TTV muncul (hanya saat mode revisi)
5. **Edit TTV & SOAP fields** sesuai kebutuhan
6. **Simpan Revisi** → TTV disimpan ke `asesmen_perawat`, jejak tercatat di `activity_log`

---

## Perubahan Kode

### 1. **Component: `app/Livewire/Pemeriksaan/SoapNote.php`**

#### **Kolom State TTV** (baris 75–82)
```php
// ── TTV (Tanda Vital) — bisa direvisi saat sedang revisi SOAP ──
public string $beratBadan    = '';
public string $tinggiBadan   = '';
public string $tekananDarah  = '';
public string $nadi          = '';
public string $suhu          = '';
public string $saturasi      = '';
public string $gds           = '';
```

TTV dimuat dari `asesmen_perawat` saat mount component.

#### **Konstanta KOLOM_TTV** (baris 27–34)
```php
/**
 * Kolom TTV di asesmen_perawat yang ikut dicatat before/after saat
 * revisi. TTV disimpan di tabel terpisah (asesmen_perawat), bukan di
 * soap_note, jadi jejaknya dicatat sendiri -- lihat simpanRevisi().
 */
private const KOLOM_TTV = [
    'berat_badan', 'tinggi_badan', 'tekanan_darah', 'nadi', 'suhu', 'saturasi', 'gds',
];
```

#### **Method `loadTtv()`** (baru)
Load nilai TTV dari `asesmen_perawat`. Dipanggil di `mount()` dan `batalRevisi()`.

#### **Method `simpanTtv()`** (baru)
Simpan perubahan TTV ke `asesmen_perawat` via `updateOrCreate()`. Perawat_id dipertahankan, tidak ditimpa.

#### **Method `simpanRevisi()` — diperbaharui**
- Tambah validasi TTV fields
- Capture `ttv_sebelum` sebelum `doSimpan()`
- Simpan TTV via `simpanTtv()` setelah SOAP disimpan
- Jejak audit mencatat both: `ttv_sebelum` dan `ttv_sesudah`

#### **Method `batalRevisi()` — diperbaharui**
- Panggil `loadTtv()` untuk reload ulang TTV

### 2. **View: `resources/views/livewire/pemeriksaan/soap-note.blade.php`**

#### **Kartu TTV — tombol navigasi** (baris 113–120)
Saat `$sedangRevisi === true` dan bukan di tab Objective, tampil tombol "Revisi TTV di tab Objective →".

#### **Form Edit TTV di Tab Objective** (baris 225–271)
Form hanya muncul saat `$sedangRevisi === true`, berisi 8 field input dengan validasi.

---

## Validasi TTV

Saat simpan revisi, TTV divalidasi dengan aturan yang sama seperti di DetailPemeriksaan (Asesmen Perawat):

```php
'beratBadan'   => 'nullable|numeric|min:1|max:500',
'tinggiBadan'  => 'nullable|numeric|min:1|max:300',
'nadi'         => 'nullable|integer|min:1|max:300',
'suhu'         => 'nullable|numeric|min:30|max:45',
'saturasi'     => 'nullable|integer|min:1|max:100',
'gds'          => 'nullable|numeric|min:0|max:9999',
```

Tekanan darah: string format bebas (tidak ada validasi ketat), bisa "120/80" atau apapun.

---

## Jejak Audit

Activity log mencatat:

```
log_name: 'soap_note'
subject_id: <soap_id>
properties: {
    'sebelum': { s_hpi: '...', ... },
    'sesudah': { s_hpi: '...', ... },
    'ttv_sebelum': { berat_badan: 65.5, ... },
    'ttv_sesudah': { berat_badan: 70.0, ... },
    'alasan': 'Koreksi TD yang salah...',
}
log('SOAP Note direvisi setelah finalisasi')
```

---

## Dampak pada Modul Lain

Karena TTV disimpan di `asesmen_perawat` (tabel yang sama, tidak ada tabel baru), otomatis terbaca oleh modul lain:

- **Resume Medis** (`SuratKeteranganService::buildDataResumeMedis()`)
- **Surat Keterangan** (Fit to Fly, Certificate of Health, etc.)
- **Lab/Obat Susulan** (riwayat kunjungan menampilkan TTV)

Tidak perlu perubahan di modul lain.

---

## Test Case

File: `tests/Feature/RevisiSoapTtvTest.php`

Scenario coverage:

1. ✅ Revisi TTV sekaligus SOAP fields
2. ✅ Revisi TTV saja, tanpa ubah SOAP
3. ✅ Validasi TTV (suhu < 30 ditolak, etc.)
4. ✅ Batal revisi → TTV balik ke nilai sebelumnya
5. ✅ Isi TTV baru jika sebelumnya kosong
6. ✅ Jejak audit mencatat TTV null/kosong
7. ✅ Perawat_id asesmen dipertahankan

Jalankan test:
```bash
php artisan test tests/Feature/RevisiSoapTtvTest.php
```

---

## Catatan Pengembang

### Permission
- Hanya role **dokter** (permission `soap.revisi`) yang bisa revisi SOAP + TTV
- Perawat tidak bisa, walau kebetulan dapat `soap.view/edit`

### Hubungan Tabel
- SOAP data: tabel `soap_note`
- TTV data: tabel `asesmen_perawat`
- Jejak revisi: tabel `activity_log`

Saat revisi:
- `soap_note`: update field isi SOAP + `revised_at`, `revised_by`, `revision_count`, `revision_reason`
- `asesmen_perawat`: update field TTV saja, `perawat_id` tidak berubah
- `activity_log`: insert entry baru dengan both-kolom yang berubah

### Sumber Data
- TTV di component diload dari `AsesmenPerawat` (method `loadTtv()`)
- Validasi menggunakan rules yang ada di `DetailPemeriksaan::simpanVitals()`
- Penyimpanan via `AsesmenPerawat::updateOrCreate()`

---

## UI/UX Highlights

1. **Form Edit TTV di Tab Objective**
   - Hanya tampil saat mode revisi aktif
   - Styling amber/kuning untuk highlight bahwa ini revisi
   - Pesan informatif: "Perbaikan di sini menimpa data asesmen perawat..."

2. **Tombol Navigasi Cepat**
   - Saat buka revisi, tombol "Revisi TTV di tab Objective →" muncul
   - Memudahkan navigasi tanpa perlu klik tab manual

3. **Pesan Notifikasi**
   - Success: "Revisi SOAP Note & Tanda Vital berhasil disimpan."
   - Error validation per-field ditampilkan di form

---

## FAQ

**Q: Bisa revisi TTV tanpa revisi SOAP?**  
A: Ya, edit hanya field TTV dan skip SOAP fields — saat simpan, hanya TTV yang akan disimpan.

**Q: Perawat bisa revisi TTV dari sini?**  
A: Tidak, cuma dokter (permission `soap.revisi`). Perawat bisa edit TTV dari tab "Vitals" di DetailPemeriksaan.

**Q: TTV lama di-overwrite atau ada versi history?**  
A: Di-overwrite langsung (no soft-delete), tapi value sebelumnya tercatat di `activity_log` untuk trail audit.

**Q: Bikin TTV baru jika sebelumnya kosong?**  
A: Ya, `updateOrCreate()` akan create entry baru jika belum ada record asesmen_perawat.

---

## Testing (Manual)

1. **Setup:**
   - Login sebagai dokter
   - Buka kunjungan dengan SOAP final + TTV

2. **Test Case:**
   - Klik "Revisi SOAP"
   - Isi alasan revisi
   - Buka tab Objective → lihat form edit TTV
   - Ubah 1-2 nilai TTV (misal TD dari "120/80" → "130/85")
   - Klik "Simpan Revisi"
   - Verifikasi: notifikasi sukses, TTV di kartu atas ter-update

3. **Verifikasi DB:**
   - Cek `asesmen_perawat` → field TTV ter-update
   - Cek `activity_log` → entry baru dengan `ttv_sebelum`/`ttv_sesudah`
   - Cek `soap_note` → field `revised_at`, `revision_count` ter-update

---

## Commit Message

```
feat(soap): Revisi Tanda Vital (TTV) saat revisi SOAP Note

- Tambah field TTV di SoapNote component (loadable saat revisi)
- Form edit TTV tampil di tab Objective saat mode revisi aktif
- Simpan perubahan TTV ke asesmen_perawat dengan jejak audit
- Validasi TTV dengan rules yang sama seperti di Asesmen Perawat
- Perawat_id asesmen dipertahankan, tidak ditimpa dokter yang merevisi
- Batal revisi → TTV balik ke nilai tersimpan
- Tambah test case untuk semua scenario revisi TTV

Permintaan user: "Saat revisi SOAP, bisa sekalian revisi TTV juga"
```

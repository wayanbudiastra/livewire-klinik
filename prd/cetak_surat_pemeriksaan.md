# Product Requirements Document (PRD)
# Modul Pemeriksaan — Cetak Surat (Keterangan Sehat, Sakit, Rujukan, Kontrol)

| Info | Detail |
|:-----|:-------|
| **Versi** | 1.0.0 |
| **Tanggal** | Juni 2026 |
| **Status** | Draft |
| **Depends On** | `pemeriksaan_soap.md` (SOAP & diagnosa ICD-10) · `setup_pemeriksaan.md` · `modul_kasir.md` (pola cetak PDF yang sudah ada) |
| **Tech Stack** | Laravel 12 · Livewire 3 · `barryvdh/laravel-dompdf` (sudah terpasang) |
| **Scope** | 4 jenis surat yang bisa dicetak dari satu kunjungan: Surat Keterangan Sehat, Surat Keterangan Sakit, Surat Rujukan, Surat Kontrol |
| **Prinsip Desain** | Satu tabel generik (`surat_keterangan`) untuk keempat jenis surat, bukan menambah kolom tersebar ke `kunjungan`/`soap_note`/`permintaan_penunjang` — data spesifik per jenis surat disimpan sebagai snapshot JSON di baris suratnya sendiri, supaya isi surat yang sudah pernah dicetak tidak berubah diam-diam kalau SOAP/diagnosa direvisi kemudian |

---

## Daftar Isi

1. [Ringkasan & Kondisi Existing](#1-ringkasan--kondisi-existing)
2. [Tujuan & Non-Tujuan](#2-tujuan--non-tujuan)
3. [Syarat Umum Sebelum Surat Bisa Dicetak](#3-syarat-umum-sebelum-surat-bisa-dicetak)
4. [Surat A — Keterangan Sehat](#4-surat-a--keterangan-sehat)
5. [Surat B — Keterangan Sakit](#5-surat-b--keterangan-sakit)
6. [Surat C — Rujukan](#6-surat-c--rujukan)
7. [Surat D — Kontrol](#7-surat-d--kontrol)
8. [Skema Database](#8-skema-database)
9. [Model, Service, & Komponen](#9-model-service--komponen)
10. [Role & Hak Akses](#10-role--hak-akses)
11. [Keputusan Desain & Batasan yang Disengaja](#11-keputusan-desain--batasan-yang-disengaja)
12. [Fase Implementasi](#12-fase-implementasi)
13. [Out of Scope](#13-out-of-scope)

---

## 1. Ringkasan & Kondisi Existing

### 1.1 Kondisi Existing (audit Juni 2026)

| Area | Status | Keterangan |
|---|---|---|
| Pola cetak PDF | ✅ Ada & terpasang | `barryvdh/laravel-dompdf` sudah dipakai `CetakInvoiceService` (`Pdf::loadView(...)->setPaper('a5','portrait')->download(...)`) — surat ini akan memakai pola yang sama, ukuran A4 (bukan A5 seperti struk). |
| Tracking riwayat cetak | ✅ Ada (untuk invoice) | `cetak_invoice_log` mencatat siapa & kapan invoice dicetak, nomor cetak ke berapa (ORIGINAL/COPY). PRD ini meniru pola yang sama lewat tabel `surat_keterangan`. |
| Data SOAP & diagnosa | ✅ Ada | `SoapNote.icd_codes` (json array `{kode, nama, is_primary}`) — sumber diagnosa untuk Surat Sakit, Rujukan, Kontrol. `SoapNote.is_final` menandai SOAP sudah final. |
| Data dokter untuk kop/tanda tangan | ⚠️ Sebagian | `Dokter.no_sip` + `tgl_expired_sip` ada (dipakai validasi SIP aktif di modul lain). **Tidak ada** kolom gambar tanda tangan/paraf digital — lihat §11.1. |
| Data klinik untuk kop surat | ✅ Ada | `Klinik::profil()` — nama, alamat, telepon, logo, nomor_izin, nama_pimpinan. |
| Tempat tujuan rujukan (RS/dokter spesialis) | ❌ Belum ada di mana pun | Bukan kolom permanen di `permintaan_penunjang` atau modul lain — diisi langsung saat membuat Surat Rujukan, disimpan di `surat_keterangan.data` (lihat §8). |
| Lama istirahat (Surat Sakit) & tanggal kontrol (Surat Kontrol) | ❌ Belum ada | Sama seperti di atas — input manual saat cetak, disimpan sebagai snapshot per surat, **tidak** ditambahkan sebagai kolom permanen ke `kunjungan`/`soap_note` (kunjungan bisa saja tidak butuh kontrol ulang, jadi tidak semestinya jadi field wajib di tabel inti). |

### 1.2 Alur Data

```
Kunjungan (status: selesai/dalam_pemeriksaan) + SoapNote (is_final = true)
        │
        ▼
Dokter buka "Cetak Surat" dari halaman Detail Pemeriksaan, pilih jenis surat
        │
        ▼
Isi field tambahan sesuai jenis surat (lihat §4-7) — mis. lama istirahat,
tujuan rujukan, tanggal kontrol — lalu Preview
        │
        ▼
Submit → surat_keterangan dibuat (snapshot diagnosa & data tambahan,
         nomor surat auto-generate) → PDF di-generate (dompdf, A4) → diunduh
        │
        ▼
Tersimpan di riwayat surat per kunjungan — bisa dicetak ulang (COPY) kapan saja
```

---

## 2. Tujuan & Non-Tujuan

### Tujuan
- Dokter dapat menerbitkan 4 jenis surat resmi langsung dari halaman pemeriksaan kunjungan yang sedang/sudah ditangani, tanpa mengetik ulang data pasien/diagnosa secara manual di Word/aplikasi lain.
- Setiap surat yang pernah dicetak tersimpan sebagai riwayat (nomor surat, isi, siapa yang menandatangani, kapan dicetak) dan bisa dicetak ulang (ditandai sebagai COPY) tanpa mengubah isi aslinya.
- Isi surat yang sudah dicetak **tidak berubah** walau SOAP/diagnosa kunjungan direvisi setelahnya — surat adalah dokumen legal yang harus konsisten dengan kondisi saat diterbitkan.

### Non-Tujuan
- Tidak membangun e-signature/tanda tangan digital terenkripsi — surat dicetak dengan ruang kosong untuk tanda tangan & stempel basah manual (lihat §11.1).
- Tidak membangun template surat yang bisa dikustomisasi bebas oleh user (drag-drop editor dll) — 4 jenis surat punya template tetap, hanya field datanya yang dinamis.
- Tidak terintegrasi dengan sistem rujukan BPJS (Rujukan Online P-Care) — surat rujukan di sini murni dokumen cetak untuk dibawa pasien secara fisik/manual.

---

## 3. Syarat Umum Sebelum Surat Bisa Dicetak

Berlaku untuk **keempat** jenis surat:

1. Kunjungan harus punya `SoapNote` dengan `is_final = true` (pemeriksaan dokter sudah final — tidak masuk akal menerbitkan surat resmi dari asesmen yang masih draft).
2. Dokter yang menandatangani **default** adalah `kunjungan.dokter_id`, tapi bisa diganti manual saat cetak (misal dokter pengganti yang menandatangani) — dipilih dari daftar dokter aktif & SIP masih berlaku (`Dokter::scopeAktifDanSipValid()` yang sudah ada).
3. Setiap surat yang diterbitkan otomatis mendapat nomor surat unik per jenis, format `{PREFIX}-{YYYYMM}-{4 digit urut}` (lihat §9.1 untuk prefix tiap jenis) — meniru pola `GoodsReceipt::generateNomorGr()` yang sudah ada.
4. Surat yang sama (jenis + kunjungan yang sama) **boleh dicetak berkali-kali** (pasien minta salinan tambahan) — setiap cetak ulang membuat baris baru di `surat_keterangan` dengan nomor surat baru (bukan mengedit baris lama), supaya riwayat audit lengkap (siapa minta salinan kapan).

---

## 4. Surat A — Keterangan Sehat

**Kegunaan umum:** melamar kerja, syarat sekolah/kuliah, SIM, dll.

### Field Tambahan Saat Cetak
| Field | Tipe | Wajib | Keterangan |
|---|---|---|---|
| Keperluan | text | tidak | mis. "melamar pekerjaan", dibiarkan kosong kalau tidak perlu disebutkan |

### Isi Surat
- Kop surat klinik (nama, alamat, telepon, logo, nomor izin).
- Nomor surat & tanggal cetak.
- Identitas pasien: nama, NIK, tempat/tanggal lahir (→ umur), jenis kelamin, alamat.
- Hasil pemeriksaan: **ringkasan vitals dari `AsesmenPerawat`** kunjungan ini (tekanan darah, nadi, suhu, BB/TB/BMI) — bukan diagnosa (surat sehat secara default tidak mencantumkan diagnosa, karena tujuannya menyatakan TIDAK ada keluhan berarti).
- Kalimat baku: *"Berdasarkan hasil pemeriksaan, yang bersangkutan dalam keadaan SEHAT dan layak melakukan aktivitas sehari-hari/{keperluan}."*
- Tanda tangan dokter (nama + No. SIP) + ruang stempel klinik.

---

## 5. Surat B — Keterangan Sakit

**Kegunaan umum:** izin tidak masuk kerja/sekolah.

### Field Tambahan Saat Cetak
| Field | Tipe | Wajib | Keterangan |
|---|---|---|---|
| Tanggal mulai istirahat | date | ya | default tanggal kunjungan |
| Lama istirahat (hari) | integer | ya | dipakai hitung tanggal selesai = mulai + lama − 1 |
| Cantumkan diagnosa di surat? | boolean | tidak | default **tidak** (banyak instansi tidak mensyaratkan diagnosa tercantum karena alasan kerahasiaan medis) — kalau diaktifkan, ambil diagnosa utama dari `SoapNote.icd_codes` |

### Isi Surat
- Kop + identitas pasien sama seperti Surat A.
- Kalimat baku: *"Berdasarkan hasil pemeriksaan pada tanggal {tanggal kunjungan}, yang bersangkutan perlu istirahat selama {lama} hari, terhitung mulai tanggal {mulai} sampai dengan {selesai}."*
- Diagnosa (kode + nama ICD-10) **hanya tampil kalau dipilih** opsi "cantumkan diagnosa".
- Tanda tangan dokter + stempel.

---

## 6. Surat C — Rujukan

**Kegunaan umum:** merujuk pasien ke faskes/dokter spesialis lain.

### Field Tambahan Saat Cetak
| Field | Tipe | Wajib | Keterangan |
|---|---|---|---|
| Tujuan Fasilitas/RS | string | ya | nama RS/klinik tujuan, free text (tidak ada master data faskes lain di sistem ini) |
| Tujuan Dokter Spesialis | string | tidak | nama dokter tujuan kalau diketahui |
| Indikasi/Alasan Rujukan | textarea | ya | default terisi dari `SoapNote.a_problems`, bisa diedit |
| Sertakan riwayat pemeriksaan penunjang | boolean | tidak | kalau ya, lampirkan ringkasan `PermintaanPenunjang` kunjungan ini yang sudah `selesai` (nama pemeriksaan + tanggal, bukan hasil detail) |

### Isi Surat
- Kop + identitas pasien.
- Diagnosa lengkap (semua `icd_codes`, bukan hanya primary — dokter tujuan perlu konteks lengkap).
- Tujuan rujukan (fasilitas + dokter kalau diisi).
- Indikasi rujukan.
- Ringkasan pemeriksaan penunjang (kalau dipilih).
- Tanda tangan dokter perujuk + stempel.

---

## 7. Surat D — Kontrol

**Kegunaan umum:** mengingatkan pasien untuk kontrol ulang pada tanggal tertentu.

### Field Tambahan Saat Cetak
| Field | Tipe | Wajib | Keterangan |
|---|---|---|---|
| Tanggal Kontrol | date | ya | harus di masa depan (validasi `after:today`) |
| Instruksi/Pesan | textarea | tidak | default terisi dari `SoapNote.p_advice`, bisa diedit |

### Isi Surat
- Kop + identitas pasien.
- Diagnosa utama (primary saja, untuk konteks singkat).
- Kalimat baku: *"Pasien diminta untuk melakukan kontrol kembali pada tanggal {tanggal_kontrol}."*
- Instruksi tambahan (kalau diisi).
- Tanda tangan dokter + stempel.

---

## 8. Skema Database

### 8.1 Tabel Baru `surat_keterangan`

| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| nomor_surat | string(30) unique | format per jenis, lihat §9.1 |
| kunjungan_id | FK `kunjungan` | |
| tipe | enum | `keterangan_sehat`, `keterangan_sakit`, `rujukan`, `kontrol` |
| dokter_id | FK `dokter` | dokter yang menandatangani (bisa berbeda dari `kunjungan.dokter_id`, lihat §3 poin 2) |
| data | json | field tambahan + **snapshot** diagnosa/data SOAP yang relevan saat surat dibuat (lihat contoh struktur per tipe di §9.2) |
| dicetak_oleh | FK `users` | |
| dicetak_pada | timestamp | |
| timestamps | | |

> **Kenapa satu tabel generik, bukan tabel per jenis surat?** Keempatnya punya kerangka identik (identitas pasien + kop + tanda tangan), hanya berbeda di 2-4 field tambahan dan kalimat baku. Memecah jadi 4 tabel hanya akan menduplikasi kolom inti (`kunjungan_id`, `dokter_id`, `dicetak_oleh`, dst) tanpa manfaat — kolom `data` (json) yang fleksibel sudah cukup menampung perbedaan kecil tadi, mengikuti pola yang sama dengan `jurnal_pending.metadata` (json) yang sudah dipakai di modul akuntansi.

---

## 9. Model, Service, & Komponen

### 9.1 Generate Nomor Surat (per tipe, prefix berbeda)

| Tipe | Prefix | Contoh |
|---|---|---|
| keterangan_sehat | SHT | `SHT-202606-0001` |
| keterangan_sakit | SKT | `SKT-202606-0001` |
| rujukan | RJK | `RJK-202606-0001` |
| kontrol | KTR | `KTR-202606-0001` |

### 9.2 Contoh Struktur `data` (json) per Tipe

```php
// keterangan_sehat
['keperluan' => 'Melamar pekerjaan']

// keterangan_sakit
[
    'tanggal_mulai'      => '2026-06-27',
    'lama_hari'          => 3,
    'tanggal_selesai'    => '2026-06-29',
    'tampilkan_diagnosa' => false,
    'diagnosa_snapshot'  => [['kode' => 'J00', 'nama' => 'Nasopharyngitis akut', 'is_primary' => true]],
]

// rujukan
[
    'tujuan_fasilitas'        => 'RS Sanglah Denpasar',
    'tujuan_dokter'           => 'dr. Spesialis Bedah',
    'indikasi'                => 'Curiga apendisitis akut, perlu evaluasi bedah',
    'diagnosa_snapshot'       => [...semua icd_codes...],
    'penunjang_snapshot'      => [['nama' => 'USG Abdomen', 'tanggal' => '2026-06-26']],
]

// kontrol
[
    'tanggal_kontrol'   => '2026-07-04',
    'instruksi'         => 'Kontrol ulang setelah obat habis.',
    'diagnosa_snapshot' => [['kode' => 'I10', 'nama' => 'Hipertensi', 'is_primary' => true]],
]
```

### 9.3 Model

| Model | Tabel | Catatan |
|---|---|---|
| `App\Models\SuratKeterangan` | `surat_keterangan` | `belongsTo(Kunjungan)`, `belongsTo(Dokter)`, `belongsTo(User, 'dicetak_oleh')`. Cast `data` => `array`. |

### 9.4 Service

| Service | Tanggung Jawab |
|---|---|
| `App\Services\Pemeriksaan\SuratKeteranganService.php` | `cetakSehat()`, `cetakSakit()`, `cetakRujukan()`, `cetakKontrol()` — masing-masing: validasi §3, ambil snapshot data relevan, `SuratKeterangan::create()`, generate nomor surat, lalu render PDF via `Pdf::loadView(...)->setPaper('a4','portrait')->download(...)` (pola identik `CetakInvoiceService`). `riwayat(Kunjungan $kunjungan): Collection` — daftar surat yang pernah dicetak untuk kunjungan ini. |

### 9.5 Livewire Component & Routes

| Route | Komponen/Closure | Permission |
|---|---|---|
| `GET /pemeriksaan/{kunjungan}/surat` | `Pemeriksaan\SuratKeteranganModal` — pilih jenis surat, isi field tambahan, preview ringkas | `surat.cetak` |
| `GET /pemeriksaan/{kunjungan}/surat/{surat}/unduh` | Closure → `SuratKeteranganService::unduhUlang()` (regenerasi PDF dari data yang sudah tersimpan, untuk cetak ulang/COPY) | `surat.cetak` |

Ditambahkan sebagai tombol **"Cetak Surat"** (dropdown 4 pilihan) di halaman `DetailPemeriksaan` yang sudah ada, di sebelah riwayat kunjungan — bukan halaman terpisah, supaya dokter tidak perlu pindah konteks dari layar pemeriksaan.

---

## 10. Role & Hak Akses

| Permission | Deskripsi | Role Disarankan |
|---|---|---|
| `surat.cetak` | Menerbitkan & mencetak ulang keempat jenis surat | Dokter, Admin |

> Satu permission saja untuk keempat jenis surat (bukan 4 permission terpisah) — menerbitkan surat resmi adalah keputusan medis yang melekat pada kewenangan dokter, tidak perlu dipecah granular per jenis surat seperti modul transaksional lain (lihat juga preseden `resep.retur.create` digabung jadi satu permission di `retur_obat_alkes.md` §8 dengan alasan serupa: menghindari over-engineering pada aksi yang secara alami satu paket kewenangan).

---

## 11. Keputusan Desain & Batasan yang Disengaja

1. **Tidak ada tanda tangan digital** — surat dicetak dengan baris kosong "Dokter Pemeriksa, ( _____________ )" di bawah nama & No. SIP, untuk ditandatangani tinta basah + stempel klinik fisik. Ini sesuai praktik klinik kecil-menengah pada umumnya dan menghindari kompleksitas upload/manajemen gambar tanda tangan, validasi keasliannya, dsb. Bisa ditambahkan di fase mendatang sebagai upload gambar opsional per dokter (`dokter.signature_path`) tanpa mengubah struktur `surat_keterangan`.
2. **Snapshot, bukan referensi dinamis** — begitu surat dicetak, `data` (termasuk `diagnosa_snapshot`) **dibekukan**. Kalau SOAP kunjungan itu direvisi setelah surat dicetak, surat yang sudah terbit **tidak ikut berubah** (sesuai sifat dokumen legal — revisi harus jadi surat baru, bukan mengubah riwayat yang sudah ada). Ini sama persis prinsip yang sudah dipakai `InvoiceItem` (snapshot harga saat invoice dibuat, bukan mengikuti harga_jual barang yang berubah-ubah).
3. **Surat Rujukan tanpa master data faskes tujuan** — kolom "Tujuan Fasilitas" sengaja **free text**, bukan dropdown dari tabel master, karena sistem ini tidak (dan untuk saat ini tidak perlu) menyimpan direktori RS/faskes rujukan eksternal. Kalau di masa depan dibutuhkan riwayat "RS mana yang paling sering dirujuk", itu bisa dianalisis dari teks `data->tujuan_fasilitas` di pelaporan, tanpa perlu master data baru sekarang.
4. **PDF ukuran A4, bukan A5** — beda dari struk/invoice kasir (A5) karena ini adalah surat resmi yang lazimnya dicetak di kertas kop A4 klinik, bukan struk thermal.
5. **Tidak ada pembatasan "1 surat per kunjungan per jenis"** — pasien boleh minta salinan tambahan kapan saja (kantor lama minta asli, kantor baru minta lagi, dll). Kontrolnya cukup lewat jejak riwayat (`surat_keterangan` mencatat semua penerbitan) + permission `surat.cetak`, bukan pembatasan jumlah di level sistem.

---

## 12. Fase Implementasi

| Fase | Lingkup | Estimasi |
|---|---|---|
| **Fase 1 — Fondasi** | Migration `surat_keterangan`, model `SuratKeterangan`, `SuratKeteranganService` (skeleton 4 method cetak + generate nomor), permission `surat.cetak` | 2 hari |
| **Fase 2 — Template PDF** | 4 view blade template surat (kop, identitas pasien, isi sesuai §4-7, blok tanda tangan) — desain visual A4 konsisten antar jenis | 2–3 hari |
| **Fase 3 — UI Input & Trigger** | `SuratKeteranganModal` (pilih jenis, form field tambahan per jenis, validasi), tombol "Cetak Surat" di `DetailPemeriksaan`, routes & riwayat surat per kunjungan | 3 hari |
| **Fase 4 — Cetak Ulang & Riwayat** | Tampilan riwayat surat per kunjungan + tombol unduh ulang (regenerasi PDF dari snapshot tersimpan, ditandai COPY di kop surat) | 1–2 hari |

---

## 13. Out of Scope

- Tanda tangan digital/elektronik terverifikasi (lihat §11.1).
- Integrasi Rujukan Online BPJS P-Care.
- Master data direktori rumah sakit/faskes rujukan eksternal.
- Template surat custom/editable oleh user (selain 4 jenis baku ini).
- Pengiriman surat otomatis ke email/WhatsApp pasien — surat hanya diunduh sebagai PDF untuk dicetak manual.
- Jenis surat lain di luar 4 yang diminta (mis. Surat Keterangan Kematian, Surat Visum) — bisa ditambahkan sebagai tipe baru di enum `surat_keterangan.tipe` pada fase mendatang tanpa mengubah struktur tabel.

# Fitur Pemisahan Nama Asuransi dan Nomor Polis - Dokumentasi

## 📋 Overview

Fitur ini memisahkan input asuransi di form pendaftaran pasien dari field tunggal `no_asuransi` (string) menjadi dua field terpisah:

1. **Nama Asuransi** - Dropdown dari master data asuransi
2. **Nomor Polis** - Input text untuk nomor polis spesifik

## 🎯 Perubahan File

### Livewire Component
- **`app/Livewire/Pasien/PasienForm.php`**
  - Replace `no_asuransi` dengan `asuransi_id` + `nomor_polis`
  - Add `getDaftarAsuransiProperty()` method
  - Update `loadEdit()` untuk load asuransi primary
  - Update validation rules
  - Update save() call ke service dengan asuransi data

### Service & Repository
- **`app/Services/PasienService.php`**
  - Update `create()` signature: add `?array $asuransiData`
  - Update `update()` signature: add `?array $asuransiData`

- **`app/Repositories/PasienRepository.php`**
  - Add PasienAsuransi import
  - Update `create()` - insert PasienAsuransi saat baru
  - Update `update()` - update PasienAsuransi saat edit

### View
- **`resources/views/livewire/pasien/pasien-form.blade.php`**
  - Replace field `no_asuransi` dengan dropdown asuransi + nomor polis
  - Nomor polis field bersifat conditional (hanya tampil jika asuransi dipilih)

### Test
- **`tests/Feature/PasienFormAsuransiTest.php`** (BARU)
  - Test menambah pasien dengan asuransi
  - Test edit asuransi
  - Test load data saat edit

## 📊 Data Structure

```
pasien_asuransi table:
├── id (PK)
├── pasien_id (FK → pasien.id)
├── asuransi_id (FK → asuransi.id)
├── nomor_polis (string)
├── is_primary (boolean)
├── is_active (boolean)
└── timestamps
```

## ✅ Validasi Rules

```php
'asuransi_id' => ['nullable', 'integer', 'exists:asuransi,id'],
'nomor_polis' => ['nullable', 'string', 'max:50'],
```

## 🔄 Data Flow saat Create

1. User pilih asuransi + nomor polis di form
2. PasienForm.save() prepare `$asuransiData`
3. Call `service->create($data, $kontak, $asuransiData)`
4. Repository insert Pasien + PasienAsuransi dengan `is_primary=true`

## 🔄 Data Flow saat Update

1. loadEdit() load asuransi primary dari PasienAsuransi
2. User ubah atau hapus asuransi
3. save() delete asuransi lama, insert yang baru
4. Or pass null untuk hapus asuransi sepenuhnya

## 🧪 Test

```bash
php artisan test tests/Feature/PasienFormAsuransiTest.php
```

## ✨ Fitur

- ✅ Asuransi bersifat optional (dapat dihapus)
- ✅ Nomor polis hanya tampil saat asuransi dipilih
- ✅ Support edit/update asuransi
- ✅ Primary asuransi ditandai di database
- ✅ Backward compatible dengan kolom `no_asuransi` lama

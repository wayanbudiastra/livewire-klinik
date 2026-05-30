<?php

namespace App\Livewire\Pengaturan;

use App\Models\Klinik;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

class ProfilKlinik extends Component
{
    use WithFileUploads;

    // Informasi Umum
    public string $nama          = '';
    public string $jenis         = '';
    public string $nomor_izin    = '';
    public string $npwp          = '';
    public string $nama_pimpinan  = '';
    public string $jabatan_pimpinan = '';

    // Lokasi
    public string $alamat   = '';
    public string $kota     = '';
    public string $provinsi = '';
    public string $kode_pos = '';

    // Kontak
    public string $telepon = '';
    public string $fax     = '';
    public string $email   = '';
    public string $website = '';

    // Struk
    public string $header_struk = '';
    public string $footer_struk = '';

    // Logo
    public $logoFile = null;
    public ?string $logoPath = null;  // path yang tersimpan di DB

    public function mount(): void
    {
        $k = Klinik::profil();

        $this->nama              = $k->nama ?? '';
        $this->jenis             = $k->jenis ?? '';
        $this->nomor_izin        = $k->nomor_izin ?? '';
        $this->npwp              = $k->npwp ?? '';
        $this->nama_pimpinan     = $k->nama_pimpinan ?? '';
        $this->jabatan_pimpinan  = $k->jabatan_pimpinan ?? '';
        $this->alamat            = $k->alamat ?? '';
        $this->kota              = $k->kota ?? '';
        $this->provinsi          = $k->provinsi ?? '';
        $this->kode_pos          = $k->kode_pos ?? '';
        $this->telepon           = $k->telepon ?? '';
        $this->fax               = $k->fax ?? '';
        $this->email             = $k->email ?? '';
        $this->website           = $k->website ?? '';
        $this->header_struk      = $k->header_struk ?? '';
        $this->footer_struk      = $k->footer_struk ?? '';
        $this->logoPath          = $k->logo;
    }

    public function simpan(): void
    {
        $this->validate([
            'nama'     => 'required|string|max:255',
            'email'    => 'nullable|email|max:255',
            'website'  => 'nullable|url|max:255',
            'kode_pos' => 'nullable|digits_between:4,6',
            'npwp'     => 'nullable|string|max:30',
            'logoFile' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ], [
            'nama.required' => 'Nama klinik wajib diisi.',
            'email.email'   => 'Format email tidak valid.',
            'website.url'   => 'Format website tidak valid (sertakan https://).',
            'logoFile.image'  => 'File logo harus berupa gambar.',
            'logoFile.mimes'  => 'Format logo harus JPG, PNG, atau WEBP.',
            'logoFile.max'    => 'Ukuran logo maksimal 2 MB.',
        ]);

        $data = [
            'nama'             => $this->nama,
            'jenis'            => $this->jenis,
            'nomor_izin'       => $this->nomor_izin ?: null,
            'npwp'             => $this->npwp ?: null,
            'nama_pimpinan'    => $this->nama_pimpinan ?: null,
            'jabatan_pimpinan' => $this->jabatan_pimpinan ?: null,
            'alamat'           => $this->alamat,
            'kota'             => $this->kota ?: null,
            'provinsi'         => $this->provinsi ?: null,
            'kode_pos'         => $this->kode_pos ?: null,
            'telepon'          => $this->telepon ?: null,
            'fax'              => $this->fax ?: null,
            'email'            => $this->email ?: null,
            'website'          => $this->website ?: null,
            'header_struk'     => $this->header_struk ?: null,
            'footer_struk'     => $this->footer_struk ?: null,
        ];

        if ($this->logoFile) {
            // Hapus logo lama jika ada
            if ($this->logoPath && \Illuminate\Support\Facades\Storage::disk('public')->exists($this->logoPath)) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($this->logoPath);
            }
            $data['logo'] = $this->logoFile->store('klinik', 'public');
            $this->logoPath = $data['logo'];
            $this->logoFile = null;
        }

        $klinik = Klinik::first();
        if ($klinik) {
            $klinik->update($data);
        } else {
            Klinik::create($data);
        }

        $this->dispatch('notify', type: 'success', message: 'Profil klinik berhasil disimpan.');
    }

    public function hapusLogo(): void
    {
        $klinik = Klinik::first();
        if ($klinik && $klinik->logo) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($klinik->logo);
            $klinik->update(['logo' => null]);
            $this->logoPath = null;
        }
    }

    public function render()
    {
        return view('livewire.pengaturan.profil-klinik');
    }
}

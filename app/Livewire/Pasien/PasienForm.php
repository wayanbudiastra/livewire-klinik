<?php

namespace App\Livewire\Pasien;

use App\Models\Pasien;
use App\Services\PasienService;
use Illuminate\Validation\Rule;
use Livewire\Component;

class PasienForm extends Component
{
    public ?int  $pasienId   = null;
    public bool  $isEdit     = false;
    public string $nomorRM   = '';

    // Identitas
    public string $nama           = '';
    public string $tempat_lahir   = '';
    public string $tanggal_lahir  = '';
    public string $jenis_kelamin  = 'L';
    public string $tipe_pasien    = 'WNI';

    // Identifikasi
    public string $nik         = '';
    public string $no_paspor   = '';
    public string $negara_asal = '';
    public string $no_bpjs     = '';

    // Kontak
    public string $alamat  = '';
    public string $telepon = '';
    public string $email   = '';

    // Medis
    public string $golongan_darah = '';
    public string $alergi        = '';
    public string $no_asuransi   = '';

    // Kontak darurat
    public array $kontak = [];

    // ── Kontak Darurat Helpers ───────────────────────────────

    public function addKontak(): void
    {
        $this->kontak[] = [
            'nama'       => '',
            'nomor_hp'   => '',
            'hubungan'   => 'lainnya',
            'alamat'     => '',
            'is_primary' => count($this->kontak) === 0,
        ];
    }

    public function removeKontak(int $index): void
    {
        $wasPrimary = $this->kontak[$index]['is_primary'] ?? false;
        array_splice($this->kontak, $index, 1);
        if ($wasPrimary && count($this->kontak) > 0) {
            $this->kontak[0]['is_primary'] = true;
        }
    }

    public function setPrimaryKontak(int $index): void
    {
        foreach ($this->kontak as $i => $_) {
            $this->kontak[$i]['is_primary'] = ($i === $index);
        }
    }

    public function updatedTipePasien(): void
    {
        if ($this->tipe_pasien === 'WNI') {
            $this->no_paspor   = '';
            $this->negara_asal = '';
        } else {
            $this->nik     = '';
            $this->no_bpjs = '';
        }
    }

    // ── Load Edit ────────────────────────────────────────────

    public function mount(?int $pasienId = null): void
    {
        if ($pasienId) {
            $this->loadEdit($pasienId);
        }
    }

    public function loadEdit(int $pasienId): void
    {
        $p = Pasien::with('kontakDarurat')->findOrFail($pasienId);
        $this->pasienId      = $pasienId;
        $this->isEdit        = true;
        $this->nomorRM       = $p->nomor_rm;
        $this->nama          = $p->nama;
        $this->tempat_lahir  = $p->tempat_lahir;
        $this->tanggal_lahir = $p->tanggal_lahir->format('Y-m-d');
        $this->jenis_kelamin = $p->jenis_kelamin;
        $this->tipe_pasien   = $p->tipe_pasien;
        $this->nik           = $p->nik           ?? '';
        $this->no_paspor     = $p->no_paspor     ?? '';
        $this->negara_asal   = $p->negara_asal   ?? '';
        $this->no_bpjs       = $p->no_bpjs       ?? '';
        $this->alamat        = $p->alamat;
        $this->telepon       = $p->telepon;
        $this->email         = $p->email         ?? '';
        $this->golongan_darah= $p->golongan_darah ?? '';
        $this->alergi        = $p->alergi        ?? '';
        $this->no_asuransi   = $p->no_asuransi   ?? '';
        $this->kontak        = $p->kontakDarurat->map(fn ($k) => [
            'nama'       => $k->nama,
            'nomor_hp'   => $k->nomor_hp,
            'hubungan'   => $k->hubungan,
            'alamat'     => $k->alamat ?? '',
            'is_primary' => $k->is_primary,
        ])->toArray();
    }

    // ── Validation Rules ─────────────────────────────────────

    public function getRules(): array
    {
        $id = $this->pasienId;
        return [
            'nama'          => ['required', 'string', 'min:3', 'max:100'],
            'tempat_lahir'  => ['required', 'string', 'min:2', 'max:100'],
            'tanggal_lahir' => ['required', 'date', 'before_or_equal:today'],
            'jenis_kelamin' => ['required', 'in:L,P'],
            'tipe_pasien'   => ['required', 'in:WNI,WNA'],
            'nik'           => ['nullable', 'string', 'size:16', 'regex:/^\d{16}$/',
                                $id ? Rule::unique('pasien','nik')->ignore($id) : 'unique:pasien,nik'],
            'no_bpjs'       => ['nullable', 'string', 'size:13',
                                $id ? Rule::unique('pasien','no_bpjs')->ignore($id) : 'unique:pasien,no_bpjs'],
            'no_paspor'     => ['nullable', 'string', 'min:5', 'max:20',
                                $id ? Rule::unique('pasien','no_paspor')->ignore($id) : 'unique:pasien,no_paspor'],
            'negara_asal'   => ['nullable', 'string', 'max:100'],
            'alamat'        => ['required', 'string', 'min:10', 'max:500'],
            'telepon'       => ['required', 'string', 'regex:/^(\+62|62|0)[0-9]{8,13}$/'],
            'email'         => ['nullable', 'email'],
            'golongan_darah'=> ['nullable', 'in:A,B,AB,O,tidak_diketahui'],
            'alergi'        => ['nullable', 'string', 'max:1000'],
            'no_asuransi'   => ['nullable', 'string', 'max:50'],
            'kontak.*.nama'     => ['required_with:kontak', 'string', 'min:3'],
            'kontak.*.nomor_hp' => ['required_with:kontak', 'string',
                                    'regex:/^(\+62|62|0)[0-9]{8,13}$/'],
            'kontak.*.hubungan' => ['required_with:kontak',
                                    'in:suami,istri,ayah,ibu,anak,kakak,adik,kakek,nenek,paman,bibi,keponakan,teman,rekan_kerja,lainnya'],
        ];
    }

    public function getMessages(): array
    {
        return [
            'nik.size'           => 'NIK harus tepat 16 digit.',
            'nik.regex'          => 'NIK hanya boleh angka.',
            'nik.unique'         => 'NIK sudah terdaftar.',
            'no_bpjs.size'       => 'BPJS harus tepat 13 digit.',
            'telepon.regex'      => 'Format telepon tidak valid (08xx).',
            'alamat.min'         => 'Alamat minimal 10 karakter.',
            'kontak.*.nomor_hp.regex' => 'Format HP kontak tidak valid.',
        ];
    }

    // ── Save ─────────────────────────────────────────────────

    public function save(PasienService $service)
    {
        $validated = $this->validate($this->getRules(), $this->getMessages());

        // Validasi kondisional WNI/WNA
        if ($this->tipe_pasien === 'WNI' && empty($this->nik)) {
            $this->addError('nik', 'NIK wajib diisi untuk pasien WNI.');
            return;
        }
        if ($this->tipe_pasien === 'WNA') {
            if (empty($this->no_paspor)) {
                $this->addError('no_paspor', 'No. Paspor wajib untuk pasien WNA.');
                return;
            }
            if (empty($this->negara_asal)) {
                $this->addError('negara_asal', 'Negara asal wajib untuk pasien WNA.');
                return;
            }
        }

        $data = [
            'nama'           => $this->nama,
            'tempat_lahir'   => $this->tempat_lahir,
            'tanggal_lahir'  => $this->tanggal_lahir,
            'jenis_kelamin'  => $this->jenis_kelamin,
            'tipe_pasien'    => $this->tipe_pasien,
            'nik'            => $this->nik            ?: null,
            'no_paspor'      => $this->no_paspor      ? strtoupper($this->no_paspor) : null,
            'negara_asal'    => $this->negara_asal     ?: null,
            'no_bpjs'        => $this->no_bpjs         ?: null,
            'alamat'         => $this->alamat,
            'telepon'        => $this->telepon,
            'email'          => $this->email           ?: null,
            'golongan_darah' => $this->golongan_darah  ?: null,
            'alergi'         => $this->alergi          ?: null,
            'no_asuransi'    => $this->no_asuransi      ?: null,
        ];

        try {
            if ($this->isEdit) {
                $data['pasien_id'] = $this->pasienId;
                $service->update($this->pasienId, $data, $this->kontak ?: null);
                $this->dispatch('notify', type: 'success', message: 'Data pasien berhasil diupdate.');
                $this->dispatch('pasien-saved');
            } else {
                $pasien = $service->create($data, $this->kontak);
                $this->dispatch('notify', type: 'success',
                    message: "Pasien berhasil didaftarkan. No. RM: {$pasien->nomor_rm}");
                return redirect()->route('pasien.show', $pasien->id);
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->addError($field, $messages[0]);
            }
            $this->dispatch('notify', type: 'error', message: $e->errors()[array_key_first($e->errors())][0]);
        } catch (\Exception $e) {
            $this->dispatch('notify', type: 'error', message: 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    public function getHubunganOptionsProperty(): array
    {
        return Pasien::getHubunganOptions();
    }

    public function render()
    {
        return view('livewire.pasien.pasien-form');
    }
}

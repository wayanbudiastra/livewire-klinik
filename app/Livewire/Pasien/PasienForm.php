<?php

namespace App\Livewire\Pasien;

use App\Models\Asuransi;
use App\Models\Pasien;
use App\Models\PasienAsuransi;
use App\Models\SumberInformasi;
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
    
    // Asuransi
    public ?int $asuransi_id    = null;
    public string $nomor_polis  = '';

    // Kontak darurat
    public array $kontak = [];
    public ?int $deleteKontakIndex = null;  // For delete confirmation dialog
    public bool $showDeleteConfirm = false;
    public ?int $copiedPhoneIndex = null;   // Track which phone was copied for feedback

    // Sumber Informasi
    public ?int  $sumberInformasiId     = null;
    public string $sumberKeterangan     = '';
    public bool  $sumberButuhKeterangan = false;

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

    public function confirmDeleteKontak(int $index): void
    {
        // Cegah hapus jika hanya tersisa 1 kontak (mandatory requirement)
        if (count($this->kontak) <= 1) {
            $this->addError('kontak', 'Minimal 1 kontak darurat harus tetap ada. Tambahkan kontak baru sebelum menghapus kontak ini.');
            return;
        }

        // Show confirmation dialog
        $this->deleteKontakIndex = $index;
        $this->showDeleteConfirm = true;
    }

    public function cancelDeleteKontak(): void
    {
        $this->deleteKontakIndex = null;
        $this->showDeleteConfirm = false;
    }

    public function copyPhoneNumber(int $index): void
    {
        if (isset($this->kontak[$index]['nomor_hp'])) {
            // Mark as copied for visual feedback
            $this->copiedPhoneIndex = $index;
            
            // Reset after 2 seconds
            $this->dispatch('resetCopiedPhone', delay: 2000);
        }
    }

    #[\Livewire\Attributes\On('resetCopiedPhone')]
    public function resetCopiedPhone(): void
    {
        $this->copiedPhoneIndex = null;
    }

    public function removeKontak(int $index): void
    {
        // Cegah hapus jika hanya tersisa 1 kontak (mandatory requirement)
        if (count($this->kontak) <= 1) {
            $this->addError('kontak', 'Minimal 1 kontak darurat harus tetap ada. Tambahkan kontak baru sebelum menghapus kontak ini.');
            return;
        }

        // Store info sebelum di-splice
        $namaKontak = $this->kontak[$index]['nama'] ?? 'Unknown';
        $wasPrimary = $this->kontak[$index]['is_primary'] ?? false;
        
        // Hapus kontak dari array
        array_splice($this->kontak, $index, 1);
        
        // Update primary jika yang dihapus adalah primary
        if ($wasPrimary && count($this->kontak) > 0) {
            $this->kontak[0]['is_primary'] = true;
        }

        // Close dialog
        $this->showDeleteConfirm = false;
        $this->deleteKontakIndex = null;
    }

    public function setPrimaryKontak(int $index): void
    {
        foreach ($this->kontak as $i => $_) {
            $this->kontak[$i]['is_primary'] = ($i === $index);
        }
    }

    public function updatedTipePasien(): void
    {
        // Mode edit: kalau user kembali ke tipe asal pasien (mis. salah klik
        // WNA lalu balik ke WNI), pulihkan data identitas dari database
        // supaya NIK/BPJS/Paspor yang sudah tersimpan tidak ikut hilang.
        if ($this->isEdit && $this->pasienId) {
            $p = Pasien::find($this->pasienId);
            if ($p && $p->tipe_pasien === $this->tipe_pasien) {
                $this->nik         = $p->nik         ?? '';
                $this->no_bpjs     = $p->no_bpjs     ?? '';
                $this->no_paspor   = $p->no_paspor   ?? '';
                $this->negara_asal = $p->negara_asal ?? '';
                $this->resetErrorBag(['nik', 'no_bpjs', 'no_paspor', 'negara_asal']);
                return;
            }
        }

        $this->resetErrorBag(['nik', 'no_bpjs', 'no_paspor', 'negara_asal']);

        if ($this->tipe_pasien === 'WNI') {
            $this->no_paspor   = '';
            $this->negara_asal = '';
        } else {
            $this->nik     = '';
            $this->no_bpjs = '';
        }
    }

    public function updatedSumberInformasiId($value): void
    {
        if ($value) {
            $sumber = SumberInformasi::find($value);
            $this->sumberButuhKeterangan = $sumber?->butuh_keterangan ?? false;
            if (!$this->sumberButuhKeterangan) {
                $this->sumberKeterangan = '';
            }
        } else {
            $this->sumberButuhKeterangan = false;
            $this->sumberKeterangan      = '';
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
        
        // Load asuransi primary jika ada
        $asuransiPrimary = PasienAsuransi::where('pasien_id', $pasienId)
            ->where('is_active', true)
            ->where('is_primary', true)
            ->first();
        if ($asuransiPrimary) {
            $this->asuransi_id  = $asuransiPrimary->asuransi_id;
            $this->nomor_polis  = $asuransiPrimary->nomor_polis;
        }
        
        $this->kontak        = $p->kontakDarurat->map(fn ($k) => [
            'nama'       => $k->nama,
            'nomor_hp'   => $k->nomor_hp,
            'hubungan'   => $k->hubungan,
            'alamat'     => $k->alamat ?? '',
            'is_primary' => $k->is_primary,
        ])->toArray();

        // Sumber informasi (mode edit — isi jika ada, tidak wajib)
        $this->sumberInformasiId     = $p->sumber_informasi_id;
        $this->sumberKeterangan      = $p->sumber_informasi_keterangan ?? '';
        $this->sumberButuhKeterangan = $p->sumberInformasi?->butuh_keterangan ?? false;
    }

    // ── Validation Rules ─────────────────────────────────────

    public function getRules(): array
    {
        $id = $this->pasienId;
        $rules = [
            'nama'          => ['required', 'string', 'min:3', 'max:100'],
            'tempat_lahir'  => ['required', 'string', 'min:2', 'max:100'],
            'tanggal_lahir' => ['required', 'date', 'before_or_equal:today'],
            'jenis_kelamin' => ['required', 'in:L,P'],
            'tipe_pasien'   => ['required', 'in:WNI,WNA'],
            'nik'           => ['nullable', 'string', 'size:16', 'regex:/^\d{16}$/',
                                $id ? Rule::unique('pasien','nik')->ignore($id) : 'unique:pasien,nik'],
            'no_bpjs'       => ['nullable', 'string', 'size:13', 'regex:/^\d{13}$/',
                                $id ? Rule::unique('pasien','no_bpjs')->ignore($id) : 'unique:pasien,no_bpjs'],
            'no_paspor'     => ['nullable', 'string', 'min:5', 'max:20',
                                $id ? Rule::unique('pasien','no_paspor')->ignore($id) : 'unique:pasien,no_paspor'],
            'negara_asal'   => ['nullable', 'string', 'max:100'],
            'alamat'        => ['required', 'string', 'max:500'],
            // Terima format Indonesia (08xx/+62/62) maupun nomor luar negeri
            // (pasien WNA) -- boleh diawali + dan berisi spasi/tanda hubung,
            // jadi tidak dipaksa pola 08xx saja.
            'telepon'       => ['required', 'string', 'regex:/^\+?[0-9\s\-]{6,20}$/'],
            'email'         => ['nullable', 'email'],
            'golongan_darah'=> ['nullable', 'in:A,B,AB,O,tidak_diketahui'],
            'alergi'        => ['required', 'string', 'max:1000'],
            'asuransi_id'   => ['nullable', 'integer', 'exists:asuransi,id'],
            'nomor_polis'   => ['nullable', 'string', 'max:50'],
            // Kontak Darurat — MANDATORY (minimal 1 kontak)
            'kontak'            => ['required', 'array', 'min:1'],
            'kontak.*.nama'     => ['required', 'string', 'min:3'],
            'kontak.*.nomor_hp' => ['required', 'string',
                                    'regex:/^(\+62|62|0)[0-9]{8,13}$/'],
            'kontak.*.hubungan' => ['required',
                                    'in:suami,istri,ayah,ibu,anak,kakak,adik,kakek,nenek,paman,bibi,keponakan,teman,rekan_kerja,lainnya'],
        ];

        if (!$this->isEdit) {
            $rules['sumberInformasiId'] = ['required', 'exists:sumber_informasi,id'];
            if ($this->sumberButuhKeterangan) {
                $rules['sumberKeterangan'] = ['required', 'string', 'min:3', 'max:200'];
            }
        }

        return $rules;
    }

    public function getMessages(): array
    {
        return [
            'nik.size'                   => 'NIK harus tepat 16 digit.',
            'nik.regex'                  => 'NIK hanya boleh angka.',
            'nik.unique'                 => 'NIK sudah terdaftar.',
            'no_bpjs.size'               => 'BPJS harus tepat 13 digit.',
            'no_bpjs.regex'              => 'BPJS hanya boleh berisi angka.',
            'telepon.regex'              => 'Format nomor telepon tidak valid.',
            'alergi.required'            => 'Riwayat alergi wajib diisi. Tulis "Tidak ada alergi" jika tidak ada.',
            
            // Kontak Darurat — error messages
            'kontak.required'            => 'Minimal 1 kontak darurat wajib ditambahkan.',
            'kontak.min'                 => 'Minimal 1 kontak darurat wajib ditambahkan.',
            'kontak.*.nama.required'     => 'Nama kontak wajib diisi.',
            'kontak.*.nomor_hp.required' => 'No. HP kontak wajib diisi.',
            'kontak.*.nomor_hp.regex'    => 'Format HP kontak tidak valid.',
            'kontak.*.hubungan.required' => 'Hubungan kontak wajib dipilih.',
            
            // Duplikasi kontak
            // 'kontak.*.nama' dan 'kontak.*.nomor_hp' untuk duplikasi ditangani di save()
            
            'sumberInformasiId.required' => 'Mohon pilih sumber informasi pasien.',
            'sumberInformasiId.exists'   => 'Sumber informasi tidak valid.',
            'sumberKeterangan.required'  => 'Mohon isi keterangan sumber informasi.',
            'sumberKeterangan.min'       => 'Keterangan minimal 3 karakter.',
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

        // Validasi nomor HP kontak tidak boleh sama dengan nomor pasien
        foreach ($this->kontak as $index => $k) {
            if (!empty($k['nomor_hp']) && $k['nomor_hp'] === $this->telepon) {
                $this->addError("kontak.{$index}.nomor_hp", 'Nomor HP kontak tidak boleh sama dengan nomor telepon pasien.');
                return;
            }
        }

        // Validasi duplikasi kontak dalam form (nama atau nomor HP yang sama)
        $namaList = [];
        $nomorHpList = [];
        foreach ($this->kontak as $index => $k) {
            $namaLower = strtolower(trim($k['nama'] ?? ''));
            $nomorHp = trim($k['nomor_hp'] ?? '');
            
            // Check duplikasi nama
            if (!empty($namaLower)) {
                if (in_array($namaLower, $namaList)) {
                    $this->addError("kontak.{$index}.nama", 'Nama kontak "' . $k['nama'] . '" sudah ada dalam daftar.');
                    return;
                }
                $namaList[] = $namaLower;
            }
            
            // Check duplikasi nomor HP
            if (!empty($nomorHp)) {
                if (in_array($nomorHp, $nomorHpList)) {
                    $this->addError("kontak.{$index}.nomor_hp", 'Nomor HP kontak sudah ada dalam daftar.');
                    return;
                }
                $nomorHpList[] = $nomorHp;
            }
        }

        $data = [
            'nama'                         => $this->nama,
            'tempat_lahir'                 => $this->tempat_lahir,
            'tanggal_lahir'                => $this->tanggal_lahir,
            'jenis_kelamin'                => $this->jenis_kelamin,
            'tipe_pasien'                  => $this->tipe_pasien,
            'nik'                          => $this->nik            ?: null,
            'no_paspor'                    => $this->no_paspor      ? strtoupper($this->no_paspor) : null,
            'negara_asal'                  => $this->negara_asal     ?: null,
            'no_bpjs'                      => $this->no_bpjs         ?: null,
            'alamat'                       => $this->alamat,
            'telepon'                      => $this->telepon,
            'email'                        => $this->email           ?: null,
            'golongan_darah'               => $this->golongan_darah  ?: null,
            'alergi'                       => $this->alergi          ?: null,
            'sumber_informasi_id'          => $this->sumberInformasiId ?: null,
            'sumber_informasi_keterangan'  => $this->sumberButuhKeterangan ? ($this->sumberKeterangan ?: null) : null,
        ];
        
        // Prepare asuransi data if provided
        $asuransiData = null;
        if ($this->asuransi_id && !empty($this->nomor_polis)) {
            $asuransiData = [
                'asuransi_id' => $this->asuransi_id,
                'nomor_polis' => $this->nomor_polis,
            ];
        }

        // Pastikan identitas tipe lain dikosongkan (penting saat edit WNI <-> WNA,
        // agar NIK/BPJS lama tidak tertinggal di pasien WNA dan sebaliknya).
        if ($this->tipe_pasien === 'WNA') {
            $data['nik']     = null;
            $data['no_bpjs'] = null;
        } else {
            $data['no_paspor']   = null;
            $data['negara_asal'] = null;
        }

        try {
            if ($this->isEdit) {
                $data['pasien_id'] = $this->pasienId;
                $service->update($this->pasienId, $data, $this->kontak ?: null, $asuransiData);
                $this->dispatch('notify', type: 'success', message: 'Data pasien berhasil diupdate.');
                $this->dispatch('pasien-saved');
            } else {
                $pasien = $service->create($data, $this->kontak, $asuransiData);
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

    public function getDaftarAsuransiProperty()
    {
        return Asuransi::where('is_active', true)->orderBy('nama')->get();
    }

    public function render()
    {
        return view('livewire.pasien.pasien-form', [
            'daftarSumber'   => SumberInformasi::active()->get(),
            'daftarAsuransi' => $this->daftarAsuransi,
        ]);
    }
}

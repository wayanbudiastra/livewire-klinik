<?php

namespace App\Livewire\Akuntansi;

use App\Models\Akuntansi\ChartOfAccount;
use App\Services\Akuntansi\JurnalManualService;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

class JurnalManualForm extends Component
{
    use WithFileUploads;

    public string $tanggal          = '';
    public string $kategori         = '';
    public string $kodeAkunDebit    = '';
    public string $kodeAkunKredit   = '';
    public string $nominal          = '';
    public string $keterangan       = '';
    public $dokumenPendukung        = null;

    /** Saran akun debit/kredit per kategori -- hanya prefill, tetap bisa diubah manual. */
    private const SARAN_AKUN = [
        'listrik'           => ['debit' => '5-3100', 'kredit' => '1-1100'],
        'air'                => ['debit' => '5-3100', 'kredit' => '1-1100'],
        'internet_telepon'  => ['debit' => '5-3100', 'kredit' => '1-1100'],
        'sewa'               => ['debit' => '5-3100', 'kredit' => '1-1100'],
        'gaji_non_dokter'   => ['debit' => '5-3100', 'kredit' => '1-1100'],
        'pajak_retribusi'   => ['debit' => '5-3100', 'kredit' => '1-1100'],
        'suntik_modal'      => ['debit' => '1-1100', 'kredit' => '3-1100'],
        'lainnya'            => ['debit' => '',       'kredit' => ''],
    ];

    public function mount(): void
    {
        $this->tanggal = now()->format('Y-m-d');
    }

    public function updatedKategori(string $value): void
    {
        $saran = self::SARAN_AKUN[$value] ?? null;
        if (! $saran) return;

        $this->kodeAkunDebit  = $saran['debit'];
        $this->kodeAkunKredit = $saran['kredit'];
    }

    #[Computed]
    public function daftarAkun()
    {
        return ChartOfAccount::aktif()->orderBy('kode')->get();
    }

    #[Computed]
    public function daftarKategori(): array
    {
        return [
            'listrik'          => 'Listrik',
            'air'               => 'Air',
            'internet_telepon' => 'Internet & Telepon',
            'sewa'              => 'Sewa',
            'gaji_non_dokter'  => 'Gaji Karyawan Non-Dokter',
            'pajak_retribusi'  => 'Pajak & Retribusi',
            'suntik_modal'     => 'Suntik Modal Pemilik',
            'lainnya'           => 'Lainnya',
        ];
    }

    public function simpan(JurnalManualService $service): void
    {
        $this->validate([
            'tanggal'          => ['required', 'date', 'before_or_equal:today'],
            'kategori'         => ['nullable', 'in:listrik,air,internet_telepon,sewa,gaji_non_dokter,pajak_retribusi,suntik_modal,lainnya'],
            'kodeAkunDebit'    => ['required', 'exists:chart_of_accounts,kode', 'different:kodeAkunKredit'],
            'kodeAkunKredit'   => ['required', 'exists:chart_of_accounts,kode'],
            'nominal'          => ['required', 'numeric', 'min:0.01'],
            'keterangan'       => ['required', 'string', 'max:255'],
            'dokumenPendukung' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ], [
            'tanggal.before_or_equal'    => 'Tanggal tidak boleh di masa depan.',
            'kodeAkunDebit.different'    => 'Akun debit dan akun kredit tidak boleh sama.',
            'dokumenPendukung.mimes'     => 'Dokumen harus berformat PDF, JPG, atau PNG.',
            'dokumenPendukung.max'       => 'Ukuran dokumen maksimal 5 MB.',
        ]);

        try {
            $path = $this->dokumenPendukung?->store('jurnal-manual', 'public');

            $service->buat([
                'tanggal'           => $this->tanggal,
                'kategori'          => $this->kategori ?: null,
                'kode_akun_debit'   => $this->kodeAkunDebit,
                'kode_akun_kredit'  => $this->kodeAkunKredit,
                'nominal'           => (float) $this->nominal,
                'keterangan'        => $this->keterangan,
                'dokumen_pendukung' => $path,
            ], auth()->id());

            session()->flash('success', 'Jurnal manual berhasil dicatat, menunggu posting di Jurnal Pending.');
            $this->redirect(route('akuntansi.jurnal-manual'));
        } catch (\DomainException $e) {
            $this->addError('tanggal', $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.akuntansi.jurnal-manual-form');
    }
}

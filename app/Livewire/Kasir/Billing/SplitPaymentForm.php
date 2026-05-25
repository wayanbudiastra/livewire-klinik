<?php

namespace App\Livewire\Kasir\Billing;

use Livewire\Attributes\Computed;
use Livewire\Component;
use App\Models\{Invoice, DepositPasien, Kunjungan};
use App\Services\Kasir\{BillingService, SesiKasService};

class SplitPaymentForm extends Component
{
    public Invoice $billing;
    public float   $sisaTagihan     = 0;
    public float   $totalSudahDiisi = 0;
    public float   $saldoDeposit    = 0;

    public array  $splitItems = [];

    public string $metode      = 'tunai';
    public string $jumlahInput = '';
    public string $referensi   = '';
    public string $namaAsuransi = '';
    public string $nomorPolis   = '';
    public string $jumlahCover  = '';

    public array $metodeList = [
        'tunai'    => 'Tunai',
        'debit'    => 'Kartu Debit',
        'kredit'   => 'Kartu Kredit',
        'transfer' => 'Transfer Bank',
        'qris'     => 'QRIS / E-Wallet',
        'bpjs'     => 'BPJS',
        'asuransi' => 'Asuransi Swasta',
        'deposit'  => 'Deposit Pasien',
    ];

    public function mount(Invoice $billing): void
    {
        $this->billing     = $billing;
        $this->sisaTagihan = (float) $billing->sisa;

        $pasienId         = $billing->kunjungan->pasien_id;
        $deposit          = DepositPasien::where('pasien_id', $pasienId)->first();
        $this->saldoDeposit = (float) ($deposit?->saldo ?? 0);
    }

    protected function rules(): array
    {
        $rules = [
            'metode'      => ['required', 'in:tunai,debit,kredit,transfer,qris,bpjs,asuransi,deposit'],
            'jumlahInput' => ['required', 'numeric', 'min:0.01'],
        ];

        if ($this->metode === 'bpjs') {
            $rules['referensi'] = ['required', 'string', 'max:100'];
        }
        if ($this->metode === 'asuransi') {
            $rules['namaAsuransi'] = ['required', 'string', 'max:100'];
            $rules['nomorPolis']   = ['required', 'string', 'max:50'];
            $rules['jumlahCover']  = ['required', 'numeric', 'min:0'];
        }

        return $rules;
    }

    public function tambahItem(): void
    {
        $this->validate();

        $jumlah = (float) $this->jumlahInput;

        if ($this->metode === 'deposit') {
            $depositTerpakai = collect($this->splitItems)->where('metode', 'deposit')->sum('jumlah');

            if ($depositTerpakai + $jumlah > $this->saldoDeposit) {
                $this->addError('jumlahInput',
                    'Saldo deposit tidak cukup. Tersedia: Rp ' .
                    number_format($this->saldoDeposit - $depositTerpakai, 0, ',', '.')
                );
                return;
            }
        }

        if ($this->totalSudahDiisi + $jumlah > $this->sisaTagihan + 0.01) {
            $this->addError('jumlahInput',
                'Total melebihi sisa tagihan (Rp ' . number_format($this->sisaTagihan, 0, ',', '.') . ').'
            );
            return;
        }

        $this->splitItems[] = [
            'metode'        => $this->metode,
            'label'         => $this->metodeList[$this->metode],
            'jumlah'        => $jumlah,
            'referensi'     => $this->referensi ?: null,
            'nama_asuransi' => $this->namaAsuransi ?: null,
            'nomor_polis'   => $this->nomorPolis ?: null,
            'jumlah_cover'  => $this->jumlahCover ? (float) $this->jumlahCover : null,
            'jumlah_pasien' => $this->metode === 'asuransi'
                ? ($jumlah - (float) $this->jumlahCover)
                : null,
        ];

        $this->totalSudahDiisi = collect($this->splitItems)->sum('jumlah');
        $this->resetItemForm();
    }

    public function hapusItem(int $index): void
    {
        array_splice($this->splitItems, $index, 1);
        $this->totalSudahDiisi = collect($this->splitItems)->sum('jumlah');
    }

    public function isiOtomatis(): void
    {
        $sisa = $this->sisaTagihan - $this->totalSudahDiisi;
        $this->jumlahInput = (string) max(0, $sisa);
    }

    #[Computed]
    public function hasPendingResep(): bool
    {
        $kunjungan = Kunjungan::find($this->billing->kunjungan_id);
        return $kunjungan?->resep()->where('is_locked', false)->exists() ?? false;
    }

    public function konfirmasi(BillingService $billingService, SesiKasService $sesiKasService): void
    {
        if ($this->hasPendingResep) {
            $this->addError('global', 'Masih ada resep obat yang belum dikonfirmasi apoteker. Selesaikan terlebih dahulu sebelum proses pembayaran.');
            return;
        }

        if (empty($this->splitItems)) {
            $this->addError('global', 'Tambahkan minimal 1 metode pembayaran.');
            return;
        }

        $totalSplit = collect($this->splitItems)->sum('jumlah');
        if (abs($totalSplit - $this->sisaTagihan) > 0.01) {
            $this->addError('global', 'Total pembayaran belum sesuai sisa tagihan.');
            return;
        }

        $sesiKas = $sesiKasService->getSesiAktif(auth()->id());
        if (!$sesiKas) {
            $this->addError('global', 'Kas belum dibuka. Buka kas terlebih dahulu.');
            return;
        }

        try {
            $billingService->prosesSplitPayment(
                $this->billing,
                $this->splitItems,
                auth()->id(),
                $sesiKas
            );

            session()->flash('success', "Invoice {$this->billing->nomor_invoice} berhasil dilunasi.");
            $this->redirectRoute('kasir.billing.show', $this->billing);
        } catch (\Exception $e) {
            $this->addError('global', $e->getMessage());
        }
    }

    private function resetItemForm(): void
    {
        $this->metode       = 'tunai';
        $this->jumlahInput  = '';
        $this->referensi    = '';
        $this->namaAsuransi = '';
        $this->nomorPolis   = '';
        $this->jumlahCover  = '';
    }

    public function render()
    {
        return view('livewire.kasir.billing.split-payment-form');
    }
}

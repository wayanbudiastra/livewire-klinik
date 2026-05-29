<div class="space-y-5">

    @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    {{-- Header Info --}}
    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="text-base font-semibold text-gray-900 dark:text-white font-mono">
                    {{ $transaksi->nomor_ritel }}
                </h3>
                <p class="text-xs text-gray-400 mt-0.5">{{ $transaksi->created_at->format('d M Y, H:i') }}</p>
            </div>
            @php
            $statusClass = match($transaksi->status) {
                'draft'          => 'badge-warning',
                'menunggu_kasir' => 'badge-info',
                'dibayar'        => 'badge-primary',
                'selesai'        => 'badge-success',
                'dibatalkan'     => 'badge-gray',
                default          => 'badge-gray',
            };
            @endphp
            <span class="badge {{ $statusClass }} text-sm px-3 py-1">{{ $transaksi->status_label }}</span>
        </div>
        <div class="card-body grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
            <div>
                <p class="text-xs text-gray-400 uppercase tracking-wide">Nama Pembeli</p>
                <p class="font-semibold text-gray-800 dark:text-gray-200 mt-0.5">{{ $transaksi->nama_pembeli }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-400 uppercase tracking-wide">Nomor HP</p>
                <p class="font-medium text-gray-700 dark:text-gray-300 mt-0.5">{{ $transaksi->nomor_hp ?: '—' }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-400 uppercase tracking-wide">Apoteker</p>
                <p class="font-medium text-gray-700 dark:text-gray-300 mt-0.5">{{ $transaksi->apoteker?->nama ?? '—' }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-400 uppercase tracking-wide">Kasir</p>
                <p class="font-medium text-gray-700 dark:text-gray-300 mt-0.5">{{ $transaksi->kasir?->nama ?? '—' }}</p>
            </div>
            @if($transaksi->catatan)
            <div class="col-span-2 md:col-span-4">
                <p class="text-xs text-gray-400 uppercase tracking-wide">Catatan</p>
                <p class="text-gray-700 dark:text-gray-300 mt-0.5">{{ $transaksi->catatan }}</p>
            </div>
            @endif
        </div>
    </div>

    {{-- Item List --}}
    <div class="card">
        <div class="card-header">
            <h3 class="text-sm font-semibold dark:text-white">Daftar Obat</h3>
            <p class="text-sm font-bold text-gray-700 dark:text-gray-300">
                Total: Rp {{ number_format($transaksi->total_harga, 0, ',', '.') }}
            </p>
        </div>
        <div class="card-body p-0">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Obat / Alkes</th>
                        <th class="text-center">Jumlah</th>
                        <th class="text-right">Harga Satuan</th>
                        <th class="text-right">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($transaksi->items as $i => $item)
                    <tr wire:key="detail-item-{{ $item->id }}">
                        <td class="text-gray-400 text-sm">{{ $i + 1 }}</td>
                        <td>
                            <p class="font-medium text-gray-900 dark:text-gray-100 text-sm">{{ $item->barang->nama }}</p>
                            <p class="text-xs text-gray-400">{{ $item->barang->kode }} · {{ $item->barang->satuan }}</p>
                        </td>
                        <td class="text-center text-sm">{{ $item->jumlah }} {{ $item->barang->satuan }}</td>
                        <td class="text-right text-sm text-gray-600 dark:text-gray-400">
                            Rp {{ number_format($item->harga_satuan, 0, ',', '.') }}
                        </td>
                        <td class="text-right font-medium text-sm">
                            Rp {{ number_format($item->subtotal, 0, ',', '.') }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4" class="text-right font-semibold text-gray-700 dark:text-gray-300 py-3 px-4">Total</td>
                        <td class="text-right font-bold text-lg text-gray-900 dark:text-white py-3 px-4">
                            Rp {{ number_format($transaksi->total_harga, 0, ',', '.') }}
                        </td>
                    </tr>
                    @if($transaksi->status === 'dibayar' || $transaksi->status === 'selesai')
                    <tr class="bg-gray-50 dark:bg-gray-800/30">
                        <td colspan="4" class="text-right text-sm text-gray-500 py-2 px-4">
                            Dibayar ({{ $transaksi->metode_bayar }})
                        </td>
                        <td class="text-right text-sm font-medium text-gray-700 dark:text-gray-300 py-2 px-4">
                            Rp {{ number_format($transaksi->total_bayar, 0, ',', '.') }}
                        </td>
                    </tr>
                    @if($transaksi->kembalian !== null)
                    <tr class="bg-gray-50 dark:bg-gray-800/30">
                        <td colspan="4" class="text-right text-sm text-gray-500 py-2 px-4">Kembalian</td>
                        <td class="text-right text-sm font-medium text-emerald-600 py-2 px-4">
                            Rp {{ number_format($transaksi->kembalian, 0, ',', '.') }}
                        </td>
                    </tr>
                    @endif
                    @endif
                </tfoot>
            </table>
        </div>
    </div>

    {{-- Timeline --}}
    <div class="card">
        <div class="card-header">
            <h3 class="text-sm font-semibold dark:text-white">Timeline Status</h3>
        </div>
        <div class="card-body">
            <div class="flex items-center gap-2 text-sm flex-wrap">
                @php
                $steps = [
                    ['key' => 'draft',          'label' => 'Draft'],
                    ['key' => 'menunggu_kasir', 'label' => 'Menunggu Kasir'],
                    ['key' => 'dibayar',        'label' => 'Dibayar'],
                    ['key' => 'selesai',        'label' => 'Selesai'],
                ];
                $order = ['draft' => 0, 'menunggu_kasir' => 1, 'dibayar' => 2, 'selesai' => 3];
                $current = $order[$transaksi->status] ?? -1;
                @endphp
                @if($transaksi->status === 'dibatalkan')
                <span class="inline-flex items-center px-3 py-1.5 rounded-full text-sm font-medium bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300">
                    Dibatalkan
                </span>
                @else
                @foreach($steps as $idx => $step)
                @php $stepIdx = $order[$step['key']]; @endphp
                <span @class([
                    'inline-flex items-center px-3 py-1.5 rounded-full text-sm font-medium',
                    'bg-[#0a3d62] text-white' => $current === $stepIdx,
                    'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300' => $current > $stepIdx,
                    'bg-gray-100 text-gray-400 dark:bg-gray-700 dark:text-gray-500' => $current < $stepIdx,
                ])>{{ $step['label'] }}</span>
                @if($idx < count($steps) - 1)
                <svg class="w-4 h-4 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
                @endif
                @endforeach
                @endif
            </div>
            @if($transaksi->dibayar_at)
            <p class="text-xs text-gray-400 mt-2">Dibayar: {{ $transaksi->dibayar_at->format('d/m/Y H:i') }}</p>
            @endif
            @if($transaksi->diserahkan_at)
            <p class="text-xs text-gray-400">Diserahkan: {{ $transaksi->diserahkan_at->format('d/m/Y H:i') }}</p>
            @endif
        </div>
    </div>

    {{-- Form Pembayaran (kasir) --}}
    @if($transaksi->status === 'menunggu_kasir')
    <div class="card border-blue-200 dark:border-blue-800">
        <div class="card-header">
            <h3 class="text-sm font-semibold text-blue-700 dark:text-blue-400">Proses Pembayaran</h3>
        </div>
        @if(!$showPayForm)
        <div class="card-body">
            <button wire:click="$set('showPayForm', true)" class="btn-primary">
                Proses Pembayaran
            </button>
        </div>
        @else
        <div class="card-body grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="form-group">
                <label class="form-label">Metode Bayar <span class="text-red-500">*</span></label>
                <select wire:model="metodeBayar" class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200">
                    <option value="tunai">Tunai</option>
                    <option value="transfer">Transfer Bank</option>
                    <option value="kartu">Kartu Debit/Kredit</option>
                    <option value="split">Split Payment</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Jumlah Bayar <span class="text-red-500">*</span></label>
                <input type="number" wire:model="totalBayarInput"
                    class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"
                    placeholder="Nominal yang diterima"
                    min="{{ $transaksi->total_harga }}" step="500" />
                @error('totalBayarInput') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <div class="flex items-end gap-2">
                <button wire:click="prosesBayar" wire:loading.attr="disabled" class="btn-primary flex-1"
                    onclick="return confirm('Konfirmasi pembayaran?')">
                    <span wire:loading.remove wire:target="prosesBayar">Konfirmasi Bayar</span>
                    <span wire:loading wire:target="prosesBayar">Memproses...</span>
                </button>
                <button wire:click="$set('showPayForm', false)" class="btn-secondary">Batal</button>
            </div>
        </div>
        @endif
    </div>
    @endif

    {{-- Aksi Footer --}}
    <div class="flex items-center justify-between">
        <a href="{{ route('farmasi.ritel.index') }}" class="btn-secondary">← Kembali</a>

        <div class="flex gap-2">
            @if($transaksi->bisaDibatalkan())
            <button wire:click="batalkan" wire:loading.attr="disabled" class="btn-danger"
                onclick="return confirm('Batalkan transaksi ini?')">
                <span wire:loading.remove wire:target="batalkan">Batalkan Transaksi</span>
                <span wire:loading wire:target="batalkan">Membatalkan...</span>
            </button>
            @endif

            @if($transaksi->status === 'dibayar')
            <button wire:click="serahkanObat" wire:loading.attr="disabled" class="btn-primary"
                onclick="return confirm('Serahkan obat dan potong stok? Tindakan ini tidak dapat dibatalkan.')">
                <span wire:loading.remove wire:target="serahkanObat">Serahkan Obat & Potong Stok</span>
                <span wire:loading wire:target="serahkanObat">Memproses...</span>
            </button>
            @endif

            @if($transaksi->status === 'draft')
            <a href="{{ route('farmasi.ritel.edit', $transaksi->id) }}" class="btn-primary">Edit Transaksi</a>
            @endif
        </div>
    </div>

</div>

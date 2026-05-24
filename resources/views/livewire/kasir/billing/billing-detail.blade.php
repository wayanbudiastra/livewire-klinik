<div class="space-y-5">
    @if(session('success'))
    <div class="bg-green-50 border border-green-200 rounded-lg p-3 text-sm text-green-700">
        {{ session('success') }}
    </div>
    @endif
    @if(session('error'))
    <div class="bg-red-50 border border-red-200 rounded-lg p-3 text-sm text-red-700">
        {{ session('error') }}
    </div>
    @endif

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-lg font-bold text-gray-900 font-mono">{{ $billing->nomor_invoice }}</h2>
            <p class="text-sm text-gray-500">{{ $billing->created_at->format('d/m/Y H:i') }}</p>
        </div>
        <div class="flex items-center gap-2">
            @if($billing->status === 'lunas')
            <span class="inline-flex px-3 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-700">LUNAS</span>
            @elseif($billing->status === 'dibatalkan')
            <span class="inline-flex px-3 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-700">DIBATALKAN</span>
            @else
            <span class="inline-flex px-3 py-1 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-700">BELUM BAYAR</span>
            @endif

            {{-- Tombol Cetak --}}
            @if($billing->status === 'lunas')
            <a href="{{ route('kasir.billing.cetak', $billing) }}" target="_blank"
                class="px-3 py-1.5 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center gap-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                </svg>
                @if($billing->sudah_cetak)
                    Cetak COPY ({{ $billing->jumlah_cetak }}x)
                @else
                    Cetak ORIGINAL
                @endif
            </a>
            @endif

            {{-- Tombol Batalkan --}}
            @if(in_array($billing->status, ['belum_bayar','lunas']) && auth()->user()->hasAnyRole(['super_admin','admin']))
            <button type="button" wire:click="batalkan"
                class="px-3 py-1.5 text-sm bg-red-600 text-white rounded-lg hover:bg-red-700 flex items-center gap-1">
                Batalkan
            </button>
            @endif
        </div>
    </div>

    {{-- Info Pasien & Kunjungan --}}
    <div class="bg-white rounded-xl shadow-sm p-5 grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
        <div class="space-y-1">
            <p class="text-gray-500">Pasien</p>
            <p class="font-semibold text-gray-900">{{ $billing->kunjungan->pasien->nama }}</p>
            <p class="text-gray-400 font-mono">{{ $billing->kunjungan->pasien->nomor_rm }}</p>
        </div>
        <div class="space-y-1">
            <p class="text-gray-500">Poli / Dokter</p>
            <p class="font-semibold text-gray-900">{{ $billing->kunjungan->poli?->nama ?? '-' }}</p>
            <p class="text-gray-400">{{ $billing->kunjungan->dokter?->nama ?? '-' }}</p>
        </div>
    </div>

    {{-- Item Tagihan --}}
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="px-5 py-3 border-b">
            <h3 class="text-sm font-semibold text-gray-700">Rincian Tagihan</h3>
        </div>
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2 text-left font-medium text-gray-600">Item</th>
                    <th class="px-4 py-2 text-right font-medium text-gray-600">Qty</th>
                    <th class="px-4 py-2 text-right font-medium text-gray-600">Harga</th>
                    <th class="px-4 py-2 text-right font-medium text-gray-600">Subtotal</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($billing->items as $item)
                <tr>
                    <td class="px-4 py-2 text-gray-900">{{ $item->nama_item }}</td>
                    <td class="px-4 py-2 text-right text-gray-600">{{ $item->qty }}</td>
                    <td class="px-4 py-2 text-right text-gray-600">{{ number_format($item->harga_satuan, 0, ',', '.') }}</td>
                    <td class="px-4 py-2 text-right font-medium text-gray-900">{{ number_format($item->subtotal, 0, ',', '.') }}</td>
                </tr>
                @empty
                <tr><td colspan="4" class="px-4 py-4 text-center text-gray-400">Tidak ada item</td></tr>
                @endforelse
            </tbody>
            <tfoot class="bg-blue-50 font-semibold">
                <tr>
                    <td colspan="3" class="px-4 py-3 text-right text-gray-700">Total Tagihan</td>
                    <td class="px-4 py-3 text-right text-gray-900">Rp {{ number_format($billing->total_tagihan, 0, ',', '.') }}</td>
                </tr>
            </tfoot>
        </table>
    </div>

    {{-- Rincian Pembayaran Split --}}
    @if($billing->pembayaranSplit->count() > 0)
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="px-5 py-3 border-b">
            <h3 class="text-sm font-semibold text-gray-700">Rincian Pembayaran</h3>
        </div>
        <div class="p-4 space-y-2">
            @foreach($billing->pembayaranSplit as $split)
            <div class="flex justify-between items-center text-sm">
                <div class="flex items-center gap-2">
                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs bg-blue-100 text-blue-700">
                        {{ $split->metode_label }}
                    </span>
                    @if($split->nama_asuransi)
                        <span class="text-gray-400">({{ $split->nama_asuransi }})</span>
                    @endif
                    @if($split->referensi)
                        <span class="text-gray-400 text-xs">{{ $split->referensi }}</span>
                    @endif
                </div>
                <span class="font-semibold text-gray-900">Rp {{ number_format($split->jumlah, 0, ',', '.') }}</span>
            </div>
            @endforeach
            <div class="border-t pt-2 flex justify-between font-bold text-emerald-600">
                <span>Total Dibayar</span>
                <span>Rp {{ number_format($billing->total_bayar, 0, ',', '.') }}</span>
            </div>
        </div>
    </div>
    @endif

    {{-- Log Cetak --}}
    @if($billing->cetakLogs->count() > 0)
    <div class="bg-white rounded-xl shadow-sm p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-3">Log Cetak Invoice</h3>
        <div class="space-y-1.5">
            @foreach($billing->cetakLogs as $log)
            <div class="flex items-center justify-between text-xs text-gray-500">
                <span>
                    Cetakan ke-{{ $log->nomor_cetak }}
                    <span class="font-semibold {{ $log->jenis === 'original' ? 'text-green-600' : 'text-red-600' }}">
                        ({{ strtoupper($log->jenis) }})
                    </span>
                </span>
                <span>{{ $log->created_at->format('d/m/Y H:i') }}</span>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Tombol Bayar --}}
    @if(!in_array($billing->status, ['lunas','dibatalkan']))
    <div class="flex justify-end">
        <a href="{{ route('kasir.billing.split-payment', $billing) }}"
            class="px-6 py-2.5 bg-primary-600 text-white text-sm font-semibold rounded-lg hover:bg-primary-700">
            Proses Pembayaran
        </a>
    </div>
    @endif

    {{-- Batalkan Modal --}}
    @livewire('kasir.billing.batalkan-billing-modal')
</div>

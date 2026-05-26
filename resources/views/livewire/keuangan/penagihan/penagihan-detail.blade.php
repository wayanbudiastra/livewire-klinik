<div>
    @if(session('success'))
    <div class="alert alert-success mb-4">{{ session('success') }}</div>
    @endif

    <div class="page-header">
        <div>
            <h1 class="page-title">{{ $penagihan->nomor_penagihan }}</h1>
            <p class="page-subtitle">{{ $penagihan->asuransi->nama }} — {{ $penagihan->tanggal_penagihan->format('d M Y') }}</p>
        </div>
        <div class="flex gap-2">
            @php
                $statusClass = match($penagihan->status) {
                    'diajukan'         => 'badge-primary',
                    'dibayar_sebagian' => 'badge-warning',
                    'lunas'            => 'badge-success',
                    'ditutup'          => 'badge-gray',
                    default            => 'badge-gray',
                };
            @endphp
            <span class="badge {{ $statusClass }} text-sm px-3 py-1.5">
                {{ ucfirst(str_replace('_', ' ', $penagihan->status)) }}
            </span>
            <a href="{{ route('keuangan.penagihan.index') }}" class="btn-secondary">Kembali</a>
        </div>
    </div>

    {{-- Summary Cards --}}
    <div class="grid grid-cols-3 gap-4 mb-5">
        <div class="card">
            <div class="card-body">
                <p class="text-xs text-gray-500">Total Tagihan</p>
                <p class="text-xl font-bold text-gray-900 mt-0.5">
                    Rp {{ number_format($penagihan->total_tagihan, 0, ',', '.') }}
                </p>
            </div>
        </div>
        <div class="card">
            <div class="card-body">
                <p class="text-xs text-gray-500">Sudah Dibayar</p>
                <p class="text-xl font-bold text-emerald-600 mt-0.5">
                    Rp {{ number_format($penagihan->total_dibayar, 0, ',', '.') }}
                </p>
            </div>
        </div>
        <div class="card">
            <div class="card-body">
                <p class="text-xs text-gray-500">Sisa Tagihan</p>
                <p class="text-xl font-bold {{ $penagihan->sisa_tagihan > 0 ? 'text-amber-600' : 'text-gray-400' }} mt-0.5">
                    Rp {{ number_format($penagihan->sisa_tagihan, 0, ',', '.') }}
                </p>
            </div>
        </div>
    </div>

    {{-- Item Piutang --}}
    <div class="card mb-5">
        <div class="card-header">
            <h3 class="text-sm font-semibold text-gray-700">Rincian Piutang ({{ $penagihan->items->count() }} item)</h3>
        </div>
        <div class="card-body p-0">
            <table class="table">
                <thead>
                    <tr>
                        <th>No. Piutang</th>
                        <th>Pasien</th>
                        <th class="text-right">Diajukan</th>
                        <th class="text-right">Disetujui</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($penagihan->items as $item)
                    <tr>
                        <td class="font-mono text-xs">{{ $item->piutang->nomor_piutang }}</td>
                        <td class="text-sm font-medium">{{ $item->piutang->pasien->nama }}</td>
                        <td class="text-right text-sm">
                            Rp {{ number_format($item->jumlah_diajukan, 0, ',', '.') }}
                        </td>
                        <td class="text-right text-sm">
                            @if($item->jumlah_disetujui !== null)
                                Rp {{ number_format($item->jumlah_disetujui, 0, ',', '.') }}
                            @else
                                <span class="text-gray-400">-</span>
                            @endif
                        </td>
                        <td>
                            @php
                                $s = $item->piutang->status;
                                $sc = match($s) {
                                    'diajukan'         => 'badge-primary',
                                    'dibayar_sebagian' => 'badge-warning',
                                    'lunas'            => 'badge-success',
                                    'ditolak'          => 'badge-danger',
                                    default            => 'badge-gray',
                                };
                            @endphp
                            <span class="badge {{ $sc }}">{{ $item->piutang->status_label }}</span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Riwayat Pembayaran --}}
    <div class="card mb-5">
        <div class="card-header">
            <h3 class="text-sm font-semibold text-gray-700">Riwayat Pembayaran</h3>
            @if($penagihan->sisa_tagihan > 0 && !in_array($penagihan->status, ['lunas', 'ditutup']))
            @can('piutang.lunas')
            <button type="button" wire:click="$set('showBayarForm', true)" class="btn-primary btn-sm">
                + Catat Pembayaran
            </button>
            @endcan
            @endif
        </div>
        <div class="card-body p-0">
            @if($penagihan->pembayaran->isEmpty())
            <p class="text-center text-gray-400 py-6 text-sm">Belum ada pembayaran.</p>
            @else
            <table class="table">
                <thead>
                    <tr>
                        <th>No. Pembayaran</th>
                        <th>Tanggal</th>
                        <th>Metode</th>
                        <th>No. Referensi</th>
                        <th class="text-right">Jumlah</th>
                        <th>Dicatat oleh</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($penagihan->pembayaran as $bayar)
                    <tr>
                        <td class="font-mono text-xs">{{ $bayar->nomor_pembayaran }}</td>
                        <td class="text-sm">{{ $bayar->tanggal_bayar->format('d/m/Y') }}</td>
                        <td><span class="badge badge-gray text-xs">{{ $bayar->metode_label }}</span></td>
                        <td class="text-sm text-gray-500">{{ $bayar->nomor_referensi ?? '-' }}</td>
                        <td class="text-right font-semibold text-emerald-600">
                            Rp {{ number_format($bayar->jumlah_bayar, 0, ',', '.') }}
                        </td>
                        <td class="text-sm text-gray-500">{{ $bayar->pencatat->name ?? '-' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @endif
        </div>
    </div>

    {{-- Modal Catat Pembayaran --}}
    @if($showBayarForm)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-semibold text-gray-900">Catat Pembayaran</h3>
                <button type="button" wire:click="$set('showBayarForm', false)" class="text-gray-400 hover:text-gray-600">&times;</button>
            </div>
            <div class="space-y-4">
                <div class="p-3 bg-gray-50 rounded-lg text-sm">
                    <span class="text-gray-500">Sisa tagihan:</span>
                    <span class="font-bold text-amber-600 ml-2">
                        Rp {{ number_format($penagihan->sisa_tagihan, 0, ',', '.') }}
                    </span>
                </div>
                <div class="form-group">
                    <label class="form-label">Jumlah Bayar <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 text-sm">Rp</span>
                        <input type="number" wire:model="jumlahBayar" class="form-input pl-9"
                            min="0.01" step="0.01" />
                    </div>
                    @error('jumlahBayar') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div class="form-group">
                    <label class="form-label">Metode</label>
                    <select wire:model="metode" class="form-input">
                        <option value="transfer">Transfer Bank</option>
                        <option value="cek">Cek</option>
                        <option value="giro">Giro</option>
                        <option value="tunai">Tunai</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Tanggal Bayar <span class="text-red-500">*</span></label>
                    <input type="date" wire:model="tanggalBayar" class="form-input" />
                    @error('tanggalBayar') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div class="form-group">
                    <label class="form-label">No. Referensi</label>
                    <input type="text" wire:model="nomorReferensi" class="form-input"
                        placeholder="No. transfer / cek / giro" />
                </div>
            </div>
            <div class="flex justify-end gap-3 mt-5">
                <button type="button" wire:click="$set('showBayarForm', false)" class="btn-secondary">Batal</button>
                <button wire:click="catatBayar" wire:loading.attr="disabled" class="btn-primary">
                    <span wire:loading wire:target="catatBayar">Menyimpan...</span>
                    <span wire:loading.remove wire:target="catatBayar">Catat Pembayaran</span>
                </button>
            </div>
        </div>
    </div>
    @endif
</div>

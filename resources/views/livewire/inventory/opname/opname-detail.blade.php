<div>
    @if(session('success'))
    <div class="alert alert-success mb-4">{{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div class="alert alert-danger mb-4">{{ session('error') }}</div>
    @endif

    {{-- Header Opname --}}
    <div class="page-header">
        <div>
            <h1 class="page-title">{{ $opname->nomor_opname }}</h1>
            <p class="page-subtitle">
                {{ $opname->tanggal_opname->format('d F Y') }}
                @if($opname->keterangan_periode) — {{ $opname->keterangan_periode }} @endif
            </p>
        </div>
        <div class="flex items-center gap-2">
            @php
                $sc = match($opname->status) {
                    'draft'               => 'badge-warning',
                    'menunggu_verifikasi' => 'badge-primary',
                    'selesai'             => 'badge-success',
                    'dibatalkan'          => 'badge-gray',
                    default               => 'badge-gray',
                };
            @endphp
            <span class="badge {{ $sc }} text-sm px-3 py-1.5">{{ $opname->status_label }}</span>
            <a href="{{ route('inventory.opname.index') }}" class="btn-secondary">Kembali</a>
        </div>
    </div>

    {{-- Summary Cards --}}
    <div class="grid grid-cols-2 md:grid-cols-6 gap-3 mb-5">
        <div class="card col-span-2 md:col-span-2">
            <div class="card-body">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gray-500">Progress Pengisian</p>
                        <p class="text-2xl font-bold mt-0.5">{{ $ringkasan['sudah_diisi'] }}/{{ $ringkasan['total_item'] }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-xs text-gray-400">{{ $ringkasan['total_item'] > 0 ? round($ringkasan['sudah_diisi']/$ringkasan['total_item']*100) : 0 }}%</p>
                    </div>
                </div>
                @if($ringkasan['total_item'] > 0)
                <div class="mt-2 h-2 bg-gray-200 rounded-full overflow-hidden">
                    <div class="h-full bg-primary-500 rounded-full transition-all"
                        style="width: {{ round($ringkasan['sudah_diisi']/$ringkasan['total_item']*100) }}%"></div>
                </div>
                @endif
            </div>
        </div>
        <div class="card">
            <div class="card-body text-center">
                <p class="text-xs text-gray-500">Sesuai</p>
                <p class="text-2xl font-bold text-emerald-600 mt-0.5">{{ $ringkasan['sesuai'] }}</p>
            </div>
        </div>
        <div class="card">
            <div class="card-body text-center">
                <p class="text-xs text-gray-500">Lebih</p>
                <p class="text-2xl font-bold text-blue-600 mt-0.5">{{ $ringkasan['lebih'] }}</p>
            </div>
        </div>
        <div class="card">
            <div class="card-body text-center">
                <p class="text-xs text-gray-500">Kurang</p>
                <p class="text-2xl font-bold text-red-600 mt-0.5">{{ $ringkasan['kurang'] }}</p>
            </div>
        </div>
        <div class="card">
            <div class="card-body text-center">
                <p class="text-xs text-gray-500">Nilai Selisih</p>
                <p class="text-lg font-bold text-amber-600 mt-0.5">{{ number_format($ringkasan['nilai_selisih'], 0, ',', '.') }}</p>
            </div>
        </div>
    </div>

    {{-- Action Buttons --}}
    @if($opname->status === 'draft')
    <div class="flex justify-between items-center mb-4">
        <p class="text-sm text-gray-500">Input stok fisik pada kolom "Stok Fisik" di bawah.</p>
        <x-confirm-button action="submitVerifikasi" title="Submit Untuk Verifikasi?"
            text="Pastikan semua item sudah diisi."
            icon="question" type="primary" confirm="Ya, Submit"
            wire:loading.attr="disabled" class="btn-primary">
            <span wire:loading.remove wire:target="submitVerifikasi">Submit Untuk Verifikasi</span>
            <span wire:loading wire:target="submitVerifikasi">Memproses...</span>
        </x-confirm-button>
    </div>
    @endif

    @if($opname->status === 'menunggu_verifikasi')
    @can('obat.approve')
    <div class="flex justify-between items-center mb-4 p-4 bg-primary-50 dark:bg-blue-900/20 rounded-xl">
        <div>
            <p class="font-medium text-primary-700 dark:text-primary-300">Opname menunggu verifikasi</p>
            <p class="text-sm text-gray-500">Periksa selisih lalu verifikasi untuk memperbarui stok sistem.</p>
        </div>
        <x-confirm-button action="verifikasi" title="Verifikasi & Posting Stok?"
            text="Stok sistem akan diperbarui sesuai stok fisik."
            icon="warning" type="danger" confirm="Ya, Verifikasi"
            wire:loading.attr="disabled" class="btn-primary">
            <span wire:loading.remove wire:target="verifikasi">Verifikasi & Posting Stok</span>
            <span wire:loading wire:target="verifikasi">Memproses...</span>
        </x-confirm-button>
    </div>
    @endcan
    @endif

    {{-- Filter & Tabel Item --}}
    <div class="card">
        <div class="card-header flex flex-wrap gap-3">
            <input type="text" wire:model.live.debounce.300ms="search"
                class="form-input w-52" placeholder="Cari nama barang..." />
            <select wire:model.live="filterTampil" class="form-input w-48">
                <option value="semua">Semua Item</option>
                <option value="belum_diisi">Belum Diisi</option>
                <option value="ada_selisih">Ada Selisih</option>
            </select>
        </div>
        <div class="card-body p-0">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Barang</th>
                        <th class="text-right">Stok Sistem</th>
                        <th class="text-right w-32">Stok Fisik</th>
                        <th class="text-right">Selisih</th>
                        <th class="text-right">Nilai Selisih</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($items as $item)
                    <tr @class(['bg-red-50 dark:bg-red-900/10'=>$item->tipe_selisih==='kurang', 'bg-blue-50 dark:bg-blue-900/10'=>$item->tipe_selisih==='lebih'])>
                        <td class="text-gray-400 text-xs">{{ $item->id }}</td>
                        <td>
                            <p class="font-medium text-sm text-gray-900 dark:text-gray-100">{{ $item->barang->nama }}</p>
                            <p class="text-xs text-gray-400">{{ $item->barang->kode }} · {{ $item->barang->satuan }}</p>
                        </td>
                        <td class="text-right text-sm font-medium">{{ number_format($item->stok_sistem, 2) }}</td>
                        <td class="text-right">
                            @if($opname->status === 'draft')
                            <input type="number"
                                wire:change="inputStokFisik({{ $item->id }}, $event.target.value)"
                                value="{{ $item->stok_fisik }}"
                                class="form-input text-sm text-right py-1 w-28 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"
                                min="0" step="0.01"
                                placeholder="0" />
                            @else
                            <span class="text-sm font-medium">{{ $item->stok_fisik !== null ? number_format($item->stok_fisik, 2) : '-' }}</span>
                            @endif
                        </td>
                        <td class="text-right text-sm font-medium">
                            @if($item->selisih !== null)
                            <span @class(['text-emerald-600'=>$item->tipe_selisih==='sesuai', 'text-blue-600'=>$item->tipe_selisih==='lebih', 'text-red-600'=>$item->tipe_selisih==='kurang'])>
                                {{ $item->selisih > 0 ? '+' : '' }}{{ number_format($item->selisih, 2) }}
                            </span>
                            @else
                            <span class="text-gray-300">-</span>
                            @endif
                        </td>
                        <td class="text-right text-sm">
                            @if($item->nilai_selisih > 0)
                            Rp {{ number_format($item->nilai_selisih, 0, ',', '.') }}
                            @else
                            <span class="text-gray-300">-</span>
                            @endif
                        </td>
                        <td class="text-center">
                            @if($item->tipe_selisih === null)
                            <span class="badge badge-gray text-xs">Belum diisi</span>
                            @elseif($item->tipe_selisih === 'sesuai')
                            <span class="badge badge-success text-xs">Sesuai</span>
                            @elseif($item->tipe_selisih === 'lebih')
                            <span class="badge badge-primary text-xs">Lebih</span>
                            @else
                            <span class="badge badge-danger text-xs">Kurang</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($items->hasPages())
        <div class="card-footer">{{ $items->links() }}</div>
        @endif
    </div>

    {{-- Batalkan --}}
    @if(in_array($opname->status, ['draft', 'menunggu_verifikasi']))
    <div class="flex justify-end mt-4">
        <x-confirm-button action="batalkan" title="Batalkan Opname Ini?"
            icon="warning" type="danger" confirm="Ya, Batalkan"
            class="text-sm text-red-500 hover:text-red-700">
            Batalkan Opname
        </x-confirm-button>
    </div>
    @endif
</div>

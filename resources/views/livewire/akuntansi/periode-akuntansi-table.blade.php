<div class="space-y-4">

    <div class="card">
        <div class="card-body p-0">
            <table class="table">
                <thead>
                    <tr>
                        <th>Periode</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Sisa Jurnal Pending</th>
                        <th>Ditutup Oleh</th>
                        <th>Tanggal Tutup</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($this->periodeList as $row)
                    @php $p = $row['periode']; @endphp
                    <tr wire:key="periode-{{ $p->id }}">
                        <td class="text-sm font-medium">
                            {{ $p->label }}
                            @if($row['lewat_tenggat'])
                            <span class="badge badge-warning ml-1" title="Sudah lewat tenggat tanggal 5 bulan berikutnya">
                                ⚠ Lewat tenggat
                            </span>
                            @endif
                        </td>
                        <td class="text-center">
                            <span @class([
                                'badge',
                                'badge-success' => $p->status === 'terbuka',
                                'badge-gray'     => $p->status === 'ditutup',
                            ])>
                                {{ $p->status === 'terbuka' ? 'Terbuka' : 'Ditutup' }}
                            </span>
                        </td>
                        <td class="text-center text-sm">
                            @if($p->status === 'terbuka')
                                {{ $row['sisa_pending'] }}
                                @if($row['sisa_pending'] > 0)
                                <a href="{{ route('akuntansi.jurnal-pending') }}" class="text-xs text-primary-600 hover:underline ml-1">lihat</a>
                                @endif
                            @else
                                -
                            @endif
                        </td>
                        <td class="text-sm text-gray-500">{{ $p->ditutupOleh?->nama ?? '-' }}</td>
                        <td class="text-sm text-gray-500">{{ $p->ditutup_pada?->format('d/m/Y H:i') ?? '-' }}</td>
                        <td class="text-center">
                            @if($p->status === 'terbuka')
                            <x-confirm-button
                                action="tutup({{ $p->tahun }}, {{ $p->bulan }})"
                                title="Tutup Periode {{ $p->label }}?"
                                text="Jurnal baru tidak akan bisa diposting lagi ke bulan ini sampai dibuka kembali."
                                icon="warning" type="danger" confirm="Ya, Tutup"
                                class="btn-xs btn-danger"
                                :disabled="$row['sisa_pending'] > 0">
                                Tutup Periode
                            </x-confirm-button>
                            @else
                            <button wire:click="konfirmasiBukaKembali({{ $p->id }})" class="btn-xs btn-secondary">
                                Buka Kembali
                            </button>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="empty-state py-10"><p class="empty-state-text">Belum ada data periode</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Modal Buka Kembali (SuperAdmin) --}}
    @if($showBukaKembali)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
         x-data @click.self="$wire.set('showBukaKembali', false)">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-md mx-4">
            <div class="flex items-center justify-between p-4 border-b bg-red-50 rounded-t-xl">
                <h3 class="font-semibold text-red-700">Buka Kembali Periode</h3>
                <button wire:click="$set('showBukaKembali', false)" class="text-gray-400">&times;</button>
            </div>
            <div class="p-4 space-y-4">
                @if($errorMsg)
                <div class="bg-red-50 border border-red-200 rounded-lg p-3 text-sm text-red-700">{{ $errorMsg }}</div>
                @endif

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Alasan <span class="text-red-500">*</span></label>
                    <textarea wire:model="alasanBukaKembali" rows="2"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm @error('alasanBukaKembali') border-red-400 @enderror"
                        placeholder="Minimal 10 karakter"></textarea>
                    @error('alasanBukaKembali') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div x-data="{ show: false }">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Password SuperAdmin <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <input :type="show ? 'text' : 'password'"
                            wire:model="passwordBukaKembali"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 pr-10 text-sm @error('passwordBukaKembali') border-red-400 @enderror"
                            placeholder="Password SuperAdmin" />
                        <button type="button" @click="show = !show"
                            class="absolute inset-y-0 right-3 flex items-center text-gray-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                        </button>
                    </div>
                    @error('passwordBukaKembali') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
            <div class="flex justify-end gap-2 p-4 border-t">
                <button wire:click="$set('showBukaKembali', false)"
                    class="px-4 py-2 text-sm border border-gray-300 rounded-lg hover:bg-gray-50">Batal</button>
                <button wire:click="bukaKembali" wire:loading.attr="disabled"
                    class="px-4 py-2 text-sm bg-red-600 text-white rounded-lg hover:bg-red-700">
                    <span wire:loading.remove>Buka Kembali</span>
                    <span wire:loading>Memproses...</span>
                </button>
            </div>
        </div>
    </div>
    @endif

</div>

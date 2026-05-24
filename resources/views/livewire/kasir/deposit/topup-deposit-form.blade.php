<div class="space-y-6">
    @if(session('success'))
    <div class="bg-green-50 border border-green-200 rounded-lg p-3 text-sm text-green-700">
        {{ session('success') }}
    </div>
    @endif

    {{-- Search Pasien --}}
    <div class="bg-white rounded-xl shadow-sm p-6">
        <h3 class="text-sm font-semibold text-gray-700 mb-4">Cari Pasien</h3>

        @if($pasienDipilih)
        <div class="flex items-center justify-between bg-blue-50 border border-blue-200 rounded-lg p-3">
            <div>
                <p class="font-semibold text-blue-900">{{ $pasienDipilih['nama'] }}</p>
                <p class="text-xs text-blue-600">No. RM: {{ $pasienDipilih['nomor_rm'] }}</p>
            </div>
            <button type="button" wire:click="$set('pasienDipilih', null); $set('pasienId', null); $set('depositInfo', null)"
                class="text-xs text-red-500 hover:text-red-700">Ganti</button>
        </div>

        @if($depositInfo)
        <div class="mt-3 grid grid-cols-3 gap-3 text-center">
            <div class="bg-gray-50 rounded-lg p-3">
                <p class="text-xs text-gray-500">Saldo Deposit</p>
                <p class="font-bold text-gray-900 text-lg">Rp {{ number_format($depositInfo['saldo'], 0, ',', '.') }}</p>
            </div>
            <div class="bg-gray-50 rounded-lg p-3">
                <p class="text-xs text-gray-500">Total Top-up</p>
                <p class="font-semibold text-emerald-600">Rp {{ number_format($depositInfo['total_topup'], 0, ',', '.') }}</p>
            </div>
            <div class="bg-gray-50 rounded-lg p-3">
                <p class="text-xs text-gray-500">Total Terpakai</p>
                <p class="font-semibold text-red-500">Rp {{ number_format($depositInfo['total_terpakai'], 0, ',', '.') }}</p>
            </div>
        </div>
        @endif

        @else
        <div class="relative">
            <input type="text" wire:model.live.debounce.300ms="searchPasien"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 pl-9 text-sm focus:ring-2 focus:ring-primary-500"
                placeholder="Cari nama, No. RM, atau telepon..." />
            <svg class="w-4 h-4 absolute left-3 top-2.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
        </div>

        @if(!empty($hasilSearch))
        <div class="mt-2 border border-gray-200 rounded-lg divide-y overflow-hidden">
            @foreach($hasilSearch as $p)
            <button type="button" wire:click="pilihPasien({{ $p['id'] }})"
                class="w-full text-left px-3 py-2 hover:bg-gray-50 flex items-center justify-between text-sm">
                <div>
                    <span class="font-medium text-gray-900">{{ $p['nama'] }}</span>
                    <span class="text-gray-400 ml-2">{{ $p['nomor_rm'] }}</span>
                </div>
                <span class="text-xs text-blue-600">Saldo: Rp {{ number_format($p['saldo'], 0, ',', '.') }}</span>
            </button>
            @endforeach
        </div>
        @endif
        @error('pasienId') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        @endif
    </div>

    {{-- Form Top-up --}}
    @if($pasienDipilih)
    <div class="bg-white rounded-xl shadow-sm p-6">
        <h3 class="text-sm font-semibold text-gray-700 mb-4">Top-up Deposit</h3>
        <div class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Jumlah Top-up (Rp) <span class="text-red-500">*</span>
                </label>
                <input type="number" wire:model="jumlah"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 @error('jumlah') border-red-400 @enderror"
                    placeholder="Min. 1.000" min="1000" step="1000" />
                @error('jumlah') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Keterangan</label>
                <input type="text" wire:model="keterangan"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
                    placeholder="Opsional" maxlength="200" />
                @error('keterangan') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="flex justify-end">
                <button type="button" wire:click="simpan" wire:loading.attr="disabled"
                    class="px-6 py-2 bg-primary-600 text-white text-sm font-medium rounded-lg hover:bg-primary-700 transition">
                    <span wire:loading.remove>Proses Top-up</span>
                    <span wire:loading>Memproses...</span>
                </button>
            </div>
        </div>
    </div>
    @endif
</div>

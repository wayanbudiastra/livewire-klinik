<div>
    <div class="page-header">
        <div>
            <h1 class="page-title">Buat Penagihan Asuransi</h1>
            <p class="page-subtitle">Pilih piutang yang akan ditagihkan ke asuransi</p>
        </div>
        <a href="{{ route('keuangan.penagihan.index') }}" class="btn-secondary">Kembali</a>
    </div>

    <div class="space-y-5">
        {{-- Pilih Asuransi --}}
        <div class="card">
            <div class="card-header">
                <h3 class="text-sm font-semibold text-gray-700">Asuransi Tujuan</h3>
            </div>
            <div class="card-body">
                <div class="form-group max-w-sm">
                    <label class="form-label">Pilih Asuransi <span class="text-red-500">*</span></label>
                    <select wire:model.live="asuransiId" class="form-input">
                        <option value="0">-- Pilih Asuransi --</option>
                        @foreach($opsiAsuransi as $a)
                        <option value="{{ $a->id }}">{{ $a->nama }}</option>
                        @endforeach
                    </select>
                    @error('asuransiId') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        {{-- Daftar Piutang --}}
        @if($asuransiId)
        <div class="card">
            <div class="card-header">
                <h3 class="text-sm font-semibold text-gray-700">
                    Piutang Tertagih
                    @if($piutangTertagih->isNotEmpty())
                        <span class="text-gray-400 font-normal">({{ $piutangTertagih->count() }} item)</span>
                    @endif
                </h3>
                @if($piutangTertagih->isNotEmpty())
                <div class="flex gap-2">
                    <button type="button" wire:click="pilihSemua" class="btn-secondary btn-sm">Pilih Semua</button>
                    <button type="button" wire:click="batalPilih" class="btn-secondary btn-sm">Batal Pilih</button>
                </div>
                @endif
            </div>
            <div class="card-body p-0">
                @if($piutangTertagih->isEmpty())
                <p class="text-center text-gray-400 py-8 text-sm">Tidak ada piutang tertagih untuk asuransi ini.</p>
                @else
                <table class="table">
                    <thead>
                        <tr>
                            <th class="w-10"></th>
                            <th>No. Piutang</th>
                            <th>Pasien</th>
                            <th>Tgl. Piutang</th>
                            <th>Jatuh Tempo</th>
                            <th class="text-right">Sisa Piutang</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($piutangTertagih as $p)
                        <tr class="{{ in_array($p->id, $piutangIds) ? 'bg-primary-50' : '' }} cursor-pointer"
                            wire:click="togglePilih({{ $p->id }})">
                            <td>
                                <input type="checkbox"
                                    value="{{ $p->id }}"
                                    @checked(in_array($p->id, $piutangIds))
                                    class="form-checkbox"
                                    readonly />
                            </td>
                            <td class="font-mono text-xs">{{ $p->nomor_piutang }}</td>
                            <td class="text-sm font-medium">{{ $p->pasien->nama }}</td>
                            <td class="text-sm text-gray-500">{{ $p->tanggal_piutang->format('d/m/Y') }}</td>
                            <td class="text-sm {{ $p->is_jatuh_tempo ? 'text-red-500 font-semibold' : 'text-gray-500' }}">
                                {{ $p->tanggal_jatuh_tempo?->format('d/m/Y') ?? '-' }}
                            </td>
                            <td class="text-right font-semibold">
                                Rp {{ number_format($p->sisa_piutang, 0, ',', '.') }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @endif
            </div>
        </div>
        @endif

        {{-- Summary + Submit --}}
        @if(!empty($piutangIds))
        <div class="card border-primary-200 bg-primary-50">
            <div class="card-body">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600">{{ count($piutangIds) }} piutang dipilih</p>
                        <p class="text-xl font-bold text-primary-700 mt-0.5">
                            Total: Rp {{ number_format($totalDipilih, 0, ',', '.') }}
                        </p>
                    </div>
                    <button wire:click="buat" wire:loading.attr="disabled" class="btn-primary">
                        <span wire:loading wire:target="buat">Memproses...</span>
                        <span wire:loading.remove wire:target="buat">Buat Penagihan</span>
                    </button>
                </div>
                @error('piutangIds') <p class="form-error mt-2">{{ $message }}</p> @enderror
            </div>
        </div>
        @endif
    </div>
</div>

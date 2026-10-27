<div class="space-y-4">
    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @error('simpan')
        <div class="alert alert-danger">{{ $message }}</div>
    @enderror

    {{-- 1. Pasien --}}
    <div class="card">
        <div class="card-header"><h3 class="card-title">1. Pasien</h3></div>
        <div class="card-body">
            @if ($this->pasien)
                <div class="flex items-center justify-between text-sm">
                    <div><span class="font-medium">{{ $this->pasien->nama }}</span>
                        <span class="text-gray-400">· {{ $this->pasien->nomor_rm }}</span></div>
                    <button type="button" wire:click="gantiPasien" class="btn btn-secondary btn-sm">Ganti Pasien</button>
                </div>
            @else
                <input type="text" wire:model.live.debounce.300ms="cariPasien" class="form-input w-full"
                       placeholder="Cari pasien (nama / No. RM) — hanya pasien ber-ID">
                @foreach ($this->hasilPasien as $p)
                    <button type="button" wire:click="pilihPasien({{ $p->id }})"
                            class="block w-full text-left text-sm px-2 py-1.5 hover:bg-gray-50 dark:hover:bg-gray-800">
                        {{ $p->nama }} <span class="text-gray-400">· {{ $p->nomor_rm }}</span>
                    </button>
                @endforeach
            @endif
        </div>
    </div>

    @if ($this->pasien)
        {{-- 2. Registrasi utama --}}
        <div class="card">
            <div class="card-header"><h3 class="card-title">2. Registrasi Utama (bill lunas, maks {{ config('emr.susulan_maks_hari') }} hari)</h3></div>
            <div class="card-body">
                @forelse ($this->kunjunganLayak as $k)
                    <label class="flex items-center gap-2 text-sm py-1">
                        <input type="radio" wire:model.live="kunjunganId" value="{{ $k->id }}">
                        {{ $k->tanggal->format('d/m/Y') }} · {{ $k->poli?->nama }} · {{ $k->dokter?->user?->nama }}
                    </label>
                @empty
                    <p class="text-sm text-gray-500">Tidak ada registrasi dengan bill lunas dalam {{ config('emr.susulan_maks_hari') }} hari terakhir. Susulan tidak dapat dibuat tanpa registrasi utama.</p>
                @endforelse
            </div>
        </div>

        @if ($kunjunganId)
            {{-- 3. Order --}}
            <div class="card">
                <div class="card-header"><h3 class="card-title">3. Order Susulan</h3></div>
                <div class="card-body space-y-3">
                    <div class="flex flex-wrap gap-4 text-sm">
                        <label class="flex items-center gap-1"><input type="radio" wire:model.live="jenis" value="lab"> Laboratorium</label>
                        <label class="flex items-center gap-1"><input type="radio" wire:model.live="jenis" value="obat"> Obat</label>
                        <span class="text-xs text-gray-400">Lab dan obat dibuat sebagai transaksi terpisah.</span>
                    </div>

                    <div>
                        <label class="form-label">Dokter penyaran <span class="text-red-500">*</span></label>
                        <select wire:model="dokterId" class="form-select w-full">
                            <option value="">— pilih dokter —</option>
                            @foreach ($this->daftarDokter as $d)
                                <option value="{{ $d->id }}">{{ $d->user?->nama }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <input type="text" wire:model.live.debounce.300ms="cariItem" class="form-input w-full"
                               placeholder="{{ $jenis === 'lab' ? 'Cari pemeriksaan lab…' : 'Cari obat…' }}">
                        @foreach ($this->hasilItem as $it)
                            <button type="button"
                                    wire:click="tambahItem({{ $it['id'] }}, @js($it['nama']), {{ $it['harga'] }})"
                                    class="flex w-full justify-between text-left text-sm px-2 py-1.5 hover:bg-gray-50 dark:hover:bg-gray-800">
                                <span>{{ $it['nama'] }}</span>
                                <span class="font-mono text-gray-500">Rp {{ number_format($it['harga'], 0, ',', '.') }}</span>
                            </button>
                        @endforeach
                    </div>

                    @if ($keranjang)
                        <table class="table w-full text-sm">
                            <tbody>
                            @foreach ($keranjang as $i => $row)
                                <tr>
                                    <td>{{ $row['nama'] }}</td>
                                    <td class="w-24">
                                        @if ($jenis === 'obat')
                                            <input type="number" min="1" wire:model.live="keranjang.{{ $i }}.jumlah" class="form-input w-20">
                                        @endif
                                    </td>
                                    <td class="text-right font-mono">Rp {{ number_format($row['harga'] * ($jenis === 'lab' ? 1 : max(1, (int) $row['jumlah'])), 0, ',', '.') }}</td>
                                    <td class="w-8"><button type="button" wire:click="hapusItem({{ $i }})" class="text-red-500">×</button></td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                        <div class="flex items-center justify-between">
                            <span class="font-semibold">Total: Rp {{ number_format($this->total, 0, ',', '.') }}</span>
                            <button type="button" wire:click="simpan" class="btn btn-primary">Buat Susulan</button>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        {{-- 4. Pembayaran --}}
        @if ($this->menungguBayar->isNotEmpty())
            <div class="card">
                <div class="card-header"><h3 class="card-title">Menunggu Pembayaran</h3></div>
                <div class="card-body space-y-3">
                    @foreach ($this->menungguBayar as $tr)
                        <div class="border rounded p-3 text-sm">
                            <div class="flex justify-between">
                                <span class="font-medium">{{ $tr->nomor_ritel }} · {{ strtoupper($tr->jenis_susulan) }}</span>
                                <span class="font-mono">Rp {{ number_format($tr->total_harga, 0, ',', '.') }}</span>
                            </div>
                            <div class="text-gray-500">Dokter: {{ $tr->dokter?->user?->nama }} ·
                                {{ $tr->items->map->nama_item->implode(', ') }}</div>
                            @if ($bayarId === $tr->id)
                                <div class="flex flex-wrap items-end gap-2 mt-2">
                                    <select wire:model="metodeBayar" class="form-select">
                                        <option value="tunai">Tunai</option><option value="transfer">Transfer</option><option value="kartu">Kartu</option>
                                    </select>
                                    <input type="number" wire:model="totalBayarInput" class="form-input w-40">
                                    <button type="button" wire:click="bayar" class="btn btn-primary btn-sm">Bayar</button>
                                </div>
                                @error('totalBayarInput')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                            @else
                                <div class="flex gap-2 mt-2">
                                    <button type="button" wire:click="bukaBayar({{ $tr->id }})" class="btn btn-primary btn-sm">Bayar</button>
                                    <button type="button" wire:click="batalkan({{ $tr->id }})" class="btn btn-secondary btn-sm">Batalkan</button>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    @endif
</div>

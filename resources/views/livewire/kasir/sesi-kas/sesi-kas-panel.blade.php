<div>
    {{-- Status Kas --}}
    @if($sesiAktif)
    <div class="bg-white rounded-xl border-l-4 border-emerald-500 shadow-sm p-4 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div class="w-3 h-3 rounded-full bg-emerald-500 animate-pulse"></div>
            <div>
                <p class="font-semibold text-gray-900">Kas Sedang Buka</p>
                <p class="text-xs text-gray-500">
                    Dibuka: {{ $sesiAktif->dibuka_pada->format('H:i') }} &middot;
                    Saldo awal: Rp {{ number_format($sesiAktif->saldo_awal, 0, ',', '.') }}
                </p>
            </div>
        </div>
        <button type="button" wire:click="$set('showTutup', true)"
            class="px-3 py-1.5 text-sm font-medium bg-amber-100 text-amber-700 rounded-lg hover:bg-amber-200 transition">
            Tutup Kas
        </button>
    </div>

    {{-- ══════════════════════════════════════════════════════════════
         MODAL TUTUP KAS — 3 Step
    ══════════════════════════════════════════════════════════════ --}}
    @if($showTutup)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
         x-data="{
             step: 1,
             uangFisik: @entangle('uangFisikAkhir'),
             saldoAwal: {{ (float) $sesiAktif->saldo_awal }},
             totalCash: {{ $this->rekapSesi['total_cash'] }},
             get totalSeharusnya() { return this.saldoAwal + this.totalCash; },
             get selisih() { return parseFloat(this.uangFisik || 0) - this.totalSeharusnya; },
             get selisihLabel() {
                 if (this.selisih > 100) return 'Lebih';
                 if (this.selisih < -100) return 'Kurang';
                 return 'Sesuai';
             },
             fmt(n) { return new Intl.NumberFormat('id-ID').format(Math.round(n)); }
         }"
         @click.self="$wire.set('showTutup', false); step = 1">

        <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl mx-4">

            {{-- Header --}}
            <div class="flex items-center justify-between px-5 py-4 border-b">
                <div class="flex items-center gap-4">
                    <h3 class="font-semibold text-gray-900">Tutup Kas</h3>
                    {{-- Step indicator --}}
                    <div class="flex items-center gap-1 text-xs">
                        <template x-for="n in [1,2,3]" :key="n">
                            <div class="flex items-center gap-1">
                                <span class="w-6 h-6 rounded-full flex items-center justify-center font-semibold transition-colors"
                                    :class="step >= n ? 'bg-primary-600 text-white' : 'bg-gray-200 text-gray-500'"
                                    x-text="n"></span>
                                <span x-show="n < 3" class="w-6 h-0.5"
                                    :class="step > n ? 'bg-primary-400' : 'bg-gray-200'"></span>
                            </div>
                        </template>
                    </div>
                </div>
                <button @click="$wire.set('showTutup', false); step = 1" class="text-gray-400 hover:text-gray-600 text-lg">&times;</button>
            </div>

            {{-- ── Step 1: Rekap Transaksi ─────────────────────────────────── --}}
            <div x-show="step === 1" x-cloak class="p-5 space-y-4">
                <p class="text-sm font-medium text-gray-700">Rekap transaksi pada shift ini:</p>

                <div class="overflow-hidden rounded-lg border border-gray-200">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-xs font-semibold uppercase text-gray-500">
                            <tr>
                                <th class="px-4 py-2.5 text-left">Metode Pembayaran</th>
                                <th class="px-4 py-2.5 text-right">Jml Transaksi</th>
                                <th class="px-4 py-2.5 text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($this->rekapSesi['per_metode'] as $row)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2.5 font-medium text-gray-800">{{ $row['label'] }}</td>
                                <td class="px-4 py-2.5 text-right text-gray-600">{{ $row['jumlah_trx'] }}</td>
                                <td class="px-4 py-2.5 text-right font-semibold text-gray-900">
                                    Rp {{ number_format($row['total'], 0, ',', '.') }}
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="3" class="px-4 py-6 text-center text-gray-400">
                                    Belum ada transaksi pada shift ini
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                        <tfoot class="bg-gray-50 border-t border-gray-200">
                            <tr>
                                <td class="px-4 py-2.5 font-bold text-gray-900" colspan="2">Total Semua Transaksi</td>
                                <td class="px-4 py-2.5 text-right font-bold text-gray-900">
                                    Rp {{ number_format($this->rekapSesi['total_semua'], 0, ',', '.') }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                @if($this->rekapSesi['total_pembatalan'] > 0)
                <div class="flex items-center gap-2 rounded-lg bg-red-50 border border-red-200 px-3 py-2 text-sm text-red-700">
                    <svg class="size-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                    </svg>
                    <span>
                        Terdapat <strong>{{ $this->rekapSesi['total_pembatalan'] }} tagihan dibatalkan</strong> pada shift ini.
                    </span>
                </div>
                @endif

                <div class="flex justify-end pt-1">
                    <button @click="step = 2"
                        class="flex items-center gap-1.5 rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700">
                        Lanjut: Validasi Uang Fisik
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </button>
                </div>
            </div>

            {{-- ── Step 2: Validasi Uang Fisik ─────────────────────────────── --}}
            <div x-show="step === 2" x-cloak class="p-5 space-y-4">
                <p class="text-sm font-medium text-gray-700">Hitung uang fisik di laci kas dan masukkan jumlahnya:</p>

                <div class="grid grid-cols-2 gap-3">
                    <div class="rounded-lg bg-gray-50 border border-gray-200 p-3">
                        <p class="text-xs text-gray-500 mb-0.5">Saldo Awal Kas</p>
                        <p class="font-semibold text-gray-900">Rp {{ number_format($sesiAktif->saldo_awal, 0, ',', '.') }}</p>
                    </div>
                    <div class="rounded-lg bg-blue-50 border border-blue-200 p-3">
                        <p class="text-xs text-blue-600 mb-0.5">Total Tunai Masuk</p>
                        <p class="font-semibold text-blue-900">Rp {{ number_format($this->rekapSesi['total_cash'], 0, ',', '.') }}</p>
                    </div>
                </div>

                <div class="flex items-center justify-between rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-3">
                    <p class="text-sm font-medium text-emerald-700">Total yang Seharusnya Ada di Laci</p>
                    <p class="text-lg font-bold text-emerald-900">
                        Rp <span x-text="fmt(totalSeharusnya)"></span>
                    </p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Jumlah Uang Fisik Dihitung (Rp) <span class="text-red-500">*</span>
                    </label>
                    <input type="number"
                        x-model="uangFisik"
                        wire:model="uangFisikAkhir"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent @error('uangFisikAkhir') border-red-400 @enderror"
                        placeholder="Hitung dan masukkan total uang di laci" min="0" step="1000" />
                    @error('uangFisikAkhir') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Selisih real-time --}}
                <div x-show="uangFisik !== '' && uangFisik !== null"
                     class="flex items-center justify-between rounded-lg px-4 py-3 transition-colors"
                     :class="{
                         'bg-emerald-50 border border-emerald-200': Math.abs(selisih) <= 100,
                         'bg-blue-50 border border-blue-200': selisih > 100,
                         'bg-red-50 border border-red-200': selisih < -100
                     }">
                    <div>
                        <p class="text-sm font-semibold"
                           :class="{
                               'text-emerald-700': Math.abs(selisih) <= 100,
                               'text-blue-700': selisih > 100,
                               'text-red-700': selisih < -100
                           }">
                            Selisih &mdash; <span x-text="selisihLabel"></span>
                        </p>
                        <p class="text-xs mt-0.5"
                           :class="{
                               'text-emerald-600': Math.abs(selisih) <= 100,
                               'text-blue-600': selisih > 100,
                               'text-red-600': selisih < -100
                           }">
                            <span x-text="selisih < 0 ? 'Uang fisik kurang dari sistem' : (selisih > 100 ? 'Uang fisik lebih dari sistem' : 'Uang fisik sesuai sistem')"></span>
                        </p>
                    </div>
                    <p class="text-xl font-bold"
                       :class="{
                           'text-emerald-800': Math.abs(selisih) <= 100,
                           'text-blue-800': selisih > 100,
                           'text-red-800': selisih < -100
                       }">
                        <span x-text="selisih >= 0 ? '' : '-'"></span>Rp <span x-text="fmt(Math.abs(selisih))"></span>
                    </p>
                </div>

                <div class="flex justify-between pt-1">
                    <button @click="step = 1"
                        class="flex items-center gap-1.5 rounded-lg border border-gray-300 px-4 py-2 text-sm hover:bg-gray-50">
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                        Kembali
                    </button>
                    <button @click="if(uangFisik !== '' && uangFisik !== null) step = 3"
                        :disabled="uangFisik === '' || uangFisik === null"
                        class="flex items-center gap-1.5 rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-40 disabled:cursor-not-allowed">
                        Lanjut: Konfirmasi
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </button>
                </div>
            </div>

            {{-- ── Step 3: Konfirmasi & Tutup ──────────────────────────────── --}}
            <div x-show="step === 3" x-cloak class="p-5 space-y-4">
                <p class="text-sm font-medium text-gray-700">Ringkasan sebelum menutup kas:</p>

                <div class="grid grid-cols-3 gap-3">
                    <div class="rounded-lg bg-gray-50 border border-gray-200 p-3 text-center">
                        <p class="text-xs text-gray-500 mb-1">Total Transaksi</p>
                        <p class="font-bold text-gray-900 text-sm">
                            Rp {{ number_format($this->rekapSesi['total_semua'], 0, ',', '.') }}
                        </p>
                    </div>
                    <div class="rounded-lg bg-blue-50 border border-blue-200 p-3 text-center">
                        <p class="text-xs text-blue-600 mb-1">Uang Fisik</p>
                        <p class="font-bold text-blue-900 text-sm">
                            Rp <span x-text="fmt(parseFloat(uangFisik || 0))"></span>
                        </p>
                    </div>
                    <div class="rounded-lg p-3 text-center border"
                         :class="{
                             'bg-emerald-50 border-emerald-200': Math.abs(selisih) <= 100,
                             'bg-blue-50 border-blue-200': selisih > 100,
                             'bg-red-50 border-red-200': selisih < -100
                         }">
                        <p class="text-xs mb-1"
                           :class="{
                               'text-emerald-600': Math.abs(selisih) <= 100,
                               'text-blue-600': selisih > 100,
                               'text-red-600': selisih < -100
                           }">Selisih</p>
                        <p class="font-bold text-sm"
                           :class="{
                               'text-emerald-800': Math.abs(selisih) <= 100,
                               'text-blue-800': selisih > 100,
                               'text-red-800': selisih < -100
                           }">
                            <span x-text="selisih >= 0 ? '' : '-'"></span>Rp <span x-text="fmt(Math.abs(selisih))"></span>
                        </p>
                    </div>
                </div>

                <div class="rounded-lg bg-amber-50 border border-amber-200 px-3 py-2.5 text-xs text-amber-800">
                    <strong>Perhatian:</strong> Setelah kas ditutup, transaksi baru tidak dapat diproses dan
                    pembatalan tagihan tidak dapat dilakukan. Pembukaan kembali memerlukan otorisasi SuperAdmin.
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Catatan Penutupan (opsional)</label>
                    <textarea wire:model="catatanTutup" rows="2"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500"
                        placeholder="Catatan tambahan (opsional)"></textarea>
                    @error('catatanTutup') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="flex justify-between pt-1">
                    <button @click="step = 2"
                        class="flex items-center gap-1.5 rounded-lg border border-gray-300 px-4 py-2 text-sm hover:bg-gray-50">
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                        Kembali
                    </button>
                    <button wire:click="tutupKas" wire:loading.attr="disabled"
                        class="flex items-center gap-1.5 rounded-lg bg-amber-500 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-600 disabled:opacity-50">
                        <span wire:loading.remove wire:target="tutupKas">
                            <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </span>
                        <span wire:loading.remove wire:target="tutupKas">Tutup Kas Sekarang</span>
                        <span wire:loading wire:target="tutupKas">Menutup...</span>
                    </button>
                </div>
            </div>

        </div>
    </div>
    @endif

    @else
    <div class="bg-white rounded-xl border-l-4 border-gray-300 shadow-sm p-4 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div class="w-3 h-3 rounded-full bg-gray-400"></div>
            <div>
                <p class="font-semibold text-gray-700">Kas Belum Dibuka</p>
                <p class="text-xs text-gray-400">{{ now()->format('d/m/Y') }}</p>
            </div>
        </div>
        <button type="button" wire:click="$set('showBuka', true)"
            class="px-3 py-1.5 text-sm font-medium bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition">
            Buka Kas
        </button>
    </div>

    {{-- Modal Buka Kas --}}
    @if($showBuka)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
         x-data @click.self="$wire.set('showBuka', false)">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-sm mx-4">
            <div class="flex items-center justify-between p-4 border-b">
                <h3 class="font-semibold text-gray-900">Buka Kas &mdash; {{ now()->format('d/m/Y') }}</h3>
                <button wire:click="$set('showBuka', false)" class="text-gray-400 hover:text-gray-600">&times;</button>
            </div>
            <div class="p-4 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Saldo Awal Kas (Rp) <span class="text-red-500">*</span>
                    </label>
                    <input type="number" wire:model="saldoAwal"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 @error('saldoAwal') border-red-400 @enderror"
                        placeholder="0" min="0" />
                    @error('saldoAwal') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Catatan</label>
                    <input type="text" wire:model="catatan"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
                        placeholder="Opsional" />
                </div>
            </div>
            <div class="flex justify-end gap-2 p-4 border-t">
                <button wire:click="$set('showBuka', false)"
                    class="px-4 py-2 text-sm border border-gray-300 rounded-lg hover:bg-gray-50">Batal</button>
                <button wire:click="bukaKas" wire:loading.attr="disabled"
                    class="px-4 py-2 text-sm bg-primary-600 text-white rounded-lg hover:bg-primary-700">
                    <span wire:loading.remove>Buka Kas</span>
                    <span wire:loading>Membuka...</span>
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- Buka Kembali (SuperAdmin) --}}
    @if($sesiTutupHariIni->count() > 0)
    <div class="mt-2">
        <button type="button" wire:click="$set('showBukaKembali', true)"
            class="text-sm text-primary-600 hover:underline">
            Buka Kembali Kas yang Sudah Tutup (SuperAdmin)
        </button>
    </div>

    @if($showBukaKembali)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
         x-data @click.self="$wire.set('showBukaKembali', false)">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-md mx-4">
            <div class="flex items-center justify-between p-4 border-b bg-red-50 rounded-t-xl">
                <h3 class="font-semibold text-red-700">Buka Kembali Kas</h3>
                <button wire:click="$set('showBukaKembali', false)" class="text-gray-400">&times;</button>
            </div>
            <div class="p-4 space-y-4">
                @if($errorMsg)
                <div class="bg-red-50 border border-red-200 rounded-lg p-3 text-sm text-red-700">{{ $errorMsg }}</div>
                @endif

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Sesi Kas <span class="text-red-500">*</span></label>
                    <select wire:model="sesiIdBukaKembali"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm @error('sesiIdBukaKembali') border-red-400 @enderror">
                        <option value="">— Pilih Sesi —</option>
                        @foreach($sesiTutupHariIni as $s)
                        <option value="{{ $s->id }}">
                            {{ $s->user->nama }} &mdash; Tutup: {{ $s->ditutup_pada?->format('H:i') }}
                            (Saldo: Rp {{ number_format($s->saldo_akhir ?? 0, 0, ',', '.') }})
                        </option>
                        @endforeach
                    </select>
                    @error('sesiIdBukaKembali') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

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
                <button wire:click="bukaKasKembali" wire:loading.attr="disabled"
                    class="px-4 py-2 text-sm bg-red-600 text-white rounded-lg hover:bg-red-700">
                    <span wire:loading.remove>Buka Kas Kembali</span>
                    <span wire:loading>Memproses...</span>
                </button>
            </div>
        </div>
    </div>
    @endif
    @endif
    @endif
</div>

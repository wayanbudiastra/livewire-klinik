<div class="space-y-4">

    <div class="card">
        <div class="card-body flex flex-wrap items-end gap-4">
            <div class="form-group mb-0 w-56">
                <label class="form-label">Per Tanggal</label>
                <input type="date" wire:model.live="tanggal" class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200" />
            </div>
            <label class="flex items-center gap-2 mb-2">
                <input type="checkbox" wire:model.live="bandingkan" class="rounded text-blue-600" />
                <span class="text-sm">Bandingkan dengan tanggal lain</span>
            </label>
            @if($bandingkan)
            <div class="form-group mb-0 w-56">
                <label class="form-label">Tanggal Pembanding</label>
                <input type="date" wire:model.live="tanggalPembanding" class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200" />
            </div>
            @endif
        </div>
    </div>

    @if($this->hasil['periode_belum_ditutup']->isNotEmpty())
    <div class="rounded-lg bg-amber-50 border border-amber-200 px-4 py-2.5 text-sm text-amber-800">
        ⚠ Periode berikut belum ditutup: <strong>{{ $this->hasil['periode_belum_ditutup']->implode(', ') }}</strong>.
        Angka Laba Ditahan masih bisa berubah sampai periode-periode itu ditutup.
    </div>
    @endif

    <div class="card">
        <div class="card-header">
            <h3 class="text-sm font-semibold dark:text-white">Neraca per {{ \Carbon\Carbon::parse($tanggal)->format('d M Y') }}</h3>
            @if($this->hasil['seimbang'])
            <span class="badge badge-success">Seimbang</span>
            @else
            <span class="badge badge-danger">Tidak Seimbang!</span>
            @endif
        </div>
        <div class="card-body p-0">
            <table class="table">
                <thead>
                    <tr>
                        <th>Pos</th>
                        <th class="text-right">{{ \Carbon\Carbon::parse($tanggal)->format('d M Y') }}</th>
                        @if($bandingkan && $this->hasil['pembanding'])
                        <th class="text-right">{{ \Carbon\Carbon::parse($tanggalPembanding)->format('d M Y') }}</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    <tr class="bg-gray-50 dark:bg-gray-800/50"><td colspan="3" class="font-bold text-sm py-2 px-4">ASET</td></tr>
                    <tr><td colspan="3" class="text-xs font-semibold text-gray-500 px-4 pt-2">Aset Lancar</td></tr>
                    @foreach($this->hasil['aset_lancar'] as $r)
                    <tr>
                        <td class="text-sm pl-8">{{ $r['akun']->nama }} ({{ $r['akun']->kode }})</td>
                        <td class="text-right text-sm">Rp {{ number_format($r['nominal'], 0, ',', '.') }}</td>
                        @if($bandingkan && $this->hasil['pembanding'])
                        <td class="text-right text-sm text-gray-500">Rp {{ number_format($this->hasil['pembanding']['aset_lancar']->firstWhere('akun.kode', $r['akun']->kode)['nominal'] ?? 0, 0, ',', '.') }}</td>
                        @endif
                    </tr>
                    @endforeach
                    <tr class="border-t"><td class="text-sm font-medium pl-4">Total Aset Lancar</td><td class="text-right text-sm font-medium">Rp {{ number_format($this->hasil['total_aset_lancar'], 0, ',', '.') }}</td>@if($bandingkan && $this->hasil['pembanding'])<td class="text-right text-sm font-medium text-gray-500">Rp {{ number_format($this->hasil['pembanding']['total_aset_lancar'], 0, ',', '.') }}</td>@endif</tr>

                    <tr><td colspan="3" class="text-xs font-semibold text-gray-500 px-4 pt-2">Aset Tidak Lancar</td></tr>
                    @forelse($this->hasil['aset_tidak_lancar'] as $r)
                    <tr><td class="text-sm pl-8">{{ $r['akun']->nama }}</td><td class="text-right text-sm">Rp {{ number_format($r['nominal'], 0, ',', '.') }}</td>@if($bandingkan && $this->hasil['pembanding'])<td></td>@endif</tr>
                    @empty
                    <tr><td colspan="3" class="text-xs text-gray-400 pl-8 pb-1">- belum ada akun -</td></tr>
                    @endforelse

                    <tr class="border-t-2 bg-gray-50 dark:bg-gray-800/50">
                        <td class="text-sm font-bold pl-4">TOTAL ASET</td>
                        <td class="text-right text-sm font-bold">Rp {{ number_format($this->hasil['total_aset'], 0, ',', '.') }}</td>
                        @if($bandingkan && $this->hasil['pembanding'])
                        <td class="text-right text-sm font-bold text-gray-500">Rp {{ number_format($this->hasil['pembanding']['total_aset'], 0, ',', '.') }}</td>
                        @endif
                    </tr>

                    <tr class="bg-gray-50 dark:bg-gray-800/50"><td colspan="3" class="font-bold text-sm py-2 px-4 pt-4">LIABILITAS</td></tr>
                    <tr><td colspan="3" class="text-xs font-semibold text-gray-500 px-4 pt-2">Liabilitas Jangka Pendek</td></tr>
                    @foreach($this->hasil['liabilitas_pendek'] as $r)
                    <tr><td class="text-sm pl-8">{{ $r['akun']->nama }} ({{ $r['akun']->kode }})</td><td class="text-right text-sm">Rp {{ number_format($r['nominal'], 0, ',', '.') }}</td>@if($bandingkan && $this->hasil['pembanding'])<td></td>@endif</tr>
                    @endforeach
                    <tr class="border-t"><td class="text-sm font-medium pl-4">Total Liabilitas Jangka Pendek</td><td class="text-right text-sm font-medium">Rp {{ number_format($this->hasil['total_liabilitas_pendek'], 0, ',', '.') }}</td>@if($bandingkan && $this->hasil['pembanding'])<td></td>@endif</tr>

                    <tr><td colspan="3" class="text-xs font-semibold text-gray-500 px-4 pt-2">Liabilitas Jangka Panjang</td></tr>
                    @forelse($this->hasil['liabilitas_panjang'] as $r)
                    <tr><td class="text-sm pl-8">{{ $r['akun']->nama }}</td><td class="text-right text-sm">Rp {{ number_format($r['nominal'], 0, ',', '.') }}</td>@if($bandingkan && $this->hasil['pembanding'])<td></td>@endif</tr>
                    @empty
                    <tr><td colspan="3" class="text-xs text-gray-400 pl-8 pb-1">- belum ada akun -</td></tr>
                    @endforelse

                    <tr class="border-t-2 bg-gray-50 dark:bg-gray-800/50">
                        <td class="text-sm font-bold pl-4">TOTAL LIABILITAS</td>
                        <td class="text-right text-sm font-bold">Rp {{ number_format($this->hasil['total_liabilitas'], 0, ',', '.') }}</td>
                        @if($bandingkan && $this->hasil['pembanding'])
                        <td class="text-right text-sm font-bold text-gray-500">Rp {{ number_format($this->hasil['pembanding']['total_liabilitas'], 0, ',', '.') }}</td>
                        @endif
                    </tr>

                    <tr class="bg-gray-50 dark:bg-gray-800/50"><td colspan="3" class="font-bold text-sm py-2 px-4 pt-4">EKUITAS</td></tr>
                    @foreach($this->hasil['ekuitas_lain'] as $r)
                    <tr><td class="text-sm pl-8">{{ $r['akun']->nama }} ({{ $r['akun']->kode }})</td><td class="text-right text-sm">Rp {{ number_format($r['nominal'], 0, ',', '.') }}</td>@if($bandingkan && $this->hasil['pembanding'])<td></td>@endif</tr>
                    @endforeach
                    <tr><td class="text-sm pl-8">Laba Ditahan (3-1200, akumulasi sebelum tahun ini)</td><td class="text-right text-sm">Rp {{ number_format($this->hasil['laba_ditahan'], 0, ',', '.') }}</td>@if($bandingkan && $this->hasil['pembanding'])<td></td>@endif</tr>
                    <tr><td class="text-sm pl-8">Laba/Rugi Tahun Berjalan</td><td class="text-right text-sm">Rp {{ number_format($this->hasil['laba_tahun_berjalan'], 0, ',', '.') }}</td>@if($bandingkan && $this->hasil['pembanding'])<td></td>@endif</tr>

                    <tr class="border-t-2 bg-gray-50 dark:bg-gray-800/50">
                        <td class="text-sm font-bold pl-4">TOTAL EKUITAS</td>
                        <td class="text-right text-sm font-bold">Rp {{ number_format($this->hasil['total_ekuitas'], 0, ',', '.') }}</td>
                        @if($bandingkan && $this->hasil['pembanding'])
                        <td class="text-right text-sm font-bold text-gray-500">Rp {{ number_format($this->hasil['pembanding']['total_ekuitas'], 0, ',', '.') }}</td>
                        @endif
                    </tr>
                </tbody>
                <tfoot>
                    <tr class="bg-gray-100 dark:bg-gray-700/50 border-t-2">
                        <td class="text-sm font-bold py-3 px-4">TOTAL LIABILITAS + EKUITAS</td>
                        <td class="text-right text-sm font-bold py-3 px-4">Rp {{ number_format($this->hasil['total_liabilitas_ekuitas'], 0, ',', '.') }}</td>
                        @if($bandingkan && $this->hasil['pembanding'])
                        <td class="text-right text-sm font-bold py-3 px-4 text-gray-500">Rp {{ number_format($this->hasil['pembanding']['total_liabilitas_ekuitas'], 0, ',', '.') }}</td>
                        @endif
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

</div>

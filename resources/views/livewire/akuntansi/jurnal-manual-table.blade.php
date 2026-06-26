<div class="space-y-4">

    <div class="flex flex-wrap items-end justify-between gap-3">
        <div class="flex flex-wrap gap-3">
            <div>
                <label class="block text-xs font-medium text-gray-600">Dari Tanggal</label>
                <input type="date" wire:model.live="filterDari" class="mt-1 form-input w-40 text-sm" />
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600">Sampai Tanggal</label>
                <input type="date" wire:model.live="filterSampai" class="mt-1 form-input w-40 text-sm" />
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600">Kategori</label>
                <select wire:model.live="filterKategori" class="mt-1 form-input w-48 text-sm">
                    <option value="">Semua Kategori</option>
                    <option value="listrik">Listrik</option>
                    <option value="air">Air</option>
                    <option value="internet_telepon">Internet & Telepon</option>
                    <option value="sewa">Sewa</option>
                    <option value="gaji_non_dokter">Gaji Karyawan Non-Dokter</option>
                    <option value="pajak_retribusi">Pajak & Retribusi</option>
                    <option value="suntik_modal">Suntik Modal Pemilik</option>
                    <option value="lainnya">Lainnya</option>
                </select>
            </div>
        </div>
        @can('akuntansi.jurnal_manual.create')
        <a href="{{ route('akuntansi.jurnal-manual.create') }}" class="btn-primary">+ Input Jurnal Manual</a>
        @endcan
    </div>

    <div class="card">
        <div class="card-body p-0">
            <table class="table">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Kategori</th>
                        <th>Akun Debit → Kredit</th>
                        <th class="text-right">Nominal</th>
                        <th>Keterangan</th>
                        <th>Dokumen</th>
                        <th class="text-center">Status</th>
                        <th>Dibuat Oleh</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($this->rows as $jm)
                    <tr wire:key="jm-{{ $jm->id }}">
                        <td class="text-sm">{{ $jm->tanggal->format('d/m/Y') }}</td>
                        <td class="text-sm text-gray-500">{{ $jm->kategori ? ucwords(str_replace('_', ' ', $jm->kategori)) : '-' }}</td>
                        <td class="text-xs font-mono">
                            {{ $jm->kode_akun_debit }} → {{ $jm->kode_akun_kredit }}
                        </td>
                        <td class="text-right text-sm font-medium">Rp {{ number_format($jm->nominal, 0, ',', '.') }}</td>
                        <td class="text-sm">{{ $jm->keterangan }}</td>
                        <td class="text-sm">
                            @if($jm->dokumen_pendukung)
                            <a href="{{ \Illuminate\Support\Facades\Storage::url($jm->dokumen_pendukung) }}" target="_blank" class="text-primary-600 hover:underline">Lihat</a>
                            @else
                            <span class="text-gray-300">-</span>
                            @endif
                        </td>
                        <td class="text-center">
                            @php
                                $sc = match($jm->status) {
                                    'pending'    => 'badge-warning',
                                    'posted'     => 'badge-success',
                                    'diabaikan'  => 'badge-gray',
                                    'dibatalkan' => 'badge-gray',
                                    default      => 'badge-gray',
                                };
                            @endphp
                            <span class="badge {{ $sc }}">{{ ucfirst($jm->status) }}</span>
                        </td>
                        <td class="text-sm text-gray-500">{{ $jm->dibuatOleh->nama ?? '-' }}</td>
                        <td class="text-center">
                            @if($jm->status === 'posted')
                            <x-confirm-button
                                action="batalkan({{ $jm->id }})"
                                title="Batalkan Jurnal Manual Ini?"
                                text="Akan dibuatkan jurnal balik (reversal) otomatis."
                                icon="warning" type="danger" confirm="Ya, Batalkan"
                                class="btn-xs btn-danger">
                                Batalkan
                            </x-confirm-button>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="9" class="empty-state py-10"><p class="empty-state-text">Belum ada jurnal manual pada rentang ini</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($this->rows->hasPages())
        <div class="card-footer">{{ $this->rows->links() }}</div>
        @endif
    </div>

</div>

<x-app-layout>
    <x-slot name="title">Detail Pasien — {{ $pasien->nama }}</x-slot>

    <div class="page-header">
        <div class="flex items-center gap-3">
            <div>
                <div class="flex items-center gap-3 mb-1">
                    <h2 class="page-title">{{ $pasien->nama }}</h2>
                    <x-tipe-pasien :tipe="$pasien->tipe_pasien" />
                    @if (!$pasien->is_active)
                        <span class="badge-danger">Nonaktif</span>
                    @endif
                </div>
                <p class="page-subtitle font-mono">{{ $pasien->nomor_rm }}</p>
            </div>
        </div>
        <div class="flex gap-2">
            @can('pasien.edit')
            <a href="{{ route('pasien.edit', $pasien) }}" class="btn-warning">Edit Data</a>
            @endcan
            <a href="{{ route('pasien.index') }}" class="btn-secondary">← Kembali</a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

        {{-- Kolom kiri: Info utama + Kontak Darurat --}}
        <div class="lg:col-span-1 space-y-5">

            {{-- Card Identitas --}}
            <div class="card">
                <div class="card-header">
                    <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-100">Identitas</h3>
                </div>
                <div class="card-body space-y-3 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-500">No. RM</span>
                        <span class="font-mono font-semibold text-gray-800 dark:text-gray-200">{{ $pasien->nomor_rm }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Nama</span>
                        <span class="font-medium text-gray-800 dark:text-gray-200">{{ $pasien->nama }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Tempat/Tgl Lahir</span>
                        <span class="text-gray-700 dark:text-gray-300 text-right">
                            {{ $pasien->tempat_lahir }},<br>
                            {{ $pasien->tanggal_lahir->format('d/m/Y') }} ({{ $pasien->umur }} thn)
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Jenis Kelamin</span>
                        <span class="text-gray-700 dark:text-gray-300">{{ $pasien->jenis_kelamin_label }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Gol. Darah</span>
                        <span class="text-gray-700 dark:text-gray-300">{{ $pasien->golongan_darah ?? '-' }}</span>
                    </div>
                    @if ($pasien->tipe_pasien === 'WNI')
                    <div class="flex justify-between">
                        <span class="text-gray-500">NIK</span>
                        <span class="font-mono text-gray-700 dark:text-gray-300">{{ $pasien->nik ?? '-' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">No. BPJS</span>
                        <span class="font-mono text-gray-700 dark:text-gray-300">{{ $pasien->no_bpjs ?? '-' }}</span>
                    </div>
                    @else
                    <div class="flex justify-between">
                        <span class="text-gray-500">No. Paspor</span>
                        <span class="font-mono text-gray-700 dark:text-gray-300">{{ $pasien->no_paspor ?? '-' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Negara Asal</span>
                        <span class="text-gray-700 dark:text-gray-300">{{ $pasien->negara_asal ?? '-' }}</span>
                    </div>
                    @endif
                    <div class="flex justify-between">
                        <span class="text-gray-500">Telepon</span>
                        <span class="text-gray-700 dark:text-gray-300">{{ $pasien->telepon }}</span>
                    </div>
                    @if ($pasien->email)
                    <div class="flex justify-between">
                        <span class="text-gray-500">Email</span>
                        <span class="text-gray-700 dark:text-gray-300">{{ $pasien->email }}</span>
                    </div>
                    @endif
                    @if ($pasien->alergi)
                    <div class="pt-2 border-t border-gray-100 dark:border-gray-700">
                        <p class="text-gray-500 text-xs mb-1">Riwayat Alergi</p>
                        <p class="text-red-600 dark:text-red-400 text-sm font-medium">{{ $pasien->alergi }}</p>
                    </div>
                    @endif
                    <div class="pt-2 border-t border-gray-100 dark:border-gray-700">
                        <p class="text-gray-500 text-xs mb-1">Alamat</p>
                        <p class="text-gray-700 dark:text-gray-300 text-sm">{{ $pasien->alamat }}</p>
                    </div>
                </div>
            </div>

            {{-- Kontak Darurat --}}
            <div class="card">
                <div class="card-header">
                    <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-100">Kontak Darurat</h3>
                    <span class="badge-gray">{{ $pasien->kontakDarurat->count() }}</span>
                </div>
                <div class="card-body space-y-3">
                    @forelse ($pasien->kontakDarurat as $k)
                    <div @class([
                        'p-3 rounded-lg border text-sm',
                        'border-blue-200 bg-blue-50/50 dark:border-blue-700 dark:bg-blue-900/10' => $k->is_primary,
                        'border-gray-100 dark:border-gray-700' => !$k->is_primary,
                    ])>
                        <div class="flex items-center justify-between mb-1">
                            <p class="font-medium text-gray-800 dark:text-gray-200">{{ $k->nama }}</p>
                            @if ($k->is_primary)
                            <span class="text-xs text-blue-600 dark:text-blue-400 font-semibold">★ Utama</span>
                            @endif
                        </div>
                        <p class="text-gray-500 text-xs capitalize">{{ str_replace('_', ' ', $k->hubungan) }}</p>
                        <p class="text-gray-600 dark:text-gray-400 text-xs mt-1">{{ $k->nomor_hp }}</p>
                    </div>
                    @empty
                    <p class="text-sm text-gray-400 text-center py-3">Belum ada kontak darurat</p>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Kolom kanan: Riwayat Kunjungan --}}
        <div class="lg:col-span-2">
            <div class="card">
                <div class="card-header">
                    <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-100">Riwayat Kunjungan</h3>
                    <a href="{{ route('kunjungan.index') }}" class="text-xs text-primary-600 hover:underline dark:text-primary-400">
                        Lihat semua
                    </a>
                </div>
                <div class="card-body p-0">
                    @if ($pasien->kunjungan->isEmpty())
                    <div class="empty-state py-12">
                        <svg class="empty-state-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                  d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                        </svg>
                        <p class="empty-state-text">Belum ada riwayat kunjungan</p>
                    </div>
                    @else
                    <table class="table">
                        <thead>
                            <tr>
                                <th>No. Antrean</th>
                                <th>Tanggal</th>
                                <th>Poli</th>
                                <th>Dokter</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($pasien->kunjungan as $k)
                            <tr wire:key="kunjungan-{{ $k->id }}">
                                <td class="font-mono text-sm">{{ $k->nomor_antrean }}</td>
                                <td class="text-sm">{{ $k->tanggal->format('d/m/Y H:i') }}</td>
                                <td class="text-sm text-gray-600 dark:text-gray-400">
                                    {{ $k->poli?->nama ?? '-' }}
                                </td>
                                <td class="text-sm text-gray-600 dark:text-gray-400">
                                    {{ $k->dokter?->user?->nama ?? '-' }}
                                </td>
                                <td><x-badge-status :status="$k->status" /></td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

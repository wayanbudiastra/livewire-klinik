<div class="space-y-5">
    <div class="page-header">
        <div>
            <h1 class="page-title">Detail Adjustment</h1>
            <p class="page-subtitle font-mono">{{ $adjustment->nomor_adjustment }}</p>
        </div>
        <a href="{{ route('inventory.adjustment.index') }}" class="btn-secondary">Kembali</a>
    </div>

    <div class="card">
        <div class="card-body grid md:grid-cols-2 gap-5 text-sm">
            <div>
                <p class="text-gray-500">Barang</p>
                <p class="font-semibold">{{ $adjustment->barang->nama }} ({{ $adjustment->barang->kode }})</p>
            </div>

            <div>
                <p class="text-gray-500">Tipe / Jumlah</p>
                <p class="font-semibold">
                    {{ App\Models\AdjustmentStok::getTipeLabels()[$adjustment->tipe] }} -
                    {{ $adjustment->jumlah }} {{ $adjustment->barang->satuan }}
                </p>
            </div>

            <div>
                <p class="text-gray-500">Dibuat oleh / waktu</p>
                <p>{{ $adjustment->user->nama ?? '-' }} / {{ $adjustment->created_at->format('d/m/Y H:i:s') }}</p>
            </div>

            <div>
                <p class="text-gray-500">Referensi</p>
                <p>{{ $adjustment->bukti_referensi ?: '-' }}</p>
            </div>

            <div class="md:col-span-2">
                <p class="text-gray-500">Catatan</p>
                <p class="whitespace-pre-line">{{ $adjustment->catatan }}</p>
            </div>

            @if ($adjustment->mutasiStok)
                <div>
                    <p class="text-gray-500">Saldo sebelum</p>
                    <p>{{ $adjustment->mutasiStok->stok_sebelum }}</p>
                </div>

                <div>
                    <p class="text-gray-500">Saldo sesudah</p>
                    <p>{{ $adjustment->mutasiStok->stok_sesudah }}</p>
                </div>
            @endif
        </div>

        @if ($adjustment->file_attachment)
            <div class="card-footer">
                <a class="btn-secondary btn-sm" href="{{ route('inventory.adjustment.attachment', $adjustment) }}">
                    Unduh Bukti Attachment
                </a>
            </div>
        @endif
    </div>

    {{-- Tombol & Form Pembatalan (hanya jika user punya permission obat.delete) --}}
    @can('obat.delete')
        <div class="card border-red-200 bg-red-50">
            <div class="card-body">
                <p class="font-medium text-red-900">Zona Berbahaya</p>
                <p class="text-sm text-red-700 mt-2">
                    Pembatalan akan <strong>mengembalikan stok</strong> dan mencatat jejak di kartu stok sebagai mutasi lawan arah.
                    Adjustment ini akan di-soft-delete (audit trail tetap tersimpan).
                </p>

                @if ($errors->has('alasanBatal'))
                    <div class="alert alert-error mt-4">
                        <strong>Pembatalan gagal:</strong> {{ $errors->first('alasanBatal') }}
                    </div>
                @endif

                <form wire:submit="batalkan"
                      wire:confirm="Batalkan adjustment {{ $adjustment->nomor_adjustment }}? Stok {{ $adjustment->barang->nama }} akan {{ $adjustment->tipe === 'penyesuaian_masuk' ? 'dikurangi' : 'ditambah' }} {{ $adjustment->jumlah }} {{ $adjustment->barang->satuan }}."
                      class="mt-4 space-y-4">
                    <div class="form-group">
                        <label class="form-label">Alasan Pembatalan <span class="text-red-500">*</span></label>
                        <textarea wire:model="alasanBatal" rows="3" class="form-input"
                            placeholder="Contoh: Kesalahan pencatatan, duplikasi entry, atau koreksi nomor dokumen..."></textarea>
                        @error('alasanBatal')
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" wire:loading.attr="disabled" class="btn-danger">
                            <span wire:loading.remove>Konfirmasi Pembatalan</span>
                            <span wire:loading>Memproses pembatalan...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endcan
</div>

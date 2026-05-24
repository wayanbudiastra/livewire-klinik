<div class="card">
    <div class="card-body">
        <div class="flex flex-wrap items-end gap-3">

            <div class="form-group">
                <label class="form-label">Tipe Periode</label>
                <select wire:model.live="tipePeriode" class="form-select w-40">
                    <option value="bulanan">Bulanan</option>
                    <option value="triwulan">Triwulan</option>
                    <option value="semester">Semester</option>
                    <option value="tahunan">Tahunan</option>
                    <option value="kustom">Kustom</option>
                </select>
            </div>

            @if($tipePeriode !== 'kustom')
            <div class="form-group">
                <label class="form-label">Tahun</label>
                <select wire:model.live="tahun" class="form-select w-28">
                    @for($y = now()->year; $y >= now()->year - 5; $y--)
                        <option value="{{ $y }}">{{ $y }}</option>
                    @endfor
                </select>
            </div>
            @endif

            @if($tipePeriode === 'bulanan')
            <div class="form-group">
                <label class="form-label">Bulan</label>
                <select wire:model.live="bulan" class="form-select w-36">
                    @foreach(['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'] as $i => $nama)
                        <option value="{{ $i + 1 }}">{{ $nama }}</option>
                    @endforeach
                </select>
            </div>
            @endif

            @if($tipePeriode === 'triwulan')
            <div class="form-group">
                <label class="form-label">Triwulan</label>
                <select wire:model.live="triwulan" class="form-select w-36">
                    <option value="1">Q1 (Jan–Mar)</option>
                    <option value="2">Q2 (Apr–Jun)</option>
                    <option value="3">Q3 (Jul–Sep)</option>
                    <option value="4">Q4 (Okt–Des)</option>
                </select>
            </div>
            @endif

            @if($tipePeriode === 'semester')
            <div class="form-group">
                <label class="form-label">Semester</label>
                <select wire:model.live="semester" class="form-select w-44">
                    <option value="1">Semester 1 (Jan–Jun)</option>
                    <option value="2">Semester 2 (Jul–Des)</option>
                </select>
            </div>
            @endif

            @if($tipePeriode === 'kustom')
            <div class="form-group">
                <label class="form-label">Dari</label>
                <input type="date" wire:model.live="tanggalMulai" class="form-input" />
            </div>
            <div class="form-group">
                <label class="form-label">Sampai</label>
                <input type="date" wire:model.live="tanggalAkhir" class="form-input" />
            </div>
            @endif

            <div class="flex gap-2 ml-auto">
                <button type="button" wire:click="generate" class="btn-primary">
                    <span wire:loading.remove wire:target="generate">Tampilkan</span>
                    <span wire:loading wire:target="generate" class="flex items-center gap-2">
                        <div class="spinner w-4 h-4"></div> Memuat...
                    </span>
                </button>

                @can('laporan.export')
                @if(isset($hasil) && $hasil)
                <button type="button" wire:click="exportPdf" class="btn-danger">
                    <span wire:loading.remove wire:target="exportPdf">PDF</span>
                    <span wire:loading wire:target="exportPdf">...</span>
                </button>
                <button type="button" wire:click="exportExcel" class="btn-success">
                    <span wire:loading.remove wire:target="exportExcel">Excel</span>
                    <span wire:loading wire:target="exportExcel">...</span>
                </button>
                @endif
                @endcan
            </div>
        </div>
    </div>
</div>

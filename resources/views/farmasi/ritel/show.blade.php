@extends('layouts.app')

@section('title', 'Detail Transaksi — ' . $tr->nomor_ritel)

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title font-mono">{{ $tr->nomor_ritel }}</h1>
        <p class="page-subtitle">Detail Transaksi Ritel · {{ $tr->nama_pembeli }}</p>
    </div>
    <a href="{{ route('farmasi.ritel.index') }}" class="btn-secondary">← Kembali</a>
</div>

<livewire:farmasi.ritel.ritel-detail :transaksi="$tr" />
@endsection

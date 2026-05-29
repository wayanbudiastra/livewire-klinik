@extends('layouts.app')

@section('title', 'Penjualan Ritel')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Penjualan Ritel</h1>
        <p class="page-subtitle">Transaksi pembelian obat tanpa konsultasi dokter</p>
    </div>
    @can('obat.create')
    <a href="{{ route('farmasi.ritel.create') }}" class="btn-primary">
        + Transaksi Baru
    </a>
    @endcan
</div>

@if(session('success'))
<div class="alert alert-success mb-4">{{ session('success') }}</div>
@endif

<livewire:farmasi.ritel.ritel-table />
@endsection

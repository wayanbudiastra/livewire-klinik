@extends('layouts.app')

@section('title', 'Transaksi Ritel Baru')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Transaksi Ritel Baru</h1>
        <p class="page-subtitle">Input obat untuk pembeli tanpa konsultasi dokter</p>
    </div>
    <a href="{{ route('farmasi.ritel.index') }}" class="btn-secondary">← Kembali</a>
</div>

<livewire:farmasi.ritel.ritel-form />
@endsection

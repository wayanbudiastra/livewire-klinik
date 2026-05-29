@extends('layouts.app')

@section('title', 'Edit Transaksi — ' . $tr->nomor_ritel)

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Edit Transaksi</h1>
        <p class="page-subtitle font-mono text-sm">{{ $tr->nomor_ritel }}</p>
    </div>
    <a href="{{ route('farmasi.ritel.show', $tr->id) }}" class="btn-secondary">← Batal Edit</a>
</div>

<livewire:farmasi.ritel.ritel-form :id="$tr->id" />
@endsection

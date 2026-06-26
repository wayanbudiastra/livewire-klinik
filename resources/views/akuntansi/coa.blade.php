<x-app-layout>
    <x-slot name="title">Chart of Accounts</x-slot>

    <div class="page-header">
        <div>
            <h2 class="page-title">Chart of Accounts</h2>
            <p class="page-subtitle">Kelola daftar akun untuk pencatatan jurnal otomatis</p>
        </div>
    </div>

    <livewire:akuntansi.chart-of-account-manager />

    @include('akuntansi.partials.toast')
</x-app-layout>

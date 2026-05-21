@props(['dokter'])

@php
    $status   = $dokter->sip_status;
    $sisaHari = $dokter->sip_sisa_hari;
@endphp

@if ($status === 'aktif')
    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium
                 bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">
        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
        SIP Aktif ({{ $sisaHari }} hari)
    </span>
@elseif ($status === 'segera_expired')
    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium
                 bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">
        <span class="h-1.5 w-1.5 rounded-full bg-amber-500 animate-pulse"></span>
        Segera Expired ({{ $sisaHari }} hari)
    </span>
@elseif ($status === 'expired')
    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium
                 bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300">
        <span class="h-1.5 w-1.5 rounded-full bg-red-500"></span>
        SIP Expired
    </span>
@else
    <span class="badge-gray">Belum diisi</span>
@endif

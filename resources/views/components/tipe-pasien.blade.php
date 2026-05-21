@props(['tipe'])

@if ($tipe === 'WNI')
    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium
                 bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300">
        🇮🇩 WNI
    </span>
@else
    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium
                 bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300">
        🌐 WNA
    </span>
@endif

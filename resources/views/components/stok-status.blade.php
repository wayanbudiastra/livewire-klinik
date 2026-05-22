@props(['status'])

@php
$map = [
    'habis'     => ['label' => 'Habis',     'class' => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300'],
    'reorder'   => ['label' => 'Reorder!',  'class' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300'],
    'overstock' => ['label' => 'Overstock', 'class' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300'],
    'normal'    => ['label' => 'Normal',    'class' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'],
];
$item = $map[$status] ?? $map['normal'];
@endphp

<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium {{ $item['class'] }}">
    @if ($status === 'habis') ⚠ @elseif ($status === 'reorder') 🔔 @endif
    {{ $item['label'] }}
</span>

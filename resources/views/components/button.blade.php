@props([
    'variant' => 'primary',
    'size'    => '',
    'type'    => 'button',
    'href'    => null,
])

@php
    $classes = "btn btn-{$variant}" . ($size ? " btn-{$size}" : '');
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class([$classes]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->class([$classes]) }}>{{ $slot }}</button>
@endif

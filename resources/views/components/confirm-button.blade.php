{{--
    Komponen tombol dengan konfirmasi SweetAlert2
    Props:
      - action    : string  — wire:click action
      - title     : string  — judul dialog
      - text      : string  — isi pesan
      - icon      : string  — warning|error|question|info
      - type      : string  — danger|warning|primary|success
      - confirm   : string  — teks tombol konfirmasi
      - class     : string  — kelas tambahan
    Contoh:
      <x-confirm-button action="delete({{ $id }})" title="Hapus?" type="danger">Hapus</x-confirm-button>
--}}
@props([
    'action'  => '',
    'title'   => 'Yakin?',
    'text'    => '',
    'icon'    => 'warning',
    'type'    => 'danger',
    'confirm' => 'Ya, Lanjutkan',
])

<button
    type="button"
    x-data
    @click="confirmAction({
        title: '{{ addslashes($title) }}',
        text: '{{ addslashes($text) }}',
        icon: '{{ $icon }}',
        confirmText: '{{ addslashes($confirm) }}',
        confirmColor: '{{ $type }}',
        callback: () => $wire.{{ $action }}
    })"
    {{ $attributes }}
>{{ $slot }}</button>

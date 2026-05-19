@props(['status'])

@php
    $map = [
        'menunggu'          => ['label' => 'Menunggu',          'class' => 'badge-menunggu'],
        'dalam_pemeriksaan' => ['label' => 'Dalam Pemeriksaan', 'class' => 'badge-dalam_pemeriksaan'],
        'selesai'           => ['label' => 'Selesai',           'class' => 'badge-selesai'],
        'dibatalkan'        => ['label' => 'Dibatalkan',        'class' => 'badge-dibatalkan'],
        'diproses'          => ['label' => 'Diproses',          'class' => 'badge-info'],
        'siap'              => ['label' => 'Siap Diambil',      'class' => 'badge-success'],
        'diambil'           => ['label' => 'Sudah Diambil',     'class' => 'badge-gray'],
        'belum_bayar'       => ['label' => 'Belum Bayar',       'class' => 'badge-danger'],
        'sebagian'          => ['label' => 'Sebagian',          'class' => 'badge-warning'],
        'lunas'             => ['label' => 'Lunas',             'class' => 'badge-success'],
        'aktif'             => ['label' => 'Aktif',             'class' => 'badge-primary'],
        'keluar'            => ['label' => 'Keluar',            'class' => 'badge-gray'],
        'pindah_ruang'      => ['label' => 'Pindah Ruang',      'class' => 'badge-warning'],
    ];
    $item = $map[$status] ?? ['label' => $status, 'class' => 'badge-gray'];
@endphp

<span class="{{ $item['class'] }}">{{ $item['label'] }}</span>

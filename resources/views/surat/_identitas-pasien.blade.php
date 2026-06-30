@php
    $umur = $pasien->tanggal_lahir
        ? \Carbon\Carbon::parse($pasien->tanggal_lahir)->age . ' tahun'
        : '-';
    $tglLahir = $pasien->tanggal_lahir
        ? \Carbon\Carbon::parse($pasien->tanggal_lahir)->translatedFormat('d F Y')
        : '-';
@endphp
<table class="identitas-table">
    <tr><td class="label">Nama</td><td>: <strong>{{ $pasien->nama }}</strong></td></tr>
    <tr><td class="label">NIK</td><td>: {{ $pasien->nik ?? '-' }}</td></tr>
    <tr><td class="label">Tempat / Tgl Lahir</td><td>: {{ $pasien->tempat_lahir ?? '-' }} / {{ $tglLahir }} ({{ $umur }})</td></tr>
    <tr><td class="label">Jenis Kelamin</td><td>: {{ $pasien->jenis_kelamin === 'L' ? 'Laki-laki' : 'Perempuan' }}</td></tr>
    <tr><td class="label">Alamat</td><td>: {{ $pasien->alamat ?? '-' }}</td></tr>
</table>

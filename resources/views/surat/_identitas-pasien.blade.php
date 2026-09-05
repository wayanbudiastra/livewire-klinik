@php
    $bahasaId = $bahasa ?? ($surat->data['bahasa'] ?? 'id');
    $LI = $bahasaId === 'en' ? [
        'name' => 'Name', 'nik' => 'ID Number', 'pob_dob' => 'Place / Date of Birth',
        'gender' => 'Gender', 'address' => 'Address', 'male' => 'Male', 'female' => 'Female',
        'years' => 'years old',
    ] : [
        'name' => 'Nama', 'nik' => 'NIK', 'pob_dob' => 'Tempat / Tgl Lahir',
        'gender' => 'Jenis Kelamin', 'address' => 'Alamat', 'male' => 'Laki-laki', 'female' => 'Perempuan',
        'years' => 'tahun',
    ];
    $umur = $pasien->tanggal_lahir
        ? \Carbon\Carbon::parse($pasien->tanggal_lahir)->age . ' ' . $LI['years']
        : '-';
    $tglLahir = $pasien->tanggal_lahir
        ? \Carbon\Carbon::parse($pasien->tanggal_lahir)->locale($bahasaId)->translatedFormat('d F Y')
        : '-';
@endphp
<table class="identitas-table">
    <tr><td class="label">{{ $LI['name'] }}</td><td>: <strong>{{ $pasien->nama }}</strong></td></tr>
    <tr><td class="label">{{ $LI['nik'] }}</td><td>: {{ $pasien->nik ?? '-' }}</td></tr>
    <tr><td class="label">{{ $LI['pob_dob'] }}</td><td>: {{ $pasien->tempat_lahir ?? '-' }} / {{ $tglLahir }} ({{ $umur }})</td></tr>
    <tr><td class="label">{{ $LI['gender'] }}</td><td>: {{ $pasien->jenis_kelamin === 'L' ? $LI['male'] : $LI['female'] }}</td></tr>
    <tr><td class="label">{{ $LI['address'] }}</td><td>: {{ $pasien->alamat ?? '-' }}</td></tr>
</table>

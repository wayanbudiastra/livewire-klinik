<!DOCTYPE html>
<html lang="{{ ($surat->data['bahasa'] ?? 'id') === 'en' ? 'en' : 'id' }}">
<head>
<meta charset="UTF-8">
<style>
    body { font-family:'DejaVu Sans',sans-serif; font-size:10px; color:#111; margin:0; padding:24px 32px; }
    .judul { text-align:center; margin:16px 0 10px; }
    .judul h2 { font-size:14px; font-weight:bold; text-transform:uppercase; letter-spacing:1px; margin:0; text-decoration:underline; }
    .nomor { text-align:center; font-size:9px; color:#555; margin-bottom:14px; }
    .identitas-table td { padding:2px 4px; vertical-align:top; }
    .identitas-table .label { width:150px; color:#444; }
    .isi { margin:16px 0; line-height:1.8; text-align:justify; }
    .diagnosa-box { margin:10px 0; border-left:3px solid #0a3d62; padding:6px 10px; background:#f0f7ff; font-size:9px; }
    .ttd-section { margin-top:40px; }
    .footer { margin-top:30px; font-size:7px; color:#aaa; text-align:center; border-top:1px solid #eee; padding-top:6px; }
</style>
</head>
<body>
@php
    $bahasa = ($surat->data['bahasa'] ?? 'id') === 'en' ? 'en' : 'id';
    $L = $bahasa === 'en' ? [
        'title' => 'Certificate of Sick Leave', 'signed_below' => 'The undersigned, physician at',
        'certifies' => 'hereby certifies that:', 'based_on_exam' => 'Based on the examination conducted on',
        'needs_rest' => 'the above-named needs', 'rest_word' => 'REST', 'for_duration' => 'for',
        'days' => 'day(s)', 'starting' => 'starting from', 'until' => 'until',
        'diagnosis' => 'Diagnosis', 'primary' => 'Primary',
        'rest_notice' => 'During the rest period, the above-named is advised not to engage in strenuous activities.',
        'closing' => 'This certificate is issued truthfully to be used as required.',
    ] : [
        'title' => 'Surat Keterangan Sakit', 'signed_below' => 'Yang bertanda tangan di bawah ini, dokter pada',
        'certifies' => 'menerangkan bahwa:', 'based_on_exam' => 'Berdasarkan hasil pemeriksaan yang dilakukan pada tanggal',
        'needs_rest' => 'yang bersangkutan perlu', 'rest_word' => 'ISTIRAHAT', 'for_duration' => 'selama',
        'days' => 'hari', 'starting' => 'terhitung mulai tanggal', 'until' => 'sampai dengan',
        'diagnosis' => 'Diagnosa', 'primary' => 'Utama',
        'rest_notice' => 'Selama masa istirahat yang bersangkutan tidak diperkenankan melakukan kegiatan yang berat.',
        'closing' => 'Demikian surat keterangan ini dibuat dengan sebenarnya untuk dipergunakan sebagaimana mestinya.',
    ];
@endphp
@include('surat._kop')

<div class="judul">
    <h2>{{ $L['title'] }}</h2>
</div>
<div class="nomor">No: {{ $surat->nomor_surat }}</div>

<p>{{ $L['signed_below'] }} <strong>{{ $klinik->nama }}</strong>, {{ $L['certifies'] }}</p>

@include('surat._identitas-pasien')

@php
    $d     = $surat->data;
    $mulai = \Carbon\Carbon::parse($d['tanggal_mulai'])->locale($bahasa)->translatedFormat('d F Y');
    $akhir = \Carbon\Carbon::parse($d['tanggal_selesai'])->locale($bahasa)->translatedFormat('d F Y');
    $tglKunjungan = \Carbon\Carbon::parse($kunjungan->tanggal_kunjungan ?? $surat->dicetak_pada)->locale($bahasa)->translatedFormat('d F Y');
@endphp

<div class="isi">
    {{ $L['based_on_exam'] }} <strong>{{ $tglKunjungan }}</strong>,
    {{ $L['needs_rest'] }} <strong>{{ $L['rest_word'] }}</strong> {{ $L['for_duration'] }}
    <strong>{{ $d['lama_hari'] }} ({{ \App\Helpers\Terbilang::convert($d['lama_hari']) }}) {{ $L['days'] }}</strong>,
    {{ $L['starting'] }} <strong>{{ $mulai }}</strong> {{ $L['until'] }} <strong>{{ $akhir }}</strong>.
</div>

@if(!empty($d['tampilkan_diagnosa']) && !empty($d['diagnosa_snapshot']))
<div class="diagnosa-box">
    <strong>{{ $L['diagnosis'] }}:</strong><br>
    @foreach($d['diagnosa_snapshot'] as $dx)
    <span style="{{ $dx['is_primary'] ? 'font-weight:bold' : '' }}">
        {{ $dx['kode'] }} — {{ $dx['nama'] }}{{ $dx['is_primary'] ? ' (' . $L['primary'] . ')' : '' }}
    </span><br>
    @endforeach
</div>
@endif

<p>{{ $L['rest_notice'] }}</p>
<p>{{ $L['closing'] }}</p>

@include('surat._ttd-dokter')

<div class="footer">
    Surat ini diterbitkan oleh sistem EMR — {{ $klinik->nama }} | {{ $surat->nomor_surat }}
</div>
</body>
</html>

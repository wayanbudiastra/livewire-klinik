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
    .vitals { margin:10px 0; border:1px solid #ccc; border-radius:4px; padding:8px 12px; background:#f8fafc; }
    .vitals table td { padding:1px 6px; font-size:9px; }
    .ttd-section { margin-top:40px; }
    .footer { margin-top:30px; font-size:7px; color:#aaa; text-align:center; border-top:1px solid #eee; padding-top:6px; }
</style>
</head>
<body>
@php
    $bahasa = ($surat->data['bahasa'] ?? 'id') === 'en' ? 'en' : 'id';
    $L = $bahasa === 'en' ? [
        'title' => 'Certificate of Health', 'signed_below' => 'The undersigned, physician at',
        'certifies' => 'hereby certifies that:', 'exam_result' => 'Physical Examination Results',
        'blood_pressure' => 'Blood Pressure', 'heart_rate' => 'Heart Rate', 'temperature' => 'Temperature',
        'body_weight' => 'Body Weight', 'body_height' => 'Body Height',
        'colour_blindness' => 'Colour Blindness Examination',
        'based_on_exam' => 'Based on the examination conducted on', 'is_in' => 'the above-named is in',
        'healthy_state' => 'HEALTHY', 'condition' => 'condition and fit for daily activities',
        'for_purpose' => 'for the purpose of',
        'closing' => 'This certificate is issued truthfully to be used as required.',
    ] : [
        'title' => 'Surat Keterangan Sehat', 'signed_below' => 'Yang bertanda tangan di bawah ini, dokter pada',
        'certifies' => 'menerangkan bahwa:', 'exam_result' => 'Hasil Pemeriksaan Fisik',
        'blood_pressure' => 'Tekanan Darah', 'heart_rate' => 'Nadi', 'temperature' => 'Suhu',
        'body_weight' => 'Berat Badan', 'body_height' => 'Tinggi Badan',
        'colour_blindness' => 'Pemeriksaan Buta Warna',
        'based_on_exam' => 'Berdasarkan hasil pemeriksaan yang dilakukan pada tanggal', 'is_in' => 'yang bersangkutan dalam keadaan',
        'healthy_state' => 'SEHAT', 'condition' => 'dan layak melakukan aktivitas sehari-hari',
        'for_purpose' => 'untuk keperluan',
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

@if(!empty($surat->data['vitals_snapshot']))
@php $v = $surat->data['vitals_snapshot']; @endphp
<div class="vitals">
    <strong style="font-size:9px;">{{ $L['exam_result'] }}:</strong>
    <table style="width:100%;margin-top:4px;">
        <tr>
            @if($v['tekanan_darah'] ?? null)
            <td>{{ $L['blood_pressure'] }}: <strong>{{ $v['tekanan_darah'] }} mmHg</strong></td>
            @endif
            @if($v['nadi'] ?? null)
            <td>{{ $L['heart_rate'] }}: <strong>{{ $v['nadi'] }} x/mnt</strong></td>
            @endif
            @if($v['suhu'] ?? null)
            <td>{{ $L['temperature'] }}: <strong>{{ $v['suhu'] }} °C</strong></td>
            @endif
        </tr>
        <tr>
            @if($v['berat_badan'] ?? null)
            <td>{{ $L['body_weight'] }}: <strong>{{ $v['berat_badan'] }} kg</strong></td>
            @endif
            @if($v['tinggi_badan'] ?? null)
            <td>{{ $L['body_height'] }}: <strong>{{ $v['tinggi_badan'] }} cm</strong></td>
            @endif
            @if($v['bmi'] ?? null)
            <td>IMT/BMI: <strong>{{ $v['bmi'] }}</strong></td>
            @endif
        </tr>
        @if(!empty($surat->data['buta_warna']))
        <tr>
            <td colspan="3">{{ $L['colour_blindness'] }}: <strong>{{ \App\Models\SuratKeterangan::labelButaWarna($surat->data['buta_warna'], $bahasa) }}</strong></td>
        </tr>
        @endif
    </table>
</div>
@elseif(!empty($surat->data['buta_warna']))
<div class="vitals">
    <table style="width:100%;">
        <tr><td>{{ $L['colour_blindness'] }}: <strong>{{ \App\Models\SuratKeterangan::labelButaWarna($surat->data['buta_warna'], $bahasa) }}</strong></td></tr>
    </table>
</div>
@endif

<div class="isi">
    {{ $L['based_on_exam'] }}
    <strong>{{ \Carbon\Carbon::parse($kunjungan->tanggal_kunjungan ?? $surat->dicetak_pada)->locale($bahasa)->translatedFormat('d F Y') }}</strong>,
    {{ $L['is_in'] }} <strong>{{ $L['healthy_state'] }}</strong> {{ $L['condition'] }}
    @if(!empty($surat->data['keperluan']))
        {{ $L['for_purpose'] }} <strong>{{ $surat->data['keperluan'] }}</strong>.
    @else
        .
    @endif
</div>

<p>{{ $L['closing'] }}</p>

@include('surat._ttd-dokter')

<div class="footer">
    Surat ini diterbitkan oleh sistem EMR — {{ $klinik->nama }} | {{ $surat->nomor_surat }}
</div>
</body>
</html>

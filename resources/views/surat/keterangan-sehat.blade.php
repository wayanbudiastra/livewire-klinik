<!DOCTYPE html>
<html lang="id">
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
@include('surat._kop')

<div class="judul">
    <h2>Surat Keterangan Sehat</h2>
</div>
<div class="nomor">No: {{ $surat->nomor_surat }}</div>

<p>Yang bertanda tangan di bawah ini, dokter pada <strong>{{ $klinik->nama }}</strong>, menerangkan bahwa:</p>

@include('surat._identitas-pasien')

@if(!empty($surat->data['vitals_snapshot']))
@php $v = $surat->data['vitals_snapshot']; @endphp
<div class="vitals">
    <strong style="font-size:9px;">Hasil Pemeriksaan Fisik:</strong>
    <table style="width:100%;margin-top:4px;">
        <tr>
            @if($v['tekanan_darah'] ?? null)
            <td>Tekanan Darah: <strong>{{ $v['tekanan_darah'] }} mmHg</strong></td>
            @endif
            @if($v['nadi'] ?? null)
            <td>Nadi: <strong>{{ $v['nadi'] }} x/mnt</strong></td>
            @endif
            @if($v['suhu'] ?? null)
            <td>Suhu: <strong>{{ $v['suhu'] }} °C</strong></td>
            @endif
        </tr>
        <tr>
            @if($v['berat_badan'] ?? null)
            <td>Berat Badan: <strong>{{ $v['berat_badan'] }} kg</strong></td>
            @endif
            @if($v['tinggi_badan'] ?? null)
            <td>Tinggi Badan: <strong>{{ $v['tinggi_badan'] }} cm</strong></td>
            @endif
            @if($v['bmi'] ?? null)
            <td>IMT/BMI: <strong>{{ $v['bmi'] }}</strong></td>
            @endif
        </tr>
    </table>
</div>
@endif

<div class="isi">
    Berdasarkan hasil pemeriksaan yang dilakukan pada tanggal
    <strong>{{ \Carbon\Carbon::parse($kunjungan->tanggal_kunjungan ?? $surat->dicetak_pada)->translatedFormat('d F Y') }}</strong>,
    yang bersangkutan dalam keadaan <strong>SEHAT</strong> dan layak melakukan aktivitas sehari-hari
    @if(!empty($surat->data['keperluan']))
        untuk keperluan <strong>{{ $surat->data['keperluan'] }}</strong>.
    @else
        .
    @endif
</div>

<p>Demikian surat keterangan ini dibuat dengan sebenarnya untuk dipergunakan sebagaimana mestinya.</p>

@include('surat._ttd-dokter')

<div class="footer">
    Surat ini diterbitkan oleh sistem EMR — {{ $klinik->nama }} | {{ $surat->nomor_surat }}
</div>
</body>
</html>

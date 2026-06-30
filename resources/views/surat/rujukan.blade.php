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
    .section { margin:12px 0; }
    .section-title { font-weight:bold; font-size:9px; color:#0a3d62; text-transform:uppercase; border-bottom:1px solid #cce; margin-bottom:4px; padding-bottom:2px; }
    .diagnosa-table td { padding:2px 4px; font-size:9px; }
    .box { border:1px solid #ddd; border-radius:3px; padding:6px 10px; background:#fafafa; }
    .penunjang-item { font-size:9px; margin:2px 0; }
    .ttd-section { margin-top:40px; }
    .footer { margin-top:30px; font-size:7px; color:#aaa; text-align:center; border-top:1px solid #eee; padding-top:6px; }
</style>
</head>
<body>
@include('surat._kop')

<div class="judul">
    <h2>Surat Rujukan</h2>
</div>
<div class="nomor">No: {{ $surat->nomor_surat }}</div>

<p>Yang bertanda tangan di bawah ini merujuk pasien:</p>

@include('surat._identitas-pasien')

@php $d = $surat->data; @endphp

<div class="section">
    <div class="section-title">Tujuan Rujukan</div>
    <div class="box">
        <strong>Fasilitas/RS :</strong> {{ $d['tujuan_fasilitas'] }}<br>
        @if(!empty($d['tujuan_dokter']))
        <strong>Dokter Tujuan :</strong> {{ $d['tujuan_dokter'] }}<br>
        @endif
    </div>
</div>

@if(!empty($d['diagnosa_snapshot']))
<div class="section">
    <div class="section-title">Diagnosa</div>
    <table class="diagnosa-table" style="width:100%;">
        @foreach($d['diagnosa_snapshot'] as $dx)
        <tr>
            <td style="width:90px;font-family:monospace;font-weight:bold;">{{ $dx['kode'] }}</td>
            <td>{{ $dx['nama'] }}</td>
            <td style="width:60px;text-align:right;color:#0a3d62;">{{ $dx['is_primary'] ? 'Utama' : '' }}</td>
        </tr>
        @endforeach
    </table>
</div>
@endif

<div class="section">
    <div class="section-title">Indikasi / Alasan Rujukan</div>
    <div class="box" style="line-height:1.6;">{{ $d['indikasi'] }}</div>
</div>

@if(!empty($d['penunjang_snapshot']))
<div class="section">
    <div class="section-title">Riwayat Pemeriksaan Penunjang</div>
    @foreach($d['penunjang_snapshot'] as $p)
    <div class="penunjang-item">• {{ $p['nama'] }}
        @if(!empty($p['tanggal'])) ({{ \Carbon\Carbon::parse($p['tanggal'])->translatedFormat('d M Y') }})@endif
    </div>
    @endforeach
</div>
@endif

<p>Atas perhatian dan kerja sama Bapak/Ibu dokter, kami ucapkan terima kasih.</p>

@include('surat._ttd-dokter')

<div class="footer">
    Surat ini diterbitkan oleh sistem EMR — {{ $klinik->nama }} | {{ $surat->nomor_surat }}
</div>
</body>
</html>

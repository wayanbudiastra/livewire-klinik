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
    .tgl-kontrol { font-size:13px; font-weight:bold; color:#0a3d62; text-align:center; margin:14px 0; padding:8px; border:1px dashed #0a3d62; border-radius:4px; }
    .instruksi-box { margin:10px 0; border-left:3px solid #f59e0b; padding:6px 10px; background:#fffbeb; font-size:9px; line-height:1.6; }
    .diagnosa-box { margin:8px 0; font-size:9px; color:#555; }
    .ttd-section { margin-top:40px; }
    .footer { margin-top:30px; font-size:7px; color:#aaa; text-align:center; border-top:1px solid #eee; padding-top:6px; }
</style>
</head>
<body>
@include('surat._kop')

<div class="judul">
    <h2>Surat Jadwal Kontrol</h2>
</div>
<div class="nomor">No: {{ $surat->nomor_surat }}</div>

<p>Yang bertanda tangan di bawah ini memberitahukan kepada:</p>

@include('surat._identitas-pasien')

@php $d = $surat->data; @endphp

<div class="isi">
    Bahwa yang bersangkutan diminta untuk melakukan <strong>kontrol kembali</strong> pada:
</div>

<div class="tgl-kontrol">
    {{ \Carbon\Carbon::parse($d['tanggal_kontrol'])->translatedFormat('l, d F Y') }}
</div>

@if(!empty($d['diagnosa_snapshot']))
<div class="diagnosa-box">
    <strong>Berkaitan dengan:</strong>
    @foreach($d['diagnosa_snapshot'] as $dx)
    {{ $dx['kode'] }} — {{ $dx['nama'] }}
    @endforeach
</div>
@endif

@if(!empty($d['instruksi']))
<div class="instruksi-box">
    <strong>Instruksi / Pesan dokter:</strong><br>
    {{ $d['instruksi'] }}
</div>
@endif

<p>Mohon untuk hadir tepat waktu. Apabila tidak dapat hadir, harap menghubungi klinik terlebih dahulu.</p>

@include('surat._ttd-dokter')

<div class="footer">
    Surat ini diterbitkan oleh sistem EMR — {{ $klinik->nama }} | {{ $surat->nomor_surat }}
</div>
</body>
</html>

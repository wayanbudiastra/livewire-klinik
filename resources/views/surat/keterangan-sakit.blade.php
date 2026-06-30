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
    .diagnosa-box { margin:10px 0; border-left:3px solid #0a3d62; padding:6px 10px; background:#f0f7ff; font-size:9px; }
    .ttd-section { margin-top:40px; }
    .footer { margin-top:30px; font-size:7px; color:#aaa; text-align:center; border-top:1px solid #eee; padding-top:6px; }
</style>
</head>
<body>
@include('surat._kop')

<div class="judul">
    <h2>Surat Keterangan Sakit</h2>
</div>
<div class="nomor">No: {{ $surat->nomor_surat }}</div>

<p>Yang bertanda tangan di bawah ini, dokter pada <strong>{{ $klinik->nama }}</strong>, menerangkan bahwa:</p>

@include('surat._identitas-pasien')

@php
    $d     = $surat->data;
    $mulai = \Carbon\Carbon::parse($d['tanggal_mulai'])->translatedFormat('d F Y');
    $akhir = \Carbon\Carbon::parse($d['tanggal_selesai'])->translatedFormat('d F Y');
    $tglKunjungan = \Carbon\Carbon::parse($kunjungan->tanggal_kunjungan ?? $surat->dicetak_pada)->translatedFormat('d F Y');
@endphp

<div class="isi">
    Berdasarkan hasil pemeriksaan yang dilakukan pada tanggal <strong>{{ $tglKunjungan }}</strong>,
    yang bersangkutan perlu <strong>ISTIRAHAT</strong> selama
    <strong>{{ $d['lama_hari'] }} ({{ \App\Helpers\Terbilang::convert($d['lama_hari']) }}) hari</strong>,
    terhitung mulai tanggal <strong>{{ $mulai }}</strong> sampai dengan <strong>{{ $akhir }}</strong>.
</div>

@if(!empty($d['tampilkan_diagnosa']) && !empty($d['diagnosa_snapshot']))
<div class="diagnosa-box">
    <strong>Diagnosa:</strong><br>
    @foreach($d['diagnosa_snapshot'] as $dx)
    <span style="{{ $dx['is_primary'] ? 'font-weight:bold' : '' }}">
        {{ $dx['kode'] }} — {{ $dx['nama'] }}{{ $dx['is_primary'] ? ' (Utama)' : '' }}
    </span><br>
    @endforeach
</div>
@endif

<p>Selama masa istirahat yang bersangkutan tidak diperkenankan melakukan kegiatan yang berat.</p>
<p>Demikian surat keterangan ini dibuat dengan sebenarnya untuk dipergunakan sebagaimana mestinya.</p>

@include('surat._ttd-dokter')

<div class="footer">
    Surat ini diterbitkan oleh sistem EMR — {{ $klinik->nama }} | {{ $surat->nomor_surat }}
</div>
</body>
</html>

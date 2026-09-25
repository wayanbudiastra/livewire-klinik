<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Label {{ $pasien->nama }}</title>
<style>
    @page { margin: 0; }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
        font-family: 'DejaVu Sans', sans-serif;
        width: 283.46pt;
        height: 141.73pt;
        padding: 10pt 12pt;
        color: #111;
    }
    .klinik {
        font-size: 8pt;
        color: #555;
        text-transform: uppercase;
        letter-spacing: 0.5pt;
        border-bottom: 1pt solid #ccc;
        padding-bottom: 4pt;
        margin-bottom: 6pt;
    }
    .nama {
        font-size: 16pt;
        font-weight: bold;
        margin-bottom: 8pt;
        line-height: 1.15;
    }
    .row {
        display: flex;
        justify-content: space-between;
        font-size: 10pt;
        margin-bottom: 4pt;
    }
    .row .label { color: #666; }
    .row .value { font-weight: bold; }
</style>
</head>
<body>
    <div class="klinik">{{ $klinik->nama ?? 'Klinik' }}</div>
    <div class="nama">{{ $pasien->nama }}</div>
    <div class="row">
        <span class="label">No. RM</span>
        <span class="value">{{ $pasien->nomor_rm }}</span>
    </div>
    <div class="row">
        <span class="label">Tgl Lahir</span>
        <span class="value">{{ $pasien->tanggal_lahir?->format('d/m/Y') }} ({{ $pasien->umur }} th)</span>
    </div>
    <div class="row">
        <span class="label">Jenis Kelamin</span>
        <span class="value">{{ $pasien->jenis_kelamin_label }}</span>
    </div>
</body>
</html>

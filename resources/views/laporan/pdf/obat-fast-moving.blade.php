<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9px; color: #111; margin: 0; padding: 16px; }
        .header { text-align: center; border-bottom: 2px solid #d97706; padding-bottom: 8px; margin-bottom: 10px; }
        .header h1 { font-size: 15px; color: #d97706; margin: 0 0 2px; }
        .header .sub { font-size: 10px; color: #555; }
        .periode { text-align: center; font-size: 10px; margin-bottom: 8px; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #ddd; padding: 3px 5px; text-align: left; font-size: 8px; }
        th { background: #fffbeb; font-weight: bold; }
        .footer { margin-top: 16px; font-size: 7px; color: #999; text-align: right; }
        .low-stock { color: #dc2626; font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ config('emr.klinik_nama', 'Klinik') }}</h1>
        <div class="sub">Laporan Obat Fast Moving</div>
    </div>
    <div class="periode">Periode: {{ $label }}</div>

    <table>
        <thead>
            <tr>
                <th>#</th><th>Kode</th><th>Nama Obat</th><th>Jenis</th>
                <th style="text-align:right">Total Keluar</th><th style="text-align:right">Frekuensi</th>
                <th style="text-align:right">Stok Skrg</th><th>Satuan</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data['data'] as $i => $row)
            <tr>
                <td style="text-align:center;font-weight:bold">{{ $i + 1 }}</td>
                <td style="font-family:monospace">{{ $row['kode'] }}</td>
                <td>{{ $row['nama'] }}</td>
                <td style="text-transform:capitalize">{{ $row['jenis'] }}</td>
                <td style="text-align:right;font-weight:bold">{{ number_format($row['total_keluar']) }}</td>
                <td style="text-align:right">{{ number_format($row['frekuensi']) }}x</td>
                <td style="text-align:right" class="{{ $row['stok_sekarang'] < 10 ? 'low-stock' : '' }}">{{ number_format($row['stok_sekarang']) }}</td>
                <td>{{ $row['satuan'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">Dicetak pada {{ now()->format('d/m/Y H:i') }} oleh {{ auth()->user()->nama ?? auth()->user()->name }}</div>
</body>
</html>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9px; color: #111; margin: 0; padding: 16px; }
        .header { text-align: center; border-bottom: 2px solid #059669; padding-bottom: 8px; margin-bottom: 10px; }
        .header h1 { font-size: 15px; color: #059669; margin: 0 0 2px; }
        .header .sub { font-size: 10px; color: #555; }
        .periode { text-align: center; font-size: 10px; margin-bottom: 8px; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #ddd; padding: 3px 5px; text-align: left; font-size: 8px; }
        th { background: #ecfdf5; font-weight: bold; }
        .footer { margin-top: 16px; font-size: 7px; color: #999; text-align: right; }
        .total-row { font-weight: bold; background: #d1fae5; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ config('emr.klinik_nama', 'Klinik') }}</h1>
        <div class="sub">Laporan Nilai Inventory</div>
    </div>
    <div class="periode">{{ $label }}</div>
    <p style="font-size:8px;color:#666;">
        Total Jenis Barang: {{ number_format($data['total_jenis_barang']) }} |
        Total Nilai: Rp {{ number_format($data['total_nilai'], 0, ',', '.') }}
    </p>

    <table>
        <thead>
            <tr>
                <th>Kode</th><th>Nama</th><th>Jenis</th><th style="text-align:right">Stok</th>
                <th>Satuan</th><th style="text-align:right">Harga Pokok</th><th style="text-align:right">Nilai (Rp)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data['detail'] as $row)
            <tr>
                <td style="font-family:monospace">{{ $row['kode'] }}</td>
                <td>{{ $row['nama'] }}</td>
                <td style="text-transform:capitalize">{{ $row['jenis'] }}</td>
                <td style="text-align:right">{{ number_format($row['stok']) }}</td>
                <td>{{ $row['satuan'] }}</td>
                <td style="text-align:right">{{ number_format($row['harga_pokok'], 0, ',', '.') }}</td>
                <td style="text-align:right;font-weight:bold">{{ number_format($row['nilai'], 0, ',', '.') }}</td>
            </tr>
            @endforeach
            <tr class="total-row">
                <td colspan="6">TOTAL NILAI</td>
                <td style="text-align:right">{{ number_format($data['total_nilai'], 0, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>

    <div class="footer">Dicetak pada {{ now()->format('d/m/Y H:i') }} oleh {{ auth()->user()->nama ?? auth()->user()->name }}</div>
</body>
</html>

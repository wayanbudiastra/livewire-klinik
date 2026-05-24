<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9px; color: #111; margin: 0; padding: 16px; }
        .header { text-align: center; border-bottom: 2px solid #0891b2; padding-bottom: 8px; margin-bottom: 10px; }
        .header h1 { font-size: 15px; color: #0891b2; margin: 0 0 2px; }
        .header .sub { font-size: 10px; color: #555; }
        .periode { text-align: center; font-size: 10px; margin-bottom: 8px; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #ddd; padding: 3px 5px; text-align: left; font-size: 8px; }
        th { background: #ecfeff; font-weight: bold; }
        .footer { margin-top: 16px; font-size: 7px; color: #999; text-align: right; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ config('emr.klinik_nama', 'Klinik') }}</h1>
        <div class="sub">Laporan Rekap per Poli</div>
    </div>
    <div class="periode">Periode: {{ $label }}</div>

    <table>
        <thead><tr><th>#</th><th>Poli</th><th style="text-align:right">Total Kunjungan</th></tr></thead>
        <tbody>
            @foreach($data as $i => $row)
            <tr>
                <td style="text-align:center;font-weight:bold">{{ $i + 1 }}</td>
                <td>{{ $row['poli'] }}</td>
                <td style="text-align:right;font-weight:bold">{{ number_format($row['total']) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">Dicetak pada {{ now()->format('d/m/Y H:i') }} oleh {{ auth()->user()->nama ?? auth()->user()->name }}</div>
</body>
</html>

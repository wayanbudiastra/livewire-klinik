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
        <div class="sub">Laporan Rekap Resep</div>
    </div>
    <div class="periode">Periode: {{ $label }}</div>
    <p style="font-size:8px;color:#666;">Total Resep: {{ number_format($data['total_resep']) }} | Total Item Obat: {{ number_format($data['total_item_obat']) }}</p>

    <table>
        <thead>
            <tr><th>Dokter</th><th style="text-align:right">Jumlah Resep</th></tr>
        </thead>
        <tbody>
            @foreach($data['per_dokter'] as $dokter => $jumlah)
            <tr>
                <td>{{ $dokter }}</td>
                <td style="text-align:right">{{ number_format($jumlah) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">Dicetak pada {{ now()->format('d/m/Y H:i') }} oleh {{ auth()->user()->nama ?? auth()->user()->name }}</div>
</body>
</html>

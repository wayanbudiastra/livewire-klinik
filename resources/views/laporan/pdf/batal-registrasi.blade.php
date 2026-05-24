<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9px; color: #111; margin: 0; padding: 16px; }
        .header { text-align: center; border-bottom: 2px solid #dc2626; padding-bottom: 8px; margin-bottom: 10px; }
        .header h1 { font-size: 15px; color: #dc2626; margin: 0 0 2px; }
        .header .sub { font-size: 10px; color: #555; }
        .periode { text-align: center; font-size: 10px; margin-bottom: 8px; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #ddd; padding: 3px 5px; text-align: left; font-size: 8px; }
        th { background: #fef2f2; font-weight: bold; }
        .footer { margin-top: 16px; font-size: 7px; color: #999; text-align: right; }
        .total-box { background: #fef2f2; border: 1px solid #dc2626; padding: 6px 10px; display: inline-block; margin-bottom: 8px; }
        .total-box strong { color: #dc2626; font-size: 14px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ config('emr.klinik_nama', 'Klinik') }}</h1>
        <div class="sub">Laporan Batal Registrasi</div>
    </div>
    <div class="periode">Periode: {{ $label }}</div>

    <div class="total-box">Total Batal: <strong>{{ number_format($data['total_batal']) }}</strong> kunjungan</div>

    <table>
        <thead>
            <tr><th>Tanggal</th><th>No. RM</th><th>Nama Pasien</th><th>Poli</th><th>Status</th></tr>
        </thead>
        <tbody>
            @foreach($data['detail'] as $k)
            <tr>
                <td>{{ \Carbon\Carbon::parse($k->tanggal)->format('d/m/Y') }}</td>
                <td>{{ $k->pasien?->nomor_rm ?? '-' }}</td>
                <td>{{ $k->pasien?->nama ?? '-' }}</td>
                <td>{{ $k->poli?->nama ?? '-' }}</td>
                <td>Dibatalkan</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">Dicetak pada {{ now()->format('d/m/Y H:i') }} oleh {{ auth()->user()->nama ?? auth()->user()->name }}</div>
</body>
</html>

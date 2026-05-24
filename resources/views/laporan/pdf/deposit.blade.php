<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9px; color: #111; margin: 0; padding: 16px; }
        .header { text-align: center; border-bottom: 2px solid #7c3aed; padding-bottom: 8px; margin-bottom: 10px; }
        .header h1 { font-size: 15px; color: #7c3aed; margin: 0 0 2px; }
        .header .sub { font-size: 10px; color: #555; }
        .periode { text-align: center; font-size: 10px; margin-bottom: 8px; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #ddd; padding: 3px 5px; text-align: left; font-size: 8px; }
        th { background: #f5f3ff; font-weight: bold; }
        .footer { margin-top: 16px; font-size: 7px; color: #999; text-align: right; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ config('emr.klinik_nama', 'Klinik') }}</h1>
        <div class="sub">Laporan Deposit Pasien</div>
    </div>
    <div class="periode">Periode: {{ $label }}</div>
    <p style="font-size:8px;color:#666;">
        Topup: Rp {{ number_format($data['total_topup'], 0, ',', '.') }} |
        Pemakaian: Rp {{ number_format($data['total_pemakaian'], 0, ',', '.') }} |
        Refund: Rp {{ number_format($data['total_refund'], 0, ',', '.') }}
    </p>

    <table>
        <thead>
            <tr><th>Tanggal</th><th>No. Transaksi</th><th>Pasien</th><th>No. RM</th><th>Tipe</th><th style="text-align:right">Jumlah (Rp)</th><th style="text-align:right">Saldo Sesudah (Rp)</th></tr>
        </thead>
        <tbody>
            @forelse($data['detail'] as $row)
            <tr>
                <td>{{ $row['tanggal'] }}</td>
                <td style="font-family:monospace">{{ $row['nomor'] }}</td>
                <td>{{ $row['pasien'] }}</td>
                <td style="font-family:monospace">{{ $row['nomor_rm'] }}</td>
                <td style="text-transform:capitalize">{{ $row['tipe'] }}</td>
                <td style="text-align:right">{{ number_format($row['jumlah'], 0, ',', '.') }}</td>
                <td style="text-align:right">{{ number_format($row['saldo_sesudah'], 0, ',', '.') }}</td>
            </tr>
            @empty
            <tr><td colspan="7" style="text-align:center;color:#999">Tidak ada data</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">Dicetak pada {{ now()->format('d/m/Y H:i') }} oleh {{ auth()->user()->nama ?? auth()->user()->name }}</div>
</body>
</html>

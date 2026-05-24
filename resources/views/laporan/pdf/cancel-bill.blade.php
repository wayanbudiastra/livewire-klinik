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
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ config('emr.klinik_nama', 'Klinik') }}</h1>
        <div class="sub">Laporan Cancel Bill (Pembatalan Tagihan)</div>
    </div>
    <div class="periode">Periode: {{ $label }}</div>
    <p style="font-size:8px;color:#666;">Total Batal: {{ number_format($data['total_batal']) }} | Total Nilai: Rp {{ number_format($data['total_nilai_batal'], 0, ',', '.') }}</p>

    <table>
        <thead>
            <tr><th>No. Invoice</th><th>Tgl Batal</th><th>Pasien</th><th style="text-align:right">Nilai (Rp)</th><th>Alasan</th><th>Dibatalkan Oleh</th></tr>
        </thead>
        <tbody>
            @forelse($data['detail'] as $row)
            <tr>
                <td style="font-family:monospace">{{ $row['nomor_invoice'] }}</td>
                <td>{{ $row['tanggal_batal'] ?? '-' }}</td>
                <td>{{ $row['pasien'] }}</td>
                <td style="text-align:right">{{ number_format($row['nilai'], 0, ',', '.') }}</td>
                <td>{{ $row['alasan'] ?? '-' }}</td>
                <td>{{ $row['oleh'] ?? '-' }}</td>
            </tr>
            @empty
            <tr><td colspan="6" style="text-align:center;color:#999">Tidak ada data</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">Dicetak pada {{ now()->format('d/m/Y H:i') }} oleh {{ auth()->user()->nama ?? auth()->user()->name }}</div>
</body>
</html>

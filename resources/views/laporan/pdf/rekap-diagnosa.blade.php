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
        .rank-1 { background: #fef9c3; }
        .rank-2 { background: #f1f5f9; }
        .rank-3 { background: #fff7ed; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ config('emr.klinik_nama', 'Klinik') }}</h1>
        <div class="sub">Laporan Rekap Diagnosa — 10 Besar Penyakit</div>
    </div>
    <div class="periode">Periode: {{ $label }}</div>
    <p style="font-size:8px;color:#666;">Total diagnosa: {{ number_format($data['total_diagnosa']) }} | Jenis: {{ number_format($data['jumlah_jenis']) }}</p>

    <table>
        <thead>
            <tr><th>#</th><th>Kode ICD</th><th class="text-right">Jumlah Kasus</th><th class="text-right">Persentase</th></tr>
        </thead>
        <tbody>
            @php $rank = 1; $total = $data['total_diagnosa']; @endphp
            @foreach($data['sepuluh_besar'] as $kode => $jumlah)
            <tr class="{{ $rank === 1 ? 'rank-1' : ($rank === 2 ? 'rank-2' : ($rank === 3 ? 'rank-3' : '')) }}">
                <td style="text-align:center;font-weight:bold;">{{ $rank++ }}</td>
                <td style="font-family:monospace;font-weight:bold;">{{ $kode }}</td>
                <td style="text-align:right;">{{ number_format($jumlah) }}</td>
                <td style="text-align:right;">{{ $total > 0 ? round($jumlah / $total * 100, 1) : 0 }}%</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">Dicetak pada {{ now()->format('d/m/Y H:i') }} oleh {{ auth()->user()->nama ?? auth()->user()->name }}</div>
</body>
</html>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9px; color: #111; margin: 0; padding: 16px; }
        .header { text-align: center; border-bottom: 2px solid #1d4ed8; padding-bottom: 8px; margin-bottom: 10px; }
        .header h1 { font-size: 15px; color: #1d4ed8; margin: 0 0 2px; }
        .header .sub { font-size: 10px; color: #555; }
        .periode { text-align: center; font-size: 10px; margin-bottom: 8px; font-weight: bold; }
        .summary { display: table; width: 100%; margin: 8px 0; }
        .summary-item { display: table-cell; text-align: center; padding: 6px; background: #f3f4f6; border-radius: 4px; margin: 0 4px; }
        .summary-value { font-size: 16px; font-weight: bold; color: #1d4ed8; }
        .summary-label { font-size: 8px; color: #666; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #ddd; padding: 3px 5px; text-align: left; font-size: 8px; }
        th { background: #f3f4f6; font-weight: bold; }
        .footer { margin-top: 16px; font-size: 7px; color: #999; text-align: right; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ config('emr.klinik_nama', 'Klinik') }}</h1>
        <div class="sub">Laporan Kunjungan Pasien</div>
    </div>
    <div class="periode">Periode: {{ $label }}</div>

    <div class="summary">
        <div class="summary-item">
            <div class="summary-value">{{ number_format($data['total_kunjungan']) }}</div>
            <div class="summary-label">Total Kunjungan</div>
        </div>
        <div class="summary-item">
            <div class="summary-value">{{ number_format($data['pasien_baru']) }}</div>
            <div class="summary-label">Pasien Baru</div>
        </div>
        <div class="summary-item">
            <div class="summary-value">{{ number_format($data['pasien_lama']) }}</div>
            <div class="summary-label">Pasien Lama</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Tanggal</th><th>No. Antrean</th><th>No. RM</th><th>Nama Pasien</th>
                <th>Poli</th><th>Dokter</th><th>Tipe Bayar</th><th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data['detail'] as $k)
            <tr>
                <td>{{ \Carbon\Carbon::parse($k->tanggal)->format('d/m/Y') }}</td>
                <td>{{ $k->nomor_antrean ?? '-' }}</td>
                <td>{{ $k->pasien->nomor_rm ?? '-' }}</td>
                <td>{{ $k->pasien->nama ?? '-' }}</td>
                <td>{{ $k->poli?->nama ?? '-' }}</td>
                <td>{{ $k->dokter?->user->nama ?? '-' }}</td>
                <td>{{ $k->tipe_pembayaran ?? 'Umum' }}</td>
                <td>{{ ucfirst($k->status) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">Dicetak pada {{ now()->format('d/m/Y H:i') }} oleh {{ auth()->user()->nama ?? auth()->user()->name }}</div>
</body>
</html>

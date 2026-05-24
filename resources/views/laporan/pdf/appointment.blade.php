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
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #ddd; padding: 3px 5px; text-align: left; font-size: 8px; }
        th { background: #eff6ff; font-weight: bold; }
        .footer { margin-top: 16px; font-size: 7px; color: #999; text-align: right; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ config('emr.klinik_nama', 'Klinik') }}</h1>
        <div class="sub">Laporan Appointment</div>
    </div>
    <div class="periode">Periode: {{ $label }}</div>
    <p style="font-size:8px;color:#666;">Total: {{ $data['total'] }} | Hadir: {{ $data['hadir'] }} | Tidak Hadir: {{ $data['tidak_hadir'] }} | Rasio: {{ $data['rasio_hadir'] }}%</p>

    <table>
        <thead>
            <tr><th>Tanggal</th><th>Kode Booking</th><th>Pasien</th><th>Dokter</th><th>Poli</th><th>Status</th></tr>
        </thead>
        <tbody>
            @foreach($data['detail'] as $a)
            <tr>
                <td>{{ \Carbon\Carbon::parse($a->tanggal_appointment)->format('d/m/Y') }}</td>
                <td style="font-family:monospace">{{ $a->kode_booking }}</td>
                <td>{{ $a->pasien?->nama ?? '-' }}</td>
                <td>{{ $a->dokter?->user?->nama ?? '-' }}</td>
                <td>{{ $a->poli?->nama ?? '-' }}</td>
                <td style="text-transform:capitalize">{{ $a->status }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">Dicetak pada {{ now()->format('d/m/Y H:i') }} oleh {{ auth()->user()->nama ?? auth()->user()->name }}</div>
</body>
</html>

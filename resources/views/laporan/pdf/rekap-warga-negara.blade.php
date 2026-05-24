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
        <div class="sub">Laporan Rekap Warga Negara (WNI/WNA)</div>
    </div>
    <div class="periode">Periode: {{ $label }}</div>
    <p style="font-size:8px;color:#666;">WNI: {{ $data['total_wni'] }} pasien ({{ $data['kunjungan_wni'] }} kunjungan) | WNA: {{ $data['total_wna'] }} pasien ({{ $data['kunjungan_wna'] }} kunjungan)</p>

    @if($data['total_wna'] > 0)
    <p style="font-weight:bold;margin-top:8px;font-size:9px;">WNA per Negara Asal</p>
    <table>
        <thead><tr><th>Negara</th><th style="text-align:right">Jumlah Pasien</th></tr></thead>
        <tbody>
            @foreach($data['wna_per_negara'] as $negara => $jumlah)
            <tr><td>{{ $negara ?? 'N/A' }}</td><td style="text-align:right">{{ $jumlah }}</td></tr>
            @endforeach
        </tbody>
    </table>

    <p style="font-weight:bold;margin-top:10px;font-size:9px;">Detail Pasien WNA</p>
    <table>
        <thead><tr><th>No. RM</th><th>Nama</th><th>No. Paspor</th><th>Negara</th></tr></thead>
        <tbody>
            @foreach($data['detail_wna'] as $wna)
            <tr>
                <td style="font-family:monospace">{{ $wna['nomor_rm'] }}</td>
                <td>{{ $wna['nama'] }}</td>
                <td style="font-family:monospace">{{ $wna['no_paspor'] ?? '-' }}</td>
                <td>{{ $wna['negara_asal'] ?? '-' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    <div class="footer">Dicetak pada {{ now()->format('d/m/Y H:i') }} oleh {{ auth()->user()->nama ?? auth()->user()->name }}</div>
</body>
</html>

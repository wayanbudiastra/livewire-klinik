<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8"/>
    <style>
        body { font-family: Arial, sans-serif; font-size: 9pt; }
        h2 { font-size: 12pt; margin: 0; }
        .header { margin-bottom: 10px; border-bottom: 2px solid #0a3d62; padding-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; font-size: 8pt; }
        th { background: #0a3d62; color: white; padding: 5px 6px; text-align: left; }
        td { padding: 4px 6px; border-bottom: 1px solid #e5e7eb; }
        .masuk  { color: #059669; font-weight: bold; }
        .keluar { color: #dc2626; font-weight: bold; }
        .saldo  { font-weight: bold; text-align: center; }
        .total-row { background: #f3f4f6; font-weight: bold; }
        .footer { margin-top: 15px; font-size: 8pt; color: #6b7280; }
    </style>
</head>
<body>
    <div class="header">
        <h2>KARTU STOK BARANG</h2>
        <table style="border:none; margin-top: 6px;">
            <tr>
                <td style="border:none; width:120px; font-weight:bold; padding:2px 0">Kode Barang</td>
                <td style="border:none; padding:2px 0">: {{ $barang->kode }}</td>
                <td style="border:none; width:80px; font-weight:bold; padding:2px 0">Satuan</td>
                <td style="border:none; padding:2px 0">: {{ $barang->satuan }}</td>
            </tr>
            <tr>
                <td style="border:none; font-weight:bold; padding:2px 0">Nama Barang</td>
                <td style="border:none; padding:2px 0">: {{ $barang->nama }}</td>
                <td style="border:none; font-weight:bold; padding:2px 0">Periode</td>
                <td style="border:none; padding:2px 0">: {{ \Carbon\Carbon::parse($tanggal_mulai)->format('d/m/Y') }} — {{ \Carbon\Carbon::parse($tanggal_akhir)->format('d/m/Y') }}</td>
            </tr>
            <tr>
                <td style="border:none; font-weight:bold; padding:2px 0">Generik</td>
                <td style="border:none; padding:2px 0">: {{ $barang->nama_generik ?? '-' }}</td>
                <td style="border:none; font-weight:bold; padding:2px 0">Dicetak</td>
                <td style="border:none; padding:2px 0">: {{ now()->format('d/m/Y H:i') }}</td>
            </tr>
        </table>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width:70px">Tanggal</th>
                <th style="width:40px">Jam</th>
                <th style="width:80px">Tipe Mutasi</th>
                <th>Keterangan</th>
                <th style="width:60px; text-align:center">Masuk</th>
                <th style="width:60px; text-align:center">Keluar</th>
                <th style="width:60px; text-align:center">Saldo</th>
                <th style="width:80px; text-align:right">HPR (Rp)</th>
                <th style="width:90px">Dicatat Oleh</th>
            </tr>
        </thead>
        <tbody>
            <tr style="background:#eff6ff;">
                <td colspan="4" style="font-weight:bold; color:#1d4ed8;">Saldo Awal Periode</td>
                <td style="text-align:center">—</td>
                <td style="text-align:center">—</td>
                <td class="saldo">{{ number_format($saldo_awal) }}</td>
                <td style="text-align:right">{{ number_format($hpr_awal, 0, ',', '.') }}</td>
                <td>—</td>
            </tr>

            @foreach($rows as $row)
            <tr>
                <td>{{ $row['tanggal'] }}</td>
                <td>{{ $row['waktu'] }}</td>
                <td>{{ \App\Services\Inventory\KartuStokService::getTipeLabel($row['tipe']) }}</td>
                <td style="max-width:180px; overflow:hidden;">
                    {{ $row['keterangan'] ?? ($row['referensi_tipe'] ? $row['referensi_tipe'].'#'.$row['referensi_id'] : '—') }}
                </td>
                <td class="masuk" style="text-align:center">{{ $row['masuk'] > 0 ? '+'.number_format($row['masuk']) : '—' }}</td>
                <td class="keluar" style="text-align:center">{{ $row['keluar'] > 0 ? '-'.number_format($row['keluar']) : '—' }}</td>
                <td class="saldo" style="{{ $row['is_anomali'] ? 'color:#dc2626;' : '' }}">{{ number_format($row['saldo']) }}</td>
                <td style="text-align:right">{{ number_format($row['hpr'], 0, ',', '.') }}</td>
                <td>{{ $row['user_nama'] }}</td>
            </tr>
            @endforeach

            <tr class="total-row">
                <td colspan="4" style="text-align:right; padding-right:10px;">TOTAL PERIODE</td>
                <td class="masuk" style="text-align:center">+{{ number_format($total_masuk) }}</td>
                <td class="keluar" style="text-align:center">-{{ number_format($total_keluar) }}</td>
                <td class="saldo">{{ number_format($saldo_akhir) }}</td>
                <td colspan="2"></td>
            </tr>
        </tbody>
    </table>

    <div class="footer">
        <p>* Kartu stok ini digenerate otomatis dari sistem EMR. HPR dihitung menggunakan metode Moving Average.</p>
    </div>
</body>
</html>

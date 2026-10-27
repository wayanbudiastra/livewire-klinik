<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tagihan Gabungan {{ $billing->nomor_invoice }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 12px; color: #111; background: #fff; }
        .page { max-width: 800px; margin: 0 auto; padding: 32px 40px; }
        .header { border-bottom: 2px solid #1d4ed8; padding-bottom: 12px; margin-bottom: 16px; display: flex; justify-content: space-between; }
        .clinic-name { font-size: 20px; font-weight: 700; color: #1d4ed8; }
        h2 { font-size: 17px; color: #1d4ed8; text-align: right; }
        h3 { font-size: 12px; margin: 16px 0 6px; text-transform: uppercase; color: #374151; }
        .meta td { padding: 2px 10px 2px 0; } .meta td:first-child { color: #6b7280; width: 110px; }
        table.items { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        table.items th { background: #1d4ed8; color: #fff; padding: 6px 8px; text-align: left; font-size: 11px; }
        table.items td { padding: 6px 8px; border-bottom: 1px solid #f3f4f6; font-size: 11.5px; }
        .right { text-align: right; }
        .sub td { font-weight: 600; background: #f3f4f6; }
        .totals { width: 320px; margin-left: auto; margin-top: 14px; border: 1px solid #e5e7eb; border-collapse: collapse; }
        .totals td { padding: 6px 12px; } .totals td:last-child { text-align: right; }
        .totals .grand td { background: #1d4ed8; color: #fff; font-weight: 700; font-size: 13px; }
        .note { margin-top: 18px; font-size: 10.5px; color: #6b7280; }
        .no-print { text-align: center; padding: 12px; }
        @media print { .no-print { display: none; } .page { padding: 0; } }
    </style>
</head>
<body>
<div class="no-print"><button onclick="window.print()">Cetak</button></div>
<div class="page">
    <div class="header">
        <div>
            <div class="clinic-name">{{ config('app.name', 'Klinik') }}</div>
            <div style="color:#555;font-size:11px">Tagihan Gabungan (registrasi + susulan)</div>
        </div>
        <div><h2>TAGIHAN GABUNGAN</h2>
            <div class="right" style="color:#555;font-size:11px">{{ $billing->nomor_invoice }}</div></div>
    </div>

    <table class="meta">
        <tr><td>Pasien</td><td><strong>{{ $billing->kunjungan->pasien->nama }}</strong> ({{ $billing->kunjungan->pasien->nomor_rm }})</td></tr>
        <tr><td>Kunjungan</td><td>{{ $billing->kunjungan->tanggal->format('d/m/Y') }} · {{ $billing->kunjungan->poli?->nama }} · {{ $billing->kunjungan->dokter?->user?->nama }}</td></tr>
    </table>

    <h3>A. Registrasi — {{ $billing->nomor_invoice }}</h3>
    <table class="items">
        <thead><tr><th>Item</th><th class="right">Qty</th><th class="right">Harga</th><th class="right">Subtotal</th></tr></thead>
        <tbody>
        @foreach ($billing->items as $item)
            <tr><td>{{ $item->nama_item }}</td><td class="right">{{ $item->qty }}</td>
                <td class="right">{{ number_format($item->harga_satuan, 0, ',', '.') }}</td>
                <td class="right">{{ number_format($item->subtotal, 0, ',', '.') }}</td></tr>
        @endforeach
        <tr class="sub"><td colspan="3">Total registrasi</td><td class="right">Rp {{ number_format($total_billing, 0, ',', '.') }}</td></tr>
        </tbody>
    </table>

    @foreach ($susulan as $i => $tr)
        <h3>{{ chr(66 + $i) }}. Susulan {{ $tr->jenis_susulan === 'lab' ? 'Laboratorium' : 'Obat' }} — {{ $tr->nomor_ritel }}
            ({{ $tr->created_at->format('d/m/Y') }} · dokter {{ $tr->dokter?->user?->nama }})</h3>
        <table class="items">
            <thead><tr><th>Item</th><th class="right">Qty</th><th class="right">Harga</th><th class="right">Subtotal</th></tr></thead>
            <tbody>
            @foreach ($tr->items as $item)
                <tr><td>{{ $item->nama_item }}</td><td class="right">{{ $item->jumlah }}</td>
                    <td class="right">{{ number_format($item->harga_satuan, 0, ',', '.') }}</td>
                    <td class="right">{{ number_format($item->subtotal, 0, ',', '.') }}</td></tr>
            @endforeach
            <tr class="sub"><td colspan="3">Total susulan · {{ $tr->status_label }}</td>
                <td class="right">Rp {{ number_format($tr->total_harga, 0, ',', '.') }}</td></tr>
            </tbody>
        </table>
    @endforeach

    <table class="totals">
        <tr><td>Total registrasi</td><td>Rp {{ number_format($total_billing, 0, ',', '.') }}</td></tr>
        <tr><td>Total susulan</td><td>Rp {{ number_format($total_susulan, 0, ',', '.') }}</td></tr>
        <tr class="grand"><td>TOTAL GABUNGAN</td><td>Rp {{ number_format($total_gabungan, 0, ',', '.') }}</td></tr>
        <tr><td>Sudah dibayar pasien</td><td>Rp {{ number_format($total_dibayar, 0, ',', '.') }}</td></tr>
        <tr><td>Sisa</td><td>Rp {{ number_format($sisa, 0, ',', '.') }}</td></tr>
    </table>

    <p class="note">Dokumen ini menggabungkan tagihan registrasi dan susulan sebagai bukti pembayaran untuk pengajuan klaim reimburse asuransi. Pembayaran dilakukan sendiri oleh pasien.</p>
</div>
</body>
</html>

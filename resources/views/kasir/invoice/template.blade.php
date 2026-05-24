<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #111; position: relative; }

        .header-bar {
            background: #1d4ed8;
            color: white;
            padding: 12px 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .klinik-nama { font-size: 14px; font-weight: bold; }
        .klinik-sub  { font-size: 10px; opacity: 0.85; margin-top: 2px; }

        .stamp {
            position: absolute;
            top: 60px;
            right: 16px;
            font-size: 26px;
            font-weight: 900;
            letter-spacing: 3px;
            opacity: 0.12;
            transform: rotate(-30deg);
        }
        .stamp-original { color: #15803d; border: 4px solid #15803d; padding: 4px 8px; }
        .stamp-copy     { color: #b91c1c; border: 4px solid #b91c1c; padding: 4px 8px; }

        .label-jenis {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: bold;
            letter-spacing: 1px;
        }
        .label-original { background: #dcfce7; color: #15803d; border: 1px solid #86efac; }
        .label-copy     { background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; }

        .section { padding: 8px 16px; }
        .divider  { border-top: 1px dashed #d1d5db; margin: 4px 0; }
        .row      { display: flex; justify-content: space-between; margin-bottom: 2px; }
        .lbl      { color: #6b7280; }
        .val      { font-weight: 600; }
        table     { width: 100%; border-collapse: collapse; }
        th, td    { padding: 4px 8px; text-align: left; }
        th        { background: #f3f4f6; font-weight: 600; font-size: 10px; color: #374151; }
        .total-row{ background: #eff6ff; font-weight: bold; }
        .footer   { padding: 6px 16px; font-size: 9px; color: #9ca3af; text-align: center; border-top: 1px solid #e5e7eb; }
    </style>
</head>
<body>

<div class="stamp stamp-{{ strtolower($jenis) }}">{{ $jenis }}</div>

<div class="header-bar">
    <div>
        <div class="klinik-nama">{{ config('emr.klinik_nama', 'Klinik Sehat') }}</div>
        <div class="klinik-sub">{{ config('emr.klinik_alamat', '') }}</div>
    </div>
    <div style="text-align: right;">
        <div style="font-size: 16px; font-weight: bold;">INVOICE</div>
        <span class="label-jenis label-{{ strtolower($jenis) }}">{{ $jenis }}</span>
    </div>
</div>

<div class="section">
    <div class="row">
        <span class="lbl">No. Invoice</span>
        <span class="val" style="font-family: monospace;">{{ $billing->nomor_invoice }}</span>
    </div>
    <div class="row">
        <span class="lbl">Tanggal</span>
        <span class="val">{{ $billing->created_at->format('d/m/Y H:i') }}</span>
    </div>
    @if($nomorCetak > 1)
    <div class="row" style="color: #b91c1c;">
        <span>Cetakan ke-{{ $nomorCetak }} (COPY)</span>
        <span>{{ $dicetak_pada }}</span>
    </div>
    @endif
</div>

<div class="divider"></div>

<div class="section">
    <div class="row">
        <span class="lbl">Pasien</span>
        <span class="val">{{ $billing->kunjungan->pasien->nama }}</span>
    </div>
    <div class="row">
        <span class="lbl">No. RM</span>
        <span style="font-family: monospace;">{{ $billing->kunjungan->pasien->nomor_rm }}</span>
    </div>
    <div class="row">
        <span class="lbl">Poli / Dokter</span>
        <span>{{ $billing->kunjungan->poli?->nama ?? '-' }} &mdash; {{ $billing->kunjungan->dokter?->nama ?? '-' }}</span>
    </div>
</div>

<div class="divider"></div>

<div class="section" style="padding-bottom: 4px;">
    <table>
        <thead>
            <tr>
                <th>Item</th>
                <th style="text-align: right;">Qty</th>
                <th style="text-align: right;">Harga</th>
                <th style="text-align: right;">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($billing->items as $item)
            <tr>
                <td>{{ $item->nama_item }}</td>
                <td style="text-align: right;">{{ $item->qty }}</td>
                <td style="text-align: right;">{{ number_format($item->harga_satuan, 0, ',', '.') }}</td>
                <td style="text-align: right;">{{ number_format($item->subtotal, 0, ',', '.') }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="3" style="text-align: right; padding: 6px 8px;">Total Tagihan</td>
                <td style="text-align: right; padding: 6px 8px;">
                    Rp {{ number_format($billing->total_tagihan, 0, ',', '.') }}
                </td>
            </tr>
        </tfoot>
    </table>
</div>

<div class="divider"></div>

<div class="section">
    <div style="font-weight: 600; margin-bottom: 4px; font-size: 11px;">Rincian Pembayaran</div>
    @foreach($billing->pembayaranSplit as $split)
    <div class="row">
        <span class="lbl" style="text-transform: capitalize;">
            {{ str_replace('_', ' ', $split->metode) }}
            @if($split->nama_asuransi) ({{ $split->nama_asuransi }}) @endif
        </span>
        <span class="val">Rp {{ number_format($split->jumlah, 0, ',', '.') }}</span>
    </div>
    @endforeach
    <div class="divider"></div>
    <div class="row" style="font-weight: bold; font-size: 13px;">
        <span>TOTAL DIBAYAR</span>
        <span style="color: #15803d;">Rp {{ number_format($billing->total_bayar, 0, ',', '.') }}</span>
    </div>
</div>

<div class="footer">
    <div>Dicetak oleh: {{ $dicetak_oleh }} &mdash; {{ $dicetak_pada }}</div>
    <div>Dokumen ini {{ $jenis === 'ORIGINAL' ? 'adalah dokumen asli' : 'adalah salinan — bukan dokumen asli' }}</div>
</div>

</body>
</html>

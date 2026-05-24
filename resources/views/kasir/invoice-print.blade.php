<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice {{ $invoice->nomor_invoice }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 12px; color: #111; background: #fff; }

        .page { max-width: 800px; margin: 0 auto; padding: 32px 40px; }

        /* Header */
        .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #1d4ed8; padding-bottom: 16px; margin-bottom: 20px; }
        .clinic-name { font-size: 20px; font-weight: 700; color: #1d4ed8; }
        .clinic-sub  { font-size: 11px; color: #555; margin-top: 2px; }
        .invoice-title { text-align: right; }
        .invoice-title h2 { font-size: 18px; font-weight: 700; color: #1d4ed8; letter-spacing: 1px; }
        .invoice-title p  { font-size: 11px; color: #555; margin-top: 3px; }

        /* Info grid */
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0; margin-bottom: 18px; border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden; }
        .info-section { padding: 12px 16px; }
        .info-section:first-child { border-right: 1px solid #e5e7eb; }
        .info-label { font-size: 10px; font-weight: 600; text-transform: uppercase; color: #6b7280; letter-spacing: 0.5px; margin-bottom: 8px; }
        .info-row { display: flex; margin-bottom: 4px; }
        .info-key   { width: 110px; color: #6b7280; flex-shrink: 0; }
        .info-val   { font-weight: 500; }

        /* Items table */
        table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        thead th { background: #1d4ed8; color: #fff; padding: 8px 10px; text-align: left; font-size: 11px; }
        thead th.right { text-align: right; }
        tbody tr { border-bottom: 1px solid #f3f4f6; }
        tbody tr:nth-child(even) { background: #f9fafb; }
        tbody td { padding: 7px 10px; font-size: 11.5px; }
        tbody td.right { text-align: right; }
        tfoot td { padding: 6px 10px; font-size: 11.5px; }

        /* Badge */
        .badge { display: inline-block; padding: 2px 8px; border-radius: 99px; font-size: 10px; font-weight: 600; }
        .badge-bpjs   { background: #d1fae5; color: #065f46; }
        .badge-umum   { background: #dbeafe; color: #1e40af; }
        .badge-lunas  { background: #d1fae5; color: #065f46; }

        /* Totals */
        .totals { width: 280px; margin-left: auto; border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden; margin-bottom: 20px; }
        .totals tr td { padding: 6px 14px; }
        .totals tr td:last-child { text-align: right; font-weight: 500; }
        .totals .grand-total td { background: #1d4ed8; color: #fff; font-size: 13px; font-weight: 700; }
        .totals .divider td { border-top: 1px solid #e5e7eb; }

        /* Payment info */
        .payment-box { border: 1px solid #e5e7eb; border-radius: 8px; padding: 12px 16px; margin-bottom: 20px; background: #f9fafb; }
        .payment-box h4 { font-size: 11px; font-weight: 700; text-transform: uppercase; color: #6b7280; margin-bottom: 8px; letter-spacing: 0.5px; }
        .payment-row { display: flex; gap: 4px; margin-bottom: 3px; font-size: 11.5px; }
        .payment-row span:first-child { width: 120px; color: #6b7280; }

        /* Footer */
        .footer { display: flex; justify-content: space-between; margin-top: 24px; padding-top: 16px; border-top: 1px solid #e5e7eb; }
        .sign-box { text-align: center; }
        .sign-box .sign-line { width: 140px; border-bottom: 1px solid #111; margin: 48px auto 4px; }
        .sign-label { font-size: 10px; color: #6b7280; }

        .note { margin-top: 16px; font-size: 10px; color: #9ca3af; text-align: center; }

        @media print {
            body { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
            .no-print { display: none !important; }
            .page { padding: 16px 20px; }
        }
    </style>
</head>
<body>

<div class="no-print" style="background:#f3f4f6;padding:12px 40px;display:flex;align-items:center;gap:12px;border-bottom:1px solid #e5e7eb;">
    <button onclick="window.print()"
        style="background:#1d4ed8;color:#fff;border:none;padding:8px 20px;border-radius:8px;cursor:pointer;font-size:13px;font-weight:600;">
        🖨️ Cetak / Print
    </button>
    <button onclick="window.close()"
        style="background:#fff;color:#374151;border:1px solid #d1d5db;padding:8px 16px;border-radius:8px;cursor:pointer;font-size:13px;">
        Tutup
    </button>
    <span style="font-size:12px;color:#6b7280;margin-left:8px;">{{ $invoice->nomor_invoice }}</span>
</div>

<div class="page">

    {{-- Header --}}
    <div class="header">
        <div>
            <div class="clinic-name">{{ config('app.name', 'Klinik') }}</div>
            <div class="clinic-sub">Sistem Informasi Manajemen Klinik</div>
        </div>
        <div class="invoice-title">
            <h2>KWITANSI / INVOICE</h2>
            <p>No: <strong>{{ $invoice->nomor_invoice }}</strong></p>
            <p>Tgl: {{ $invoice->created_at->format('d/m/Y H:i') }}</p>
            <span class="badge badge-lunas">&#10003; LUNAS</span>
        </div>
    </div>

    {{-- Patient & Visit Info --}}
    <div class="info-grid">
        <div class="info-section">
            <div class="info-label">Data Pasien</div>
            <div class="info-row"><span class="info-key">Nama</span><span class="info-val">{{ $invoice->kunjungan->pasien->nama }}</span></div>
            <div class="info-row"><span class="info-key">No. RM</span><span class="info-val">{{ $invoice->kunjungan->pasien->nomor_rm }}</span></div>
            @if ($invoice->kunjungan->pasien->tanggal_lahir)
            <div class="info-row"><span class="info-key">Tgl. Lahir</span><span class="info-val">{{ \Carbon\Carbon::parse($invoice->kunjungan->pasien->tanggal_lahir)->format('d/m/Y') }}</span></div>
            @endif
            @if ($invoice->kunjungan->pasien->no_bpjs)
            <div class="info-row"><span class="info-key">No. BPJS</span><span class="info-val">{{ $invoice->kunjungan->pasien->no_bpjs }}</span></div>
            @endif
            @if ($invoice->kunjungan->pasien->alamat)
            <div class="info-row"><span class="info-key">Alamat</span><span class="info-val">{{ $invoice->kunjungan->pasien->alamat }}</span></div>
            @endif
        </div>
        <div class="info-section">
            <div class="info-label">Data Kunjungan</div>
            <div class="info-row"><span class="info-key">Tanggal</span><span class="info-val">{{ $invoice->kunjungan->tanggal->format('d/m/Y') }}</span></div>
            <div class="info-row"><span class="info-key">Poli</span><span class="info-val">{{ $invoice->kunjungan->poli->nama ?? '-' }}</span></div>
            <div class="info-row"><span class="info-key">Dokter</span><span class="info-val">{{ $invoice->kunjungan->dokter->nama ?? '-' }}</span></div>
            <div class="info-row">
                <span class="info-key">Penjamin</span>
                <span class="info-val">
                    <span class="badge {{ $invoice->kunjungan->tipe_pembayaran === 'bpjs' ? 'badge-bpjs' : 'badge-umum' }}">
                        {{ strtoupper($invoice->kunjungan->tipe_pembayaran ?? 'Umum') }}
                    </span>
                </span>
            </div>
            @if ($invoice->shift)
            <div class="info-row"><span class="info-key">Kasir</span><span class="info-val">{{ $invoice->shift->user->name ?? '-' }}</span></div>
            @endif
        </div>
    </div>

    {{-- Items Table --}}
    <table>
        <thead>
            <tr>
                <th style="width:30px">#</th>
                <th>Nama Item</th>
                <th style="width:70px">Jenis</th>
                <th class="right" style="width:60px">Qty</th>
                <th class="right" style="width:110px">Harga Satuan</th>
                <th class="right" style="width:90px">Diskon</th>
                <th class="right" style="width:110px">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->items as $i => $item)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $item->nama_item }}</td>
                <td>{{ ucfirst($item->jenis) }}</td>
                <td class="right">{{ rtrim(rtrim(number_format($item->qty, 2, ',', '.'), '0'), ',') }} {{ $item->satuan }}</td>
                <td class="right">{{ number_format($item->harga_satuan, 0, ',', '.') }}</td>
                <td class="right">{{ $item->diskon_item > 0 ? number_format($item->diskon_item, 0, ',', '.') : '-' }}</td>
                <td class="right"><strong>{{ number_format($item->subtotal, 0, ',', '.') }}</strong></td>
            </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Totals --}}
    <table class="totals">
        @php
            $subtotalBruto = $invoice->items->sum(fn ($i) => $i->harga_satuan * $i->qty);
            $subtotalDiskonItem = $invoice->items->sum('diskon_item');
        @endphp
        <tr>
            <td style="color:#6b7280">Subtotal Item</td>
            <td>Rp {{ number_format($subtotalBruto, 0, ',', '.') }}</td>
        </tr>
        @if ($subtotalDiskonItem > 0)
        <tr>
            <td style="color:#6b7280">Diskon Item</td>
            <td style="color:#dc2626">- Rp {{ number_format($subtotalDiskonItem, 0, ',', '.') }}</td>
        </tr>
        @endif
        @if ($invoice->diskon_global > 0)
        <tr>
            <td style="color:#6b7280">Diskon Global</td>
            <td style="color:#dc2626">- Rp {{ number_format($invoice->diskon_global, 0, ',', '.') }}</td>
        </tr>
        @endif
        <tr class="grand-total">
            <td>TOTAL TAGIHAN</td>
            <td>Rp {{ number_format($invoice->total_tagihan, 0, ',', '.') }}</td>
        </tr>
    </table>

    {{-- Payment Info --}}
    @foreach ($invoice->pembayaran as $bayar)
    <div class="payment-box">
        <h4>Informasi Pembayaran</h4>
        <div class="payment-row">
            <span>Metode</span>
            <span><strong>{{ match($bayar->metode) {
                'tunai'     => 'Tunai (Cash)',
                'non_tunai' => 'Non-Tunai',
                'asuransi'  => 'Asuransi / BPJS',
                default     => ucfirst($bayar->metode),
            } }}</strong></span>
        </div>
        @if ($bayar->bank_nama)
        <div class="payment-row">
            <span>Bank</span><span>{{ $bayar->bank_nama }} — {{ $bayar->tipe_kartu ?? '' }}</span>
        </div>
        @endif
        @if ($bayar->nomor_referensi)
        <div class="payment-row"><span>No. Referensi</span><span>{{ $bayar->nomor_referensi }}</span></div>
        @endif
        @if ($bayar->nama_asuransi)
        <div class="payment-row"><span>Asuransi</span><span>{{ $bayar->nama_asuransi }}</span></div>
        @endif
        <div class="payment-row">
            <span>Jumlah Bayar</span>
            <span><strong>Rp {{ number_format($bayar->jumlah, 0, ',', '.') }}</strong></span>
        </div>
        <div class="payment-row">
            <span>Waktu</span>
            <span>{{ \Carbon\Carbon::parse($bayar->created_at)->format('d/m/Y H:i') }}</span>
        </div>
    </div>
    @endforeach

    {{-- Footer / Signature --}}
    <div class="footer">
        <div>
            <p style="font-size:10px;color:#6b7280">Dokumen ini dicetak secara otomatis oleh sistem.</p>
            <p style="font-size:10px;color:#9ca3af;margin-top:2px;">{{ $invoice->nomor_invoice }} · {{ now()->format('d/m/Y H:i') }}</p>
        </div>
        <div class="sign-box">
            <div class="sign-line"></div>
            <div style="font-weight:600;font-size:11px;">{{ $invoice->shift->user->name ?? 'Petugas Kasir' }}</div>
            <div class="sign-label">Kasir</div>
        </div>
    </div>

    <div class="note">
        Simpan dokumen ini sebagai bukti pembayaran yang sah.
    </div>
</div>

<script>
    // Auto-print on load (comment out if not desired)
    // window.onload = () => window.print();
</script>
</body>
</html>

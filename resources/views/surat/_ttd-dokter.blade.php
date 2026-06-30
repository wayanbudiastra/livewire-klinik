@php
    $kota = explode(',', $klinik->alamat ?? '')[0] ?? 'Denpasar';
    $tglCetak = $surat->dicetak_pada->translatedFormat('d F Y');
@endphp
<div class="ttd-section">
    <table style="width:100%;">
        <tr>
            <td style="width:60%;"></td>
            <td style="text-align:center;">
                <div>{{ $kota }}, {{ $tglCetak }}</div>
                <div style="margin-top:2px;">Dokter Pemeriksa,</div>
                <div style="height:55px;"></div>
                <div style="border-top:1px solid #333;display:inline-block;min-width:150px;padding-top:3px;">
                    <strong>{{ $dokter->user->nama }}</strong><br>
                    <span style="font-size:8px;">No. SIP: {{ $dokter->no_sip ?? '-' }}</span>
                </div>
            </td>
        </tr>
    </table>
</div>

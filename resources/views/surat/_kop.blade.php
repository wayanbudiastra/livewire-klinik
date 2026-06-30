<div class="kop">
    <table style="width:100%;border-bottom:3px double #0a3d62;padding-bottom:8px;margin-bottom:6px;">
        <tr>
            @if($klinik->logo)
            <td style="width:70px;vertical-align:middle;">
                <img src="{{ public_path('storage/' . $klinik->logo) }}" style="height:55px;width:auto;">
            </td>
            @endif
            <td style="vertical-align:middle;padding-left:{{ $klinik->logo ? '10px' : '0' }};">
                <div style="font-size:16px;font-weight:bold;color:#0a3d62;letter-spacing:0.5px;">{{ $klinik->nama }}</div>
                @if($klinik->alamat)
                <div style="font-size:9px;color:#444;margin-top:2px;">{{ $klinik->alamat }}</div>
                @endif
                @if($klinik->telepon)
                <div style="font-size:9px;color:#444;">Telp: {{ $klinik->telepon }}</div>
                @endif
                @if($klinik->nomor_izin)
                <div style="font-size:8px;color:#666;">Izin: {{ $klinik->nomor_izin }}</div>
                @endif
            </td>
            @if($isCopy)
            <td style="width:80px;text-align:right;vertical-align:top;">
                <div style="border:2px solid #dc2626;color:#dc2626;font-size:11px;font-weight:bold;padding:3px 6px;display:inline-block;transform:rotate(-15deg);">COPY</div>
            </td>
            @endif
        </tr>
    </table>
</div>

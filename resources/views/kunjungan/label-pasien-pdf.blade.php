<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Label {{ $pasien->nama }}</title>
<style>
    {{-- "size" wajib di sini -- setPaper() saja ternyata menyisakan
         margin default dompdf yang tidak ke-nol-kan walau @page{margin:0}
         diset, sehingga tiap label kepotong ke halaman ke-2.

         .label-page SENGAJA tidak dikasih width/height + box-sizing:
         border-box -- kombinasi itu bikin dompdf salah hitung tinggi
         konten (padding dihitung DI LUAR tinggi, bukan termasuk di
         dalamnya seperti seharusnya border-box), jadi tiap label kepotong
         jadi 2 halaman. Ukuran fisik label cukup diatur lewat @page size
         di atas; .label-page cuma butuh padding sebagai margin internal. --}}
    @page { size: 283.46pt 141.73pt; margin: 0; }
    * { margin: 0; padding: 0; }
    body {
        font-family: 'DejaVu Sans', sans-serif;
        color: #111;
    }
    .label-page {
        padding: 10pt 12pt;
        page-break-after: always;
    }
    .label-page:last-child { page-break-after: auto; }
    .klinik {
        font-size: 8pt;
        color: #555;
        text-transform: uppercase;
        letter-spacing: 0.5pt;
        border-bottom: 1pt solid #ccc;
        padding-bottom: 4pt;
        margin-bottom: 6pt;
    }
    .nama {
        font-size: 16pt;
        font-weight: bold;
        margin-bottom: 8pt;
        line-height: 1.15;
    }
    .row {
        display: flex;
        justify-content: space-between;
        font-size: 10pt;
        margin-bottom: 4pt;
    }
    .row .label { color: #666; }
    .row .value { font-weight: bold; }
</style>
</head>
<body>
    {{-- Label diulang sebanyak $jumlah lembar (dipilih user lewat popup
         sebelum cetak, default 3) -- masing-masing di halaman terpisah
         supaya kalau dicetak, jadi $jumlah lembar fisik. --}}
    @for ($i = 0; $i < $jumlah; $i++)
    <div class="label-page">
        <div class="klinik">{{ $klinik->nama ?? 'Klinik' }}</div>
        <div class="nama">{{ $pasien->nama }}</div>
        <div class="row">
            <span class="label">No. RM</span>
            <span class="value">{{ $pasien->nomor_rm }}</span>
        </div>
        <div class="row">
            <span class="label">Tgl Lahir</span>
            <span class="value">{{ $pasien->tanggal_lahir?->format('d/m/Y') }} ({{ $pasien->umur }} th)</span>
        </div>
        <div class="row">
            <span class="label">Jenis Kelamin</span>
            <span class="value">{{ $pasien->jenis_kelamin_label }}</span>
        </div>
    </div>
    @endfor
</body>
</html>

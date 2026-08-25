<!DOCTYPE html>
<html lang="{{ ($surat->data['bahasa'] ?? 'id') === 'en' ? 'en' : 'id' }}">
<head>
<meta charset="UTF-8">
<style>
    body { font-family:'DejaVu Sans',sans-serif; font-size:9.5px; color:#111; margin:0; padding:24px 32px; }
    .judul { text-align:center; margin:14px 0 10px; }
    .judul h2 { font-size:14px; font-weight:bold; text-transform:uppercase; letter-spacing:1px; margin:0; }
    .meta-row td { padding:1px 0; font-size:9.5px; }
    .section-title { font-size:10.5px; font-weight:bold; color:#0a3d62; text-transform:uppercase; border-bottom:1px solid #0a3d62; padding-bottom:2px; margin:12px 0 5px; }
    .field-label { font-size:8.5px; color:#666; text-transform:uppercase; }
    .field-value { font-size:9.5px; margin-bottom:6px; }
    .info-table td { padding:2px 6px 2px 0; vertical-align:top; width:50%; }
    .vitals-table td { padding:2px 10px 2px 0; }
    .item-table { width:100%; border-collapse:collapse; margin-top:4px; }
    .item-table th { background:#f1f5f9; text-align:left; font-size:8.5px; text-transform:uppercase; padding:4px 6px; border-bottom:1px solid #cbd5e1; }
    .item-table td { padding:4px 6px; font-size:9px; border-bottom:1px solid #eee; vertical-align:top; }
    .box { border:1px solid #ccc; border-radius:4px; padding:8px 10px; margin:6px 0; background:#f8fafc; }
    .ttd-section { margin-top:30px; }
    .footer { margin-top:24px; font-size:7px; color:#aaa; text-align:center; border-top:1px solid #eee; padding-top:6px; }
    .empty { color:#999; font-style:italic; }
</style>
</head>
<body>
@php
    $bahasa = ($surat->data['bahasa'] ?? 'id') === 'en' ? 'en' : 'id';
    $L = $bahasa === 'en' ? [
        'title' => 'MEDICAL REPORT', 'mr_id' => 'MR ID', 'date' => 'Date',
        'patient_details' => 'Patient Details', 'id_patient' => 'ID Patient',
        'gender' => 'Gender', 'name' => 'Name', 'nationality' => 'Nationality',
        'dob' => 'Date Of Birth', 'address' => 'Address', 'allergy' => 'Allergy',
        'subjective' => 'Subjective', 'chief_complaint' => 'Chief Complaint',
        'presenting_complaint' => 'S (Subjective) / History of Present Illness',
        'vital_signs' => 'Vital Signs', 'blood_pressure' => 'Blood Pressure',
        'temperature' => 'Temperature', 'body_weight' => 'Body Weight',
        'heart_rate' => 'Heart Rate', 'spo2' => 'SpO2', 'body_height' => 'Body Height',
        'objective' => 'Objective', 'physical_exam' => 'Physical Examination Results',
        'penunjang' => 'Supporting Examination',
        'assessment' => 'Assessment', 'primary_diagnosis' => 'Primary Diagnosis',
        'diagnosis_icd10' => 'Diagnosis ICD 10', 'action' => 'Action',
        'medication' => 'Medication Dosage', 'item_code' => 'Item Code',
        'item_name' => 'Item Name', 'dosage' => 'Dosage Instruction',
        'qty' => 'Qty', 'unit' => 'Unit',
        'doctor_recommendation' => 'Doctor Recommendation',
        'escorted' => 'Escorted', 'flight' => 'Flight',
        'recommendation' => 'Recommendation', 'airport_facilities' => 'Medical Facilities at the Airport',
        'attending_physician' => 'Attending Physician',
        'male' => 'Male', 'female' => 'Female', 'none' => 'None',
        'no_data' => '-', 'completed' => 'completed', 'waiting' => 'in progress',
    ] : [
        'title' => 'RESUME MEDIS', 'mr_id' => 'No. Resume', 'date' => 'Tanggal',
        'patient_details' => 'Data Pasien', 'id_patient' => 'No. RM',
        'gender' => 'Jenis Kelamin', 'name' => 'Nama', 'nationality' => 'Kewarganegaraan',
        'dob' => 'Tanggal Lahir', 'address' => 'Alamat', 'allergy' => 'Alergi',
        'subjective' => 'Subjektif', 'chief_complaint' => 'Keluhan Utama',
        'presenting_complaint' => 'S (Subjective) / Riwayat Penyakit Sekarang',
        'vital_signs' => 'Tanda Vital', 'blood_pressure' => 'Tekanan Darah',
        'temperature' => 'Suhu', 'body_weight' => 'Berat Badan',
        'heart_rate' => 'Nadi', 'spo2' => 'Saturasi O2', 'body_height' => 'Tinggi Badan',
        'objective' => 'Objektif', 'physical_exam' => 'Hasil Pemeriksaan Fisik',
        'penunjang' => 'Pemeriksaan Penunjang',
        'assessment' => 'Asesmen', 'primary_diagnosis' => 'Diagnosis Utama',
        'diagnosis_icd10' => 'Diagnosis ICD-10', 'action' => 'Tindakan',
        'medication' => 'Resep Obat', 'item_code' => 'Kode',
        'item_name' => 'Nama Obat', 'dosage' => 'Aturan Pakai',
        'qty' => 'Jumlah', 'unit' => 'Satuan',
        'doctor_recommendation' => 'Rekomendasi Dokter',
        'escorted' => 'Pendamping', 'flight' => 'Penerbangan',
        'recommendation' => 'Rekomendasi', 'airport_facilities' => 'Fasilitas Medis di Bandara',
        'attending_physician' => 'Dokter Pemeriksa',
        'male' => 'Laki-laki', 'female' => 'Perempuan', 'none' => 'Tidak ada',
        'no_data' => '-', 'completed' => 'selesai', 'waiting' => 'proses',
    ];

    $d = $surat->data;
    $tglLahir = $pasien->tanggal_lahir
        ? \Carbon\Carbon::parse($pasien->tanggal_lahir)->locale($bahasa)->translatedFormat('d F Y')
        : $L['no_data'];
    $kewarganegaraan = $pasien->negara_asal
        ?: ($pasien->tipe_pasien === 'WNA' ? $L['no_data'] : 'Indonesia');
    $diagnosaUtama = collect($d['diagnosa_snapshot'] ?? [])->firstWhere('is_primary', true);
    $diagnosaLain  = collect($d['diagnosa_snapshot'] ?? [])->where('is_primary', false)->values();
@endphp

@include('surat._kop')

<div class="judul"><h2>{{ $L['title'] }}</h2></div>

<table class="meta-row" style="width:100%;">
    <tr>
        <td>{{ $L['mr_id'] }} : <strong>{{ $surat->nomor_surat }}</strong></td>
        <td style="text-align:right;">{{ $L['date'] }} : <strong>{{ $surat->dicetak_pada->locale($bahasa)->translatedFormat('d F Y') }}</strong></td>
    </tr>
</table>

<div class="section-title">{{ $L['patient_details'] }}</div>
<table class="info-table" style="width:100%;">
    <tr>
        <td>
            <span class="field-label">{{ $L['id_patient'] }}</span><br>
            <span class="field-value">{{ $pasien->nomor_rm }}</span>
        </td>
        <td>
            <span class="field-label">{{ $L['dob'] }}</span><br>
            <span class="field-value">{{ $tglLahir }}</span>
        </td>
    </tr>
    <tr>
        <td>
            <span class="field-label">{{ $L['name'] }}</span><br>
            <span class="field-value"><strong>{{ $pasien->nama }}</strong></span>
        </td>
        <td>
            <span class="field-label">{{ $L['address'] }}</span><br>
            <span class="field-value">{{ $pasien->alamat ?: $L['no_data'] }}</span>
        </td>
    </tr>
    <tr>
        <td>
            <span class="field-label">{{ $L['gender'] }}</span><br>
            <span class="field-value">{{ $pasien->jenis_kelamin === 'L' ? $L['male'] : $L['female'] }}</span>
        </td>
        <td>
            <span class="field-label">{{ $L['allergy'] }}</span><br>
            <span class="field-value">{{ $pasien->alergi ?: $L['none'] }}</span>
        </td>
    </tr>
    <tr>
        <td>
            <span class="field-label">{{ $L['nationality'] }}</span><br>
            <span class="field-value">{{ $kewarganegaraan }}</span>
        </td>
        <td></td>
    </tr>
</table>

<div class="section-title">{{ $L['subjective'] }}</div>

<span class="field-label">{{ $L['chief_complaint'] }}</span>
<div class="field-value">{{ $d['anamnesis_snapshot'] ?: $L['no_data'] }}</div>

<span class="field-label">{{ $L['presenting_complaint'] }}</span>
<div class="field-value">{{ $d['subjektif_snapshot'] ?: $L['no_data'] }}</div>

@if(!empty($d['vitals_snapshot']))
@php $v = $d['vitals_snapshot']; @endphp
<div class="section-title">{{ $L['vital_signs'] }}</div>
<div class="box">
    <table class="vitals-table" style="width:100%;">
        <tr>
            @if($v['tekanan_darah'] ?? null)<td>{{ $L['blood_pressure'] }}: <strong>{{ $v['tekanan_darah'] }} mmHg</strong></td>@endif
            @if($v['nadi'] ?? null)<td>{{ $L['heart_rate'] }}: <strong>{{ $v['nadi'] }} x/mnt</strong></td>@endif
            @if($v['suhu'] ?? null)<td>{{ $L['temperature'] }}: <strong>{{ $v['suhu'] }} °C</strong></td>@endif
            @if($v['saturasi'] ?? null)<td>{{ $L['spo2'] }}: <strong>{{ $v['saturasi'] }} %</strong></td>@endif
        </tr>
        <tr>
            @if($v['berat_badan'] ?? null)<td>{{ $L['body_weight'] }}: <strong>{{ $v['berat_badan'] }} kg</strong></td>@endif
            @if($v['tinggi_badan'] ?? null)<td>{{ $L['body_height'] }}: <strong>{{ $v['tinggi_badan'] }} cm</strong></td>@endif
            @if($v['bmi'] ?? null)<td>BMI: <strong>{{ $v['bmi'] }}</strong></td>@endif
        </tr>
    </table>
</div>
@endif

<div class="section-title">{{ $L['objective'] }}</div>
<span class="field-label">{{ $L['physical_exam'] }}</span>
<div class="field-value">{{ $d['objektif_snapshot'] ?: $L['no_data'] }}</div>

<span class="field-label">{{ $L['penunjang'] }}</span>
@if(!empty($d['penunjang_snapshot']))
<table class="item-table">
    @foreach($d['penunjang_snapshot'] as $p)
    <tr><td>{{ $p['nama'] }}</td><td style="text-align:right;">{{ $p['status'] === 'selesai' ? $L['completed'] : $L['waiting'] }}</td></tr>
    @endforeach
</table>
@else
<div class="field-value empty">{{ $L['no_data'] }}</div>
@endif

<div class="section-title">{{ $L['assessment'] }}</div>
<span class="field-label">{{ $L['primary_diagnosis'] }}</span>
<div class="field-value">{{ $diagnosaUtama['nama'] ?? $L['no_data'] }}</div>

<span class="field-label">{{ $L['diagnosis_icd10'] }}</span>
<div class="field-value">
    @if($diagnosaUtama)
        {{ $diagnosaUtama['kode'] }} - {{ $diagnosaUtama['nama'] }}
    @else
        {{ $L['no_data'] }}
    @endif
    @foreach($diagnosaLain as $dx)
        <br>{{ $dx['kode'] }} - {{ $dx['nama'] }}
    @endforeach
</div>

<div class="section-title">{{ $L['action'] }}</div>
@if(!empty($d['tindakan_snapshot']))
    @foreach($d['tindakan_snapshot'] as $t)
    <div class="field-value">- {{ $t['nama'] }} @if($t['jumlah'] > 1) ({{ $t['jumlah'] }}x) @endif</div>
    @endforeach
@else
<div class="field-value empty">{{ $L['no_data'] }}</div>
@endif

<div class="section-title">{{ $L['medication'] }}</div>
@if(!empty($d['resep_snapshot']))
<table class="item-table">
    <tr>
        <th>{{ $L['item_code'] }}</th>
        <th>{{ $L['item_name'] }}</th>
        <th>{{ $L['dosage'] }}</th>
        <th>{{ $L['qty'] }}</th>
        <th>{{ $L['unit'] }}</th>
    </tr>
    @foreach($d['resep_snapshot'] as $r)
    <tr>
        <td>{{ $r['kode'] }}</td>
        <td>{{ $r['nama'] }}</td>
        <td>{{ $r['aturan_pakai'] }}</td>
        <td>{{ $r['jumlah'] }}</td>
        <td>{{ $r['satuan'] }}</td>
    </tr>
    @endforeach
</table>
@else
<div class="field-value empty">{{ $L['no_data'] }}</div>
@endif

@if(!empty($d['plan_snapshot']))
<div class="section-title">{{ $L['doctor_recommendation'] }}</div>
<div class="field-value">{{ $d['plan_snapshot'] }}</div>
@endif

@if(!empty($d['escorted']) || !empty($d['flight']) || !empty($d['recommendation']) || !empty($d['fasilitas_bandara']))
<table class="info-table" style="width:100%; margin-top:8px;">
    <tr>
        <td><span class="field-label">{{ $L['escorted'] }}</span><br><span class="field-value">{{ $d['escorted'] ?: '-' }}</span></td>
        <td><span class="field-label">{{ $L['recommendation'] }}</span><br><span class="field-value">{{ $d['recommendation'] ?: '-' }}</span></td>
    </tr>
    <tr>
        <td><span class="field-label">{{ $L['flight'] }}</span><br><span class="field-value">{{ $d['flight'] ?: '-' }}</span></td>
        <td><span class="field-label">{{ $L['airport_facilities'] }}</span><br><span class="field-value">{{ $d['fasilitas_bandara'] ?: '-' }}</span></td>
    </tr>
</table>
@endif

@php
    $kotaTtd  = explode(',', $klinik->alamat ?? '')[0] ?? 'Denpasar';
    $tglCetak = $surat->dicetak_pada->locale($bahasa)->translatedFormat('d F Y');
@endphp
<div class="ttd-section">
    <table style="width:100%;">
        <tr>
            <td style="width:60%;"></td>
            <td style="text-align:center;">
                <div>{{ $kotaTtd }}, {{ $tglCetak }}</div>
                <div style="margin-top:2px;">{{ $L['attending_physician'] }},</div>
                <div style="height:55px;"></div>
                <div style="border-top:1px solid #333;display:inline-block;min-width:150px;padding-top:3px;">
                    <strong>{{ $dokter->user->nama }}</strong><br>
                    <span style="font-size:8px;">No. SIP: {{ $dokter->no_sip ?? '-' }}</span>
                </div>
            </td>
        </tr>
    </table>
</div>

<div class="footer">
    {{ $bahasa === 'en' ? 'This report was issued by the EMR System' : 'Resume ini diterbitkan oleh sistem EMR' }} — {{ $klinik->nama }} | {{ $surat->nomor_surat }}
</div>
</body>
</html>

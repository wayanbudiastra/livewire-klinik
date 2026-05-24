<?php

namespace App\Http\Controllers\Laporan;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class LaporanPemeriksaanController extends Controller
{
    public function diagnosa(): View
    {
        return view('laporan.pemeriksaan.index', ['tab' => 'diagnosa']);
    }

    public function tindakan(): View
    {
        return view('laporan.pemeriksaan.index', ['tab' => 'tindakan']);
    }

    public function poli(): View
    {
        return view('laporan.pemeriksaan.index', ['tab' => 'poli']);
    }

    public function dokter(): View
    {
        return view('laporan.pemeriksaan.index', ['tab' => 'dokter']);
    }
}

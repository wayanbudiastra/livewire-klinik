<?php

namespace App\Http\Controllers\Laporan;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class LaporanRegistrasiController extends Controller
{
    public function kunjungan(): View
    {
        return view('laporan.registrasi.index', ['tab' => 'kunjungan']);
    }

    public function batal(): View
    {
        return view('laporan.registrasi.index', ['tab' => 'batal']);
    }

    public function appointment(): View
    {
        return view('laporan.registrasi.index', ['tab' => 'appointment']);
    }

    public function wargaNegara(): View
    {
        return view('laporan.registrasi.index', ['tab' => 'warga-negara']);
    }
}

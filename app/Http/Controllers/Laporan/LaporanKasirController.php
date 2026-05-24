<?php

namespace App\Http\Controllers\Laporan;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class LaporanKasirController extends Controller
{
    public function transaksi(): View
    {
        return view('laporan.kasir.index', ['tab' => 'transaksi']);
    }

    public function cancelBill(): View
    {
        return view('laporan.kasir.index', ['tab' => 'cancel-bill']);
    }

    public function deposit(): View
    {
        return view('laporan.kasir.index', ['tab' => 'deposit']);
    }
}

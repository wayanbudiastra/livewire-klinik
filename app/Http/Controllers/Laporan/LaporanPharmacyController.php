<?php

namespace App\Http\Controllers\Laporan;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class LaporanPharmacyController extends Controller
{
    public function resep(): View
    {
        return view('laporan.pharmacy.index', ['tab' => 'resep']);
    }

    public function fastMoving(): View
    {
        return view('laporan.pharmacy.index', ['tab' => 'fast-moving']);
    }

    public function nilaiInventory(): View
    {
        return view('laporan.pharmacy.index', ['tab' => 'nilai-inventory']);
    }
}

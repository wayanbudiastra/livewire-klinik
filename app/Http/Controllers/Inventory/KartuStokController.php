<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Services\Inventory\KartuStokService;
use Illuminate\Http\Request;

class KartuStokController extends Controller
{
    public function __construct(
        private readonly KartuStokService $service
    ) {}

    public function exportPdf(Request $request)
    {
        $request->validate([
            'barang_id'     => 'required|integer|exists:barang,id',
            'tanggal_mulai' => 'required|date',
            'tanggal_akhir' => 'required|date|after_or_equal:tanggal_mulai',
        ]);

        $data = $this->service->getKartuStok(
            $request->barang_id,
            $request->tanggal_mulai,
            $request->tanggal_akhir,
            $request->tipe ?: null
        );

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('inventory.kartu-stok-pdf', $data)
            ->setPaper('a4', 'landscape');

        $filename = "kartu-stok-{$data['barang']->kode}-{$request->tanggal_mulai}.pdf";
        return $pdf->download($filename);
    }

    public function exportExcel(Request $request)
    {
        $request->validate([
            'barang_id'     => 'required|integer|exists:barang,id',
            'tanggal_mulai' => 'required|date',
            'tanggal_akhir' => 'required|date|after_or_equal:tanggal_mulai',
        ]);

        $data = $this->service->getKartuStok(
            $request->barang_id,
            $request->tanggal_mulai,
            $request->tanggal_akhir,
            $request->tipe ?: null
        );

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\KartuStokExport($data),
            "kartu-stok-{$data['barang']->kode}-{$request->tanggal_mulai}.xlsx"
        );
    }
}

<?php

namespace App\Services\Kasir;

use App\Models\{Invoice, CetakInvoiceLog};
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;

class CetakInvoiceService
{
    public function cetak(Invoice $billing, int $userId): \Illuminate\Http\Response
    {
        return DB::transaction(function () use ($billing, $userId) {
            $nomorCetak = CetakInvoiceLog::where('billing_id', $billing->id)->count() + 1;
            $jenis      = $nomorCetak === 1 ? 'original' : 'copy';

            CetakInvoiceLog::create([
                'billing_id'  => $billing->id,
                'user_id'     => $userId,
                'nomor_cetak' => $nomorCetak,
                'jenis'       => $jenis,
                'ip_address'  => request()->ip(),
            ]);

            $billing->update([
                'sudah_cetak'  => true,
                'jumlah_cetak' => $nomorCetak,
            ]);

            AuditKasirService::log('cetak_invoice', $userId, 'billing', $billing->id, [
                'nomor_invoice' => $billing->nomor_invoice,
                'nomor_cetak'   => $nomorCetak,
                'jenis'         => $jenis,
            ]);

            $billing->load([
                'kunjungan.pasien',
                'kunjungan.dokter',
                'kunjungan.poli',
                'pembayaranSplit',
                'items',
            ]);

            $pdf = Pdf::loadView('kasir.invoice.template', [
                'billing'      => $billing,
                'jenis'        => strtoupper($jenis),
                'nomorCetak'   => $nomorCetak,
                'dicetak_oleh' => auth()->user()->nama,
                'dicetak_pada' => now()->format('d/m/Y H:i'),
            ])->setPaper('a5', 'portrait');

            $filename = "Invoice-{$billing->nomor_invoice}-{$jenis}.pdf";

            return $pdf->download($filename);
        });
    }
}

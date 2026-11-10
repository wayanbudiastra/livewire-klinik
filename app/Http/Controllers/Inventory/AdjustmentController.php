<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\AdjustmentStok;
use Illuminate\Support\Facades\Storage;

class AdjustmentController extends Controller
{
    public function download(AdjustmentStok $adjustment)
    {
        // Lapis kedua selain middleware route (defense in depth).
        abort_unless(auth()->user()?->can('obat.view'), 403);

        abort_unless($adjustment->file_attachment, 404);
        abort_unless(Storage::disk('local')->exists($adjustment->file_attachment), 404);

        $ext = pathinfo($adjustment->file_attachment, PATHINFO_EXTENSION);

        return Storage::disk('local')->download(
            $adjustment->file_attachment,
            "bukti-{$adjustment->nomor_adjustment}.{$ext}"
        );
    }
}

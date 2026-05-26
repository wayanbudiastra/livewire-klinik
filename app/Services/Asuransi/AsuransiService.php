<?php

namespace App\Services\Asuransi;

use App\Models\Asuransi;
use Illuminate\Support\Str;

class AsuransiService
{
    public function generateKode(): string
    {
        $last = Asuransi::orderByDesc('kode')->value('kode');
        $seq  = $last ? (int) substr($last, -3) + 1 : 1;
        return 'ASR-' . str_pad($seq, 3, '0', STR_PAD_LEFT);
    }

    public function create(array $data): Asuransi
    {
        return Asuransi::create($data);
    }

    public function update(Asuransi $asuransi, array $data): Asuransi
    {
        $asuransi->update($data);
        return $asuransi->fresh();
    }

    public function toggleActive(Asuransi $asuransi): Asuransi
    {
        $asuransi->update(['is_active' => !$asuransi->is_active]);
        return $asuransi->fresh();
    }
}

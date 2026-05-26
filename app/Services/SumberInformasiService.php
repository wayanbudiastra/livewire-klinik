<?php

namespace App\Services;

use App\Models\SumberInformasi;
use Illuminate\Support\Str;

class SumberInformasiService
{
    public function getActiveOptions()
    {
        return SumberInformasi::active()->get();
    }

    public function create(array $data): SumberInformasi
    {
        if (empty($data['kode'])) {
            $data['kode'] = Str::slug($data['nama'], '_');
        }
        return SumberInformasi::create($data);
    }

    public function update(SumberInformasi $sumber, array $data): SumberInformasi
    {
        $sumber->update($data);
        return $sumber->fresh();
    }

    public function toggleActive(SumberInformasi $sumber): SumberInformasi
    {
        if ($sumber->kode === 'lainnya' && $sumber->is_active) {
            throw new \RuntimeException('Sumber "Lainnya" tidak dapat dinonaktifkan.');
        }
        $sumber->update(['is_active' => !$sumber->is_active]);
        return $sumber;
    }
}

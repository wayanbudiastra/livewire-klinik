<?php

namespace App\Services\Inventory;

use App\Models\Supplier;

class SupplierService
{
    public function generateKode(): string
    {
        $last = Supplier::orderByDesc('id')->value('kode');
        $seq  = $last ? (int) ltrim(str_replace('SUP-', '', $last), '0') + 1 : 1;
        return 'SUP-' . str_pad($seq, 3, '0', STR_PAD_LEFT);
    }

    public function create(array $data): Supplier
    {
        $supplier = Supplier::create($data);

        activity('inventory')
            ->performedOn($supplier)
            ->causedBy(auth()->user())
            ->log('Supplier baru ditambahkan');

        return $supplier;
    }

    public function update(Supplier $supplier, array $data): Supplier
    {
        $supplier->update($data);

        activity('inventory')
            ->performedOn($supplier)
            ->causedBy(auth()->user())
            ->log('Data supplier diupdate');

        return $supplier->fresh();
    }

    public function toggleAktif(int $id): Supplier
    {
        $supplier = Supplier::findOrFail($id);
        $supplier->update(['is_active' => ! $supplier->is_active]);
        return $supplier;
    }
}

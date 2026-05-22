<?php

namespace App\Http\Requests\Farmasi;

use Illuminate\Foundation\Http\FormRequest;

class StoreObatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('obat.create');
    }

    public function rules(): array
    {
        return [
            'kode'           => ['required', 'string', 'unique:obat,kode'],
            'barcode'        => ['nullable', 'string'],
            'nama'           => ['required', 'string', 'min:3'],
            'generik'        => ['nullable', 'string'],
            'jenis_barang'   => ['required', 'in:obat,alkes'],
            'is_paten'       => ['boolean'],
            'satuan'         => ['required', 'string'],
            'satuan_besar_id'=> ['nullable', 'integer', 'exists:satuan,id'],
            'satuan_kecil_id'=> ['nullable', 'integer', 'exists:satuan,id'],
            'konversi'       => ['required', 'integer', 'min:1'],
            'stok'           => ['required', 'integer', 'min:0'],
            'harga'          => ['required', 'numeric', 'min:0'],
            'harga_beli'     => ['nullable', 'numeric', 'min:0'],
            'harga_bpjs'     => ['nullable', 'numeric', 'min:0'],
            'kategori'       => ['nullable', 'string'],
            'expired_date'   => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'kode.unique' => 'Kode obat sudah digunakan.',
            'nama.min'    => 'Nama minimal 3 karakter.',
        ];
    }
}

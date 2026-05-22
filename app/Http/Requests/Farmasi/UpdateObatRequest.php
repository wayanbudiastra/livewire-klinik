<?php

namespace App\Http\Requests\Farmasi;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateObatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('obat.edit');
    }

    public function rules(): array
    {
        $id = $this->input('obat_id');

        return [
            'kode'           => ['required', 'string', Rule::unique('obat', 'kode')->ignore($id)],
            'barcode'        => ['nullable', 'string'],
            'nama'           => ['required', 'string', 'min:3'],
            'generik'        => ['nullable', 'string'],
            'jenis_barang'   => ['required', 'in:obat,alkes'],
            'is_paten'       => ['boolean'],
            'satuan'         => ['required', 'string'],
            'satuan_besar_id'=> ['nullable', 'integer', 'exists:satuan,id'],
            'satuan_kecil_id'=> ['nullable', 'integer', 'exists:satuan,id'],
            'konversi'       => ['required', 'integer', 'min:1'],
            'harga'          => ['required', 'numeric', 'min:0'],
            'harga_beli'     => ['nullable', 'numeric', 'min:0'],
            'harga_bpjs'     => ['nullable', 'numeric', 'min:0'],
            'kategori'       => ['nullable', 'string'],
            'expired_date'   => ['nullable', 'date'],
        ];
    }
}

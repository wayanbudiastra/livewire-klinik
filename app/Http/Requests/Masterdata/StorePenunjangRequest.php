<?php

namespace App\Http\Requests\Masterdata;

use Illuminate\Foundation\Http\FormRequest;

class StorePenunjangRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('masterdata.create');
    }

    public function rules(): array
    {
        return [
            'kode'         => ['required', 'string', 'min:2', 'unique:item_penunjang,kode'],
            'nama'         => ['required', 'string', 'min:3'],
            'kategori'     => ['required', 'in:lab,radiologi'],
            'tarif'        => ['required', 'numeric', 'min:0'],
            'tarif_bpjs'   => ['nullable', 'numeric', 'min:0'],
            'deskripsi'    => ['nullable', 'string'],
            'satuan_waktu' => ['nullable', 'string'],
        ];
    }
}

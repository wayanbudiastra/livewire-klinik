<?php

namespace App\Http\Requests\Masterdata;

use Illuminate\Foundation\Http\FormRequest;

class StorePeralatanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('masterdata.create');
    }

    public function rules(): array
    {
        return [
            'kode'       => ['required', 'string', 'unique:peralatan_medis,kode'],
            'nama'       => ['required', 'string', 'min:3'],
            'merk'       => ['nullable', 'string'],
            'nomor_seri' => ['nullable', 'string', 'unique:peralatan_medis,nomor_seri'],
            'deskripsi'  => ['nullable', 'string'],
        ];
    }
}

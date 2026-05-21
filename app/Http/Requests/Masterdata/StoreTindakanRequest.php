<?php

namespace App\Http\Requests\Masterdata;

use Illuminate\Foundation\Http\FormRequest;

class StoreTindakanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('masterdata.create');
    }

    public function rules(): array
    {
        return [
            'kode'       => ['required', 'string', 'min:2', 'unique:master_tindakan,kode'],
            'nama'       => ['required', 'string', 'min:3'],
            'deskripsi'  => ['nullable', 'string'],
            'tarif'      => ['required', 'numeric', 'min:0'],
            'tarif_bpjs' => ['nullable', 'numeric', 'min:0'],
            'poli_ids'   => ['required', 'array', 'min:1'],
            'poli_ids.*' => ['integer', 'exists:poli,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'poli_ids.required' => 'Tindakan wajib dipetakan ke minimal satu Poli.',
            'poli_ids.min'      => 'Tindakan wajib dipetakan ke minimal satu Poli.',
            'kode.unique'       => 'Kode tindakan sudah digunakan.',
        ];
    }
}

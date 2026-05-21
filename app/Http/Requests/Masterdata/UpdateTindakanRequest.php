<?php

namespace App\Http\Requests\Masterdata;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTindakanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('masterdata.edit');
    }

    public function rules(): array
    {
        $id = $this->input('tindakan_id');
        return [
            'kode'       => ['required', 'string', 'min:2', Rule::unique('master_tindakan', 'kode')->ignore($id)],
            'nama'       => ['required', 'string', 'min:3'],
            'deskripsi'  => ['nullable', 'string'],
            'tarif'      => ['required', 'numeric', 'min:0'],
            'tarif_bpjs' => ['nullable', 'numeric', 'min:0'],
            'poli_ids'   => ['required', 'array', 'min:1'],
            'poli_ids.*' => ['integer', 'exists:poli,id'],
        ];
    }
}

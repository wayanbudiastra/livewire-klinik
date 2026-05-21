<?php

namespace App\Http\Requests\Dokter;

use Illuminate\Foundation\Http\FormRequest;

class StoreSharingFeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('masterdata.edit');
    }

    public function rules(): array
    {
        return [
            'fees'              => ['required', 'array'],
            'fees.tindakan'     => ['required', 'numeric', 'min:0', 'max:100'],
            'fees.lab'          => ['required', 'numeric', 'min:0', 'max:100'],
            'fees.radiologi'    => ['required', 'numeric', 'min:0', 'max:100'],
            'fees.peralatan'    => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'fees.*.min' => 'Persentase minimal 0%.',
            'fees.*.max' => 'Persentase maksimal 100%.',
        ];
    }
}

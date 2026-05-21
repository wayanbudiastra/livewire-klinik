<?php

namespace App\Http\Requests\Dokter;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDokterProfilRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('masterdata.edit');
    }

    public function rules(): array
    {
        $dokterId = $this->input('dokter_id');

        return [
            'nik'             => ['nullable', 'string', 'size:16', 'regex:/^\d{16}$/',
                                  Rule::unique('dokter', 'nik')->ignore($dokterId)],
            'no_sip'          => ['nullable', 'string', 'min:5', 'max:50',
                                  Rule::unique('dokter', 'no_sip')->ignore($dokterId)],
            'tgl_expired_sip' => ['nullable', 'date'],
            'spesialisasi'    => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'nik.size'      => 'NIK harus tepat 16 digit.',
            'nik.regex'     => 'NIK hanya boleh berisi angka.',
            'nik.unique'    => 'NIK sudah digunakan dokter lain.',
            'no_sip.unique' => 'Nomor SIP sudah digunakan dokter lain.',
        ];
    }
}

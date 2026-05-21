<?php

namespace App\Http\Requests\Pasien;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePasienRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('pasien.edit');
    }

    public function rules(): array
    {
        $id = $this->input('pasien_id');

        return [
            'nama'          => ['required', 'string', 'min:3', 'max:100'],
            'tempat_lahir'  => ['required', 'string', 'min:2', 'max:100'],
            'tanggal_lahir' => ['required', 'date', 'before_or_equal:today'],
            'jenis_kelamin' => ['required', 'in:L,P'],
            'nik'           => ['nullable', 'string', 'size:16', 'regex:/^\d{16}$/',
                                Rule::unique('pasien', 'nik')->ignore($id)],
            'no_bpjs'       => ['nullable', 'string', 'size:13', 'regex:/^\d{13}$/',
                                Rule::unique('pasien', 'no_bpjs')->ignore($id)],
            'no_paspor'     => ['nullable', 'string', 'min:5', 'max:20',
                                Rule::unique('pasien', 'no_paspor')->ignore($id)],
            'negara_asal'   => ['nullable', 'string', 'max:100'],
            'alamat'        => ['required', 'string', 'min:10', 'max:500'],
            'telepon'       => ['required', 'string', 'regex:/^(\+62|62|0)[0-9]{8,13}$/'],
            'email'         => ['nullable', 'email'],
            'golongan_darah'=> ['nullable', 'in:A,B,AB,O,tidak_diketahui'],
            'alergi'        => ['nullable', 'string', 'max:1000'],
            'no_asuransi'   => ['nullable', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'nik.size'      => 'NIK harus tepat 16 digit.',
            'nik.regex'     => 'NIK hanya boleh berisi angka.',
            'telepon.regex' => 'Format telepon tidak valid (08xx / +62xx).',
            'alamat.min'    => 'Alamat minimal 10 karakter.',
        ];
    }
}

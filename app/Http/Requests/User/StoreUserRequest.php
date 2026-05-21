<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('user.create');
    }

    public function rules(): array
    {
        return [
            'nama'     => ['required', 'string', 'min:3', 'max:100'],
            'email'    => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role'     => ['required', 'string', 'exists:roles,name'],
            'nip'      => ['nullable', 'string', 'max:30', 'unique:users,nip'],
            'telepon'  => ['nullable', 'string', 'max:20'],
        ];
    }

    public function messages(): array
    {
        return [
            'nama.min'       => 'Nama minimal 3 karakter.',
            'email.unique'   => 'Email sudah terdaftar.',
            'password.min'   => 'Password minimal 8 karakter.',
            'role.exists'    => 'Role tidak valid.',
            'nip.unique'     => 'NIP sudah digunakan.',
        ];
    }
}

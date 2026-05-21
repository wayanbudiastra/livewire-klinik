<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('user.edit');
    }

    public function rules(): array
    {
        $userId = $this->input('user_id');

        return [
            'nama'    => ['required', 'string', 'min:3', 'max:100'],
            'email'   => ['required', 'email', Rule::unique('users', 'email')->ignore($userId)],
            'role'    => ['required', 'string', 'exists:roles,name'],
            'nip'     => ['nullable', 'string', 'max:30', Rule::unique('users', 'nip')->ignore($userId)],
            'telepon' => ['nullable', 'string', 'max:20'],
        ];
    }

    public function messages(): array
    {
        return [
            'nama.min'    => 'Nama minimal 3 karakter.',
            'email.unique'=> 'Email sudah digunakan user lain.',
            'role.exists' => 'Role tidak valid.',
            'nip.unique'  => 'NIP sudah digunakan user lain.',
        ];
    }
}

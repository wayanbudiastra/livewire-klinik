<?php

namespace App\Livewire\Pengaturan\User;

use App\Models\User;
use App\Services\UserService;
use Livewire\Component;

class ResetPasswordModal extends Component
{
    public bool   $showModal = false;
    public ?int   $userId    = null;
    public string $userName  = '';

    public string $new_password              = '';
    public string $new_password_confirmation = '';

    protected function rules(): array
    {
        return [
            'new_password'              => 'required|string|min:8|confirmed',
            'new_password_confirmation' => 'required',
        ];
    }

    protected function messages(): array
    {
        return [
            'new_password.min'       => 'Password minimal 8 karakter.',
            'new_password.confirmed' => 'Konfirmasi password tidak cocok.',
        ];
    }

    public function open(int $userId): void
    {
        $user = User::findOrFail($userId);
        $this->authorize('resetPassword', $user);

        $this->userId   = $userId;
        $this->userName = $user->nama;
        $this->new_password              = '';
        $this->new_password_confirmation = '';
        $this->showModal = true;
        $this->resetValidation();
    }

    public function save(UserService $service): void
    {
        $this->validate();
        $service->resetPassword($this->userId, $this->new_password);

        $this->showModal = false;
        $this->dispatch('password-reset');
        $this->dispatch('notify', [
            'type'    => 'success',
            'message' => "Password {$this->userName} berhasil direset.",
        ]);
    }

    public function render()
    {
        return view('livewire.pengaturan.user.reset-password-modal');
    }
}

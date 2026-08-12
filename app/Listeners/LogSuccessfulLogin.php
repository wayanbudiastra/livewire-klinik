<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;

class LogSuccessfulLogin
{
    /**
     * Catat setiap login berhasil ke activity_log (log_name = 'login').
     * Dipakai untuk halaman "Log Login User" (Pengaturan, khusus super_admin).
     */
    public function handle(Login $event): void
    {
        activity('login')
            ->causedBy($event->user)
            ->withProperties([
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ])
            ->log('Login berhasil');
    }
}

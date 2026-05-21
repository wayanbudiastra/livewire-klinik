<?php

namespace App\Providers;

use App\Models\User;
use App\Policies\UserPolicy;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\UserRepository;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    protected $policies = [
        User::class => UserPolicy::class,
    ];

    public function register(): void
    {
        // Bind repository interface ke implementasi Eloquent
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
    }

    public function boot(): void
    {
        Paginator::useTailwind();

        // Super Admin bypass semua gate check
        Gate::before(function (User $user, string $_ability) {
            if ($user->hasRole('super_admin')) {
                return true;
            }
        });

        // Register policies
        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }
    }
}

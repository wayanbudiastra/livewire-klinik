# Master Data — User Management & Role Access Control
**Versi:** 2.0.0
**Tech Stack:** Laravel 12 · Livewire 3 · Spatie Permission · Tailwind CSS
**Scope:** Super Admin Panel — User Management & RBAC
**Changelog v2.0.0:** Rewrite penuh ke Laravel 12 + Livewire 3 (migrasi dari Next.js)

---

## Daftar Isi

1. [Definisi Role & Permission](#1-definisi-role--permission)
2. [Struktur Folder — Skala Besar](#2-struktur-folder--skala-besar)
3. [Migration & Model](#3-migration--model)
4. [Repository Pattern](#4-repository-pattern)
5. [Service Layer](#5-service-layer)
6. [Form Request Validation](#6-form-request-validation)
7. [Policy — Otorisasi](#7-policy--otorisasi)
8. [Livewire Components](#8-livewire-components)
9. [Routes](#9-routes)
10. [Blade Views](#10-blade-views)
11. [Seeder & Data Awal](#11-seeder--data-awal)
12. [Matrix Hak Akses per Role](#12-matrix-hak-akses-per-role)

---

## 1. Definisi Role & Permission

### 1.1 Role

| Kode Role | Label UI | Deskripsi |
|-----------|----------|-----------|
| `super_admin` | Super Admin | Full access + manajemen user & sistem |
| `admin` | Admin | Operasional harian, tanpa konfigurasi sistem |
| `dokter` | Dokter | SOAP note, diagnosa, resep elektronik |
| `perawat` | Perawat | Asesmen awal, tanda vital, tindakan |
| `apoteker` | Apoteker | Validasi resep, dispensing, stok obat |
| `kasir` | Kasir | Billing, pembayaran, invoice |
| `rekam_medis` | Rekam Medis | Arsip rekam medis, laporan |
| `pasien` | Pasien | Portal pasien (riwayat sendiri) |

> `super_admin` mendapat akses penuh via `Gate::before()` — tidak perlu assign permission satu per satu.

### 1.2 Permission Granular

```
# Pasien
pasien.view | pasien.create | pasien.edit | pasien.delete

# Kunjungan & Antrean
kunjungan.view | kunjungan.create | kunjungan.edit | kunjungan.delete

# Klinis
asesmen.view | asesmen.create | asesmen.edit
soap.view    | soap.create    | soap.edit
resep.view   | resep.create   | resep.edit
tindakan.view | tindakan.create

# Farmasi
obat.view | obat.create | obat.edit | obat.delete

# Keuangan
billing.view    | billing.create    | billing.edit
pembayaran.view | pembayaran.create

# Laporan
laporan.view | laporan.keuangan | laporan.farmasi

# Rekam Medis
rekammedis.view | rekammedis.create | rekammedis.edit

# Sistem (Super Admin only)
user.view | user.create | user.edit | user.delete
pengaturan.view | pengaturan.edit
masterdata.view | masterdata.create | masterdata.edit | masterdata.delete
```

---

## 2. Struktur Folder — Skala Besar

```
app/
├── Http/
│   ├── Controllers/
│   │   └── Pengaturan/
│   │       └── PengaturanController.php       # Thin controller, delegasi ke Livewire
│   │
│   ├── Requests/
│   │   └── User/
│   │       ├── StoreUserRequest.php
│   │       ├── UpdateUserRequest.php
│   │       └── ResetPasswordRequest.php
│   │
│   └── Middleware/
│       └── EnsureUserIsActive.php             # Blokir user nonaktif
│
├── Livewire/
│   └── Pengaturan/
│       └── User/
│           ├── UserTable.php                  # Tabel + search + filter + pagination
│           ├── UserForm.php                   # Modal create/edit
│           ├── UserDetail.php                 # Slide-over detail user
│           └── ResetPasswordModal.php         # Modal reset password
│
├── Models/
│   └── User.php
│
├── Policies/
│   └── UserPolicy.php
│
├── Repositories/
│   ├── Contracts/
│   │   └── UserRepositoryInterface.php        # Kontrak abstrak
│   └── UserRepository.php                     # Implementasi Eloquent
│
├── Services/
│   └── UserService.php                        # Business logic
│
└── Providers/
    ├── AppServiceProvider.php                 # Bind interface ke implementasi
    └── AuthServiceProvider.php               # Register policies & Gate::before

resources/views/
└── pengaturan/
    └── user/
        └── index.blade.php                    # Host Livewire components

database/
├── migrations/
│   └── 0001_01_01_000000_create_users_table.php
└── seeders/
    ├── RolePermissionSeeder.php
    └── UserSeeder.php
```

---

## 3. Migration & Model

### 3.1 Migration Users

```php
// database/migrations/0001_01_01_000000_create_users_table.php

Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('nama');
    $table->string('email')->unique();
    $table->string('password');
    $table->string('nip')->nullable()->unique();
    $table->string('telepon')->nullable();
    $table->string('foto')->nullable();
    $table->boolean('is_active')->default(true);
    $table->timestamp('last_login_at')->nullable();
    $table->timestamp('password_changed_at')->nullable(); // untuk invalidasi session
    $table->rememberToken();
    $table->timestamps();
    $table->softDeletes();                               // hapus soft delete-friendly
});
```

### 3.2 Model User

```php
// app/Models/User.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles, SoftDeletes;

    protected $fillable = [
        'nama', 'email', 'password',
        'nip', 'telepon', 'foto',
        'is_active', 'last_login_at', 'password_changed_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'is_active'           => 'boolean',
            'last_login_at'       => 'datetime',
            'password_changed_at' => 'datetime',
            'password'            => 'hashed',
        ];
    }

    // Relasi profil dokter (opsional, jika role = dokter)
    public function dokter(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Dokter::class);
    }

    // Scope untuk filter aktif/nonaktif
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('nama',  'like', "%{$term}%")
              ->orWhere('email', 'like', "%{$term}%")
              ->orWhere('nip',   'like', "%{$term}%");
        });
    }
}
```

---

## 4. Repository Pattern

### 4.1 Interface (Kontrak)

```php
// app/Repositories/Contracts/UserRepositoryInterface.php

namespace App\Repositories\Contracts;

use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

interface UserRepositoryInterface
{
    public function paginate(array $filters = [], int $perPage = 20): LengthAwarePaginator;
    public function findById(int $id): ?User;
    public function findByEmail(string $email): ?User;
    public function create(array $data): User;
    public function update(int $id, array $data): User;
    public function toggleActive(int $id, bool $state): User;
    public function resetPassword(int $id, string $hashedPassword): void;
    public function delete(int $id): void;
}
```

### 4.2 Implementasi Eloquent

```php
// app/Repositories/UserRepository.php

namespace App\Repositories;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class UserRepository implements UserRepositoryInterface
{
    public function paginate(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return User::query()
            ->with('roles')
            ->when($filters['search'] ?? null, fn($q, $s) => $q->search($s))
            ->when($filters['role']   ?? null, fn($q, $r) => $q->role($r))
            ->when(isset($filters['is_active']), fn($q) => $q->where('is_active', $filters['is_active']))
            ->orderBy($filters['sort_by'] ?? 'created_at', $filters['sort_dir'] ?? 'desc')
            ->paginate($perPage);
    }

    public function findById(int $id): ?User
    {
        return User::with('roles', 'dokter')->find($id);
    }

    public function findByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    public function create(array $data): User
    {
        return User::create($data);
    }

    public function update(int $id, array $data): User
    {
        $user = User::findOrFail($id);
        $user->update($data);
        return $user->fresh('roles');
    }

    public function toggleActive(int $id, bool $state): User
    {
        $user = User::findOrFail($id);
        $user->update(['is_active' => $state]);
        return $user;
    }

    public function resetPassword(int $id, string $hashedPassword): void
    {
        User::where('id', $id)->update([
            'password'            => $hashedPassword,
            'password_changed_at' => Carbon::now(),
        ]);
    }

    public function delete(int $id): void
    {
        User::findOrFail($id)->delete(); // soft delete
    }
}
```

### 4.3 Bind di AppServiceProvider

```php
// app/Providers/AppServiceProvider.php

use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\UserRepository;

public function register(): void
{
    $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
}
```

---

## 5. Service Layer

```php
// app/Services/UserService.php

namespace App\Services;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Facades\Activity;

class UserService
{
    public function __construct(
        private readonly UserRepositoryInterface $repo
    ) {}

    public function paginate(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->repo->paginate($filters, $perPage);
    }

    public function create(array $data, string $role): User
    {
        if ($this->repo->findByEmail($data['email'])) {
            throw ValidationException::withMessages([
                'email' => 'Email sudah digunakan oleh user lain.',
            ]);
        }

        $data['password'] = Hash::make($data['password']);
        $user = $this->repo->create($data);
        $user->assignRole($role);

        activity('user')->performedOn($user)
            ->causedBy(auth()->user())
            ->withProperties(['role' => $role])
            ->log('User baru dibuat');

        return $user;
    }

    public function update(int $id, array $data, string $role): User
    {
        // Cek duplikat email (kecuali user sendiri)
        $existing = $this->repo->findByEmail($data['email'] ?? '');
        if ($existing && $existing->id !== $id) {
            throw ValidationException::withMessages([
                'email' => 'Email sudah digunakan oleh user lain.',
            ]);
        }

        $user = $this->repo->update($id, $data);
        $user->syncRoles([$role]);

        activity('user')->performedOn($user)
            ->causedBy(auth()->user())
            ->log('Data user diupdate');

        return $user;
    }

    public function toggleActive(int $id, bool $state): User
    {
        $user = $this->repo->toggleActive($id, $state);

        activity('user')->performedOn($user)
            ->causedBy(auth()->user())
            ->log($state ? 'User diaktifkan' : 'User dinonaktifkan');

        return $user;
    }

    public function resetPassword(int $id, string $newPassword): void
    {
        $user = $this->repo->findById($id);

        if (!$user) {
            throw ValidationException::withMessages(['id' => 'User tidak ditemukan.']);
        }

        // Super Admin tidak bisa di-reset via panel
        if ($user->hasRole('super_admin') && auth()->id() !== $user->id) {
            throw ValidationException::withMessages([
                'password' => 'Password Super Admin tidak dapat direset melalui panel ini.',
            ]);
        }

        $this->repo->resetPassword($id, Hash::make($newPassword));

        activity('user')->performedOn($user)
            ->causedBy(auth()->user())
            ->log('Password direset oleh admin');
    }

    public function delete(int $id): void
    {
        $user = $this->repo->findById($id);

        // Tidak boleh hapus diri sendiri
        if ($user?->id === auth()->id()) {
            throw ValidationException::withMessages([
                'id' => 'Tidak dapat menghapus akun sendiri.',
            ]);
        }

        // Tidak boleh hapus super_admin lain
        if ($user?->hasRole('super_admin')) {
            throw ValidationException::withMessages([
                'id' => 'Akun Super Admin tidak dapat dihapus.',
            ]);
        }

        activity('user')->performedOn($user)
            ->causedBy(auth()->user())
            ->log('User dihapus');

        $this->repo->delete($id);
    }
}
```

---

## 6. Form Request Validation

### 6.1 StoreUserRequest

```php
// app/Http/Requests/User/StoreUserRequest.php

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
            'nama'        => ['required', 'string', 'min:3', 'max:100'],
            'email'       => ['required', 'email', 'unique:users,email'],
            'password'    => ['required', 'string', 'min:8', 'confirmed',
                              'regex:/[A-Z]/', 'regex:/[0-9]/'],
            'role'        => ['required', 'string', 'exists:roles,name'],
            'nip'         => ['nullable', 'string', 'unique:users,nip'],
            'telepon'     => ['nullable', 'string', 'max:20'],
        ];
    }

    public function messages(): array
    {
        return [
            'password.regex' => 'Password harus mengandung huruf kapital dan angka.',
        ];
    }
}
```

### 6.2 UpdateUserRequest

```php
// app/Http/Requests/User/UpdateUserRequest.php

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
        $userId = $this->route('user') ?? $this->input('user_id');

        return [
            'nama'    => ['required', 'string', 'min:3', 'max:100'],
            'email'   => ['required', 'email', Rule::unique('users', 'email')->ignore($userId)],
            'role'    => ['required', 'string', 'exists:roles,name'],
            'nip'     => ['nullable', 'string', Rule::unique('users', 'nip')->ignore($userId)],
            'telepon' => ['nullable', 'string', 'max:20'],
        ];
    }
}
```

### 6.3 ResetPasswordRequest

```php
// app/Http/Requests/User/ResetPasswordRequest.php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasRole('super_admin');
    }

    public function rules(): array
    {
        return [
            'new_password'              => [
                'required', 'string', 'min:8', 'confirmed',
                'regex:/[A-Z]/',       // minimal 1 huruf kapital
                'regex:/[0-9]/',       // minimal 1 angka
                'regex:/[^A-Za-z0-9]/', // minimal 1 karakter spesial
            ],
            'new_password_confirmation' => ['required'],
        ];
    }

    public function messages(): array
    {
        return [
            'new_password.regex' => 'Password harus mengandung huruf kapital, angka, dan karakter spesial.',
        ];
    }
}
```

---

## 7. Policy — Otorisasi

```php
// app/Policies/UserPolicy.php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('user.view');
    }

    public function create(User $user): bool
    {
        return $user->can('user.create');
    }

    public function update(User $user, User $target): bool
    {
        // Tidak bisa edit super_admin kecuali dirinya sendiri
        if ($target->hasRole('super_admin') && $user->id !== $target->id) {
            return false;
        }
        return $user->can('user.edit');
    }

    public function delete(User $user, User $target): bool
    {
        // Tidak bisa hapus diri sendiri atau super_admin lain
        if ($user->id === $target->id || $target->hasRole('super_admin')) {
            return false;
        }
        return $user->can('user.delete');
    }

    public function resetPassword(User $user, User $target): bool
    {
        if ($target->hasRole('super_admin') && $user->id !== $target->id) {
            return false;
        }
        return $user->hasRole('super_admin');
    }
}
```

### Daftarkan di AuthServiceProvider

```php
// app/Providers/AuthServiceProvider.php

use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;

public function boot(): void
{
    // Super Admin bypass semua gate
    Gate::before(function (User $user) {
        if ($user->hasRole('super_admin')) return true;
    });

    Gate::policy(User::class, UserPolicy::class);
}
```

---

## 8. Livewire Components

### 8.1 UserTable — Tabel Utama

```php
// app/Livewire/Pengaturan/User/UserTable.php

namespace App\Livewire\Pengaturan\User;

use App\Services\UserService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class UserTable extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $filterRole = '';

    #[Url]
    public string $filterStatus = '';

    #[Url]
    public string $sortBy = 'created_at';

    #[Url]
    public string $sortDir = 'desc';

    public int $perPage = 15;

    // Reset page saat filter berubah
    public function updatingSearch(): void    { $this->resetPage(); }
    public function updatingFilterRole(): void { $this->resetPage(); }
    public function updatingFilterStatus(): void { $this->resetPage(); }

    public function sort(string $column): void
    {
        $this->sortDir = ($this->sortBy === $column && $this->sortDir === 'asc') ? 'desc' : 'asc';
        $this->sortBy  = $column;
        $this->resetPage();
    }

    #[Computed]
    public function users()
    {
        return app(UserService::class)->paginate([
            'search'    => $this->search,
            'role'      => $this->filterRole,
            'is_active' => $this->filterStatus !== '' ? (bool)$this->filterStatus : null,
            'sort_by'   => $this->sortBy,
            'sort_dir'  => $this->sortDir,
        ], $this->perPage);
    }

    public function toggleActive(int $userId, bool $state): void
    {
        $this->authorize('update', \App\Models\User::findOrFail($userId));

        app(UserService::class)->toggleActive($userId, $state);

        $this->dispatch('notify', [
            'type'    => 'success',
            'message' => $state ? 'User berhasil diaktifkan.' : 'User berhasil dinonaktifkan.',
        ]);
    }

    #[On('user-saved')]
    #[On('user-deleted')]
    #[On('password-reset')]
    public function refresh(): void
    {
        unset($this->users); // reset computed property
    }

    public function render()
    {
        return view('livewire.pengaturan.user.user-table');
    }
}
```

### 8.2 UserForm — Modal Create/Edit

```php
// app/Livewire/Pengaturan/User/UserForm.php

namespace App\Livewire\Pengaturan\User;

use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Models\User;
use App\Services\UserService;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Spatie\Permission\Models\Role;

class UserForm extends Component
{
    public bool $showModal = false;
    public ?int $userId    = null;
    public bool $isEdit    = false;

    public string $nama     = '';
    public string $email    = '';
    public string $password = '';
    public string $password_confirmation = '';
    public string $role     = '';
    public string $nip      = '';
    public string $telepon  = '';
    public bool   $is_active = true;

    public function openCreate(): void
    {
        $this->authorize('create', User::class);
        $this->reset(['userId', 'nama', 'email', 'password', 'password_confirmation',
                      'role', 'nip', 'telepon', 'is_active']);
        $this->isEdit    = false;
        $this->showModal = true;
    }

    public function openEdit(int $userId): void
    {
        $user = User::with('roles')->findOrFail($userId);
        $this->authorize('update', $user);

        $this->userId   = $userId;
        $this->nama     = $user->nama;
        $this->email    = $user->email;
        $this->nip      = $user->nip    ?? '';
        $this->telepon  = $user->telepon ?? '';
        $this->role     = $user->roles->first()?->name ?? '';
        $this->is_active = $user->is_active;
        $this->isEdit   = true;
        $this->showModal = true;
    }

    public function save(UserService $service): void
    {
        $rules = $this->isEdit
            ? (new UpdateUserRequest())->rules()
            : (new StoreUserRequest())->rules();

        $this->validate($rules);

        $data = [
            'nama'     => $this->nama,
            'email'    => $this->email,
            'nip'      => $this->nip      ?: null,
            'telepon'  => $this->telepon  ?: null,
            'is_active' => $this->is_active,
        ];

        if ($this->isEdit) {
            $service->update($this->userId, $data, $this->role);
        } else {
            $data['password'] = $this->password;
            $service->create($data, $this->role);
        }

        $this->showModal = false;
        $this->dispatch('user-saved');
        $this->dispatch('notify', [
            'type'    => 'success',
            'message' => $this->isEdit ? 'Data user berhasil diupdate.' : 'User baru berhasil ditambahkan.',
        ]);
    }

    public function getRolesProperty()
    {
        // Super Admin tidak bisa di-assign dari form biasa
        return Role::whereNot('name', 'super_admin')
            ->orderBy('name')
            ->pluck('name');
    }

    public function render()
    {
        return view('livewire.pengaturan.user.user-form');
    }
}
```

### 8.3 ResetPasswordModal

```php
// app/Livewire/Pengaturan/User/ResetPasswordModal.php

namespace App\Livewire\Pengaturan\User;

use App\Models\User;
use App\Services\UserService;
use Livewire\Attributes\Rule;
use Livewire\Component;

class ResetPasswordModal extends Component
{
    public bool  $showModal = false;
    public ?int  $userId    = null;
    public string $userName = '';

    #[Rule(['required', 'string', 'min:8', 'confirmed',
            'regex:/[A-Z]/', 'regex:/[0-9]/', 'regex:/[^A-Za-z0-9]/'],
           message: 'Password minimal 8 karakter, mengandung huruf kapital, angka, dan simbol.')]
    public string $new_password = '';

    #[Rule('required')]
    public string $new_password_confirmation = '';

    public function open(int $userId): void
    {
        $user = User::findOrFail($userId);
        $this->authorize('resetPassword', $user);

        $this->userId    = $userId;
        $this->userName  = $user->nama;
        $this->new_password = '';
        $this->new_password_confirmation = '';
        $this->showModal = true;
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
```

---

## 9. Routes

```php
// routes/web.php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'active'])->group(function () {

    Route::prefix('pengaturan')->name('pengaturan.')->group(function () {

        // User Management — hanya Super Admin
        Route::middleware('permission:user.view')->group(function () {
            Route::get('/pengguna', fn() => view('pengaturan.user.index'))
                 ->name('pengguna');
        });

        // Master Data
        Route::middleware('permission:masterdata.view')->group(function () {
            Route::get('/poli',      fn() => view('pengaturan.masterdata.poli'))     ->name('poli');
            Route::get('/tindakan',  fn() => view('pengaturan.masterdata.tindakan'))  ->name('tindakan');
            Route::get('/kamar',     fn() => view('pengaturan.masterdata.kamar'))     ->name('kamar');
            Route::get('/obat',      fn() => view('pengaturan.masterdata.obat'))      ->name('obat');
        });

        // Konfigurasi Klinik
        Route::middleware('permission:pengaturan.view')->group(function () {
            Route::get('/klinik',    fn() => view('pengaturan.klinik'))              ->name('klinik');
        });
    });
});
```

### Middleware EnsureUserIsActive

```php
// app/Http/Middleware/EnsureUserIsActive.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check() && !Auth::user()->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->with('error', 'Akun Anda telah dinonaktifkan. Hubungi administrator.');
        }

        return $next($request);
    }
}
```

Daftarkan di `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'active' => \App\Http\Middleware\EnsureUserIsActive::class,
    ]);
})
```

---

## 10. Blade Views

### 10.1 Halaman Index User Management

```blade
{{-- resources/views/pengaturan/user/index.blade.php --}}
<x-app-layout>
    <x-slot name="title">Manajemen Pengguna</x-slot>

    <div class="page-header">
        <div>
            <h2 class="page-title">Manajemen Pengguna</h2>
            <p class="page-subtitle">Kelola akun dan hak akses seluruh pengguna sistem</p>
        </div>
    </div>

    <x-alert />

    {{-- Komponen Livewire --}}
    <livewire:pengaturan.user.user-table />
    <livewire:pengaturan.user.user-form />
    <livewire:pengaturan.user.reset-password-modal />

</x-app-layout>
```

### 10.2 Tabel User (Livewire View)

```blade
{{-- resources/views/livewire/pengaturan/user/user-table.blade.php --}}
<div>
    {{-- Toolbar --}}
    <div class="mb-4 flex flex-col sm:flex-row gap-3 justify-between">
        <div class="flex gap-2 flex-wrap">
            {{-- Search --}}
            <div class="relative">
                <span class="absolute inset-y-0 left-3 flex items-center text-gray-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/>
                    </svg>
                </span>
                <input wire:model.live.debounce.400ms="search" type="text"
                       placeholder="Cari nama, email, NIP..."
                       class="form-input pl-9 w-64"/>
            </div>

            {{-- Filter Role --}}
            <select wire:model.live="filterRole" class="form-select w-40">
                <option value="">Semua Role</option>
                @foreach (['admin','dokter','perawat','apoteker','kasir','rekam_medis'] as $r)
                    <option value="{{ $r }}">{{ ucfirst(str_replace('_',' ',$r)) }}</option>
                @endforeach
            </select>

            {{-- Filter Status --}}
            <select wire:model.live="filterStatus" class="form-select w-36">
                <option value="">Semua Status</option>
                <option value="1">Aktif</option>
                <option value="0">Nonaktif</option>
            </select>
        </div>

        @can('user.create')
        <button wire:click="$dispatch('open-user-form')" class="btn-primary">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Tambah Pengguna
        </button>
        @endcan
    </div>

    {{-- Tabel --}}
    <div class="table-wrapper">
        <table class="table">
            <thead>
                <tr>
                    <th wire:click="sort('nama')" class="table-sortable">
                        Nama @if($sortBy==='nama') {{ $sortDir==='asc'?'↑':'↓' }} @endif
                    </th>
                    <th>Email / NIP</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Login Terakhir</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->users as $user)
                <tr wire:key="user-{{ $user->id }}">
                    <td>
                        <div class="flex items-center gap-3">
                            <div class="h-8 w-8 rounded-full bg-[#0a3d62] flex items-center justify-center text-white text-xs font-bold flex-shrink-0">
                                {{ strtoupper(substr($user->nama, 0, 1)) }}
                            </div>
                            <span class="font-medium text-gray-900 dark:text-gray-100">{{ $user->nama }}</span>
                        </div>
                    </td>
                    <td>
                        <p class="text-sm text-gray-700 dark:text-gray-300">{{ $user->email }}</p>
                        @if($user->nip)
                        <p class="text-xs text-gray-400 font-mono">{{ $user->nip }}</p>
                        @endif
                    </td>
                    <td>
                        <span class="badge-primary text-xs px-2 py-1 rounded-full">
                            {{ ucfirst(str_replace('_',' ', $user->roles->first()?->name ?? '-')) }}
                        </span>
                    </td>
                    <td>
                        @can('user.edit')
                        <button wire:click="toggleActive({{ $user->id }}, {{ $user->is_active ? 'false' : 'true' }})"
                                class="{{ $user->is_active ? 'badge-success' : 'badge-danger' }} cursor-pointer">
                            {{ $user->is_active ? 'Aktif' : 'Nonaktif' }}
                        </button>
                        @else
                        <x-badge-status :status="$user->is_active ? 'aktif' : 'nonaktif'" />
                        @endcan
                    </td>
                    <td class="text-xs text-gray-400">
                        {{ $user->last_login_at?->diffForHumans() ?? 'Belum pernah' }}
                    </td>
                    <td>
                        <div class="flex items-center gap-1">
                            @can('update', $user)
                            <button wire:click="$dispatch('open-user-edit', { userId: {{ $user->id }} })"
                                    class="btn-info btn-sm">Edit</button>
                            @endcan

                            @can('resetPassword', $user)
                            <button wire:click="$dispatch('open-reset-password', { userId: {{ $user->id }} })"
                                    class="btn-warning btn-sm">Reset PW</button>
                            @endcan

                            @can('delete', $user)
                            <button wire:click="deleteUser({{ $user->id }})"
                                    wire:confirm="Yakin hapus user {{ $user->nama }}?"
                                    class="btn-danger btn-sm">Hapus</button>
                            @endcan
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6">
                        <div class="empty-state">
                            <svg class="empty-state-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                      d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                            </svg>
                            <p class="empty-state-text">Tidak ada pengguna ditemukan</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    <div class="mt-4 flex items-center justify-between text-sm text-gray-500 dark:text-gray-400">
        <span>
            Menampilkan {{ $this->users->firstItem() ?? 0 }}–{{ $this->users->lastItem() ?? 0 }}
            dari {{ $this->users->total() }} pengguna
        </span>
        {{ $this->users->links() }}
    </div>
</div>
```

---

## 11. Seeder & Data Awal

### 11.1 RolePermissionSeeder

```php
// database/seeders/RolePermissionSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'pasien.view', 'pasien.create', 'pasien.edit', 'pasien.delete',
            'kunjungan.view', 'kunjungan.create', 'kunjungan.edit', 'kunjungan.delete',
            'asesmen.view', 'asesmen.create', 'asesmen.edit',
            'soap.view', 'soap.create', 'soap.edit',
            'resep.view', 'resep.create', 'resep.edit',
            'obat.view', 'obat.create', 'obat.edit', 'obat.delete',
            'tindakan.view', 'tindakan.create',
            'billing.view', 'billing.create', 'billing.edit',
            'pembayaran.view', 'pembayaran.create',
            'laporan.view', 'laporan.keuangan', 'laporan.farmasi',
            'rekammedis.view', 'rekammedis.create', 'rekammedis.edit',
            'user.view', 'user.create', 'user.edit', 'user.delete',
            'pengaturan.view', 'pengaturan.edit',
            'masterdata.view', 'masterdata.create', 'masterdata.edit', 'masterdata.delete',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm]);
        }

        $roleMap = [
            'super_admin' => [], // via Gate::before

            'admin' => [
                'pasien.view', 'pasien.create', 'pasien.edit', 'pasien.delete',
                'kunjungan.view', 'kunjungan.create', 'kunjungan.edit', 'kunjungan.delete',
                'laporan.view', 'laporan.keuangan',
                'pengaturan.view', 'pengaturan.edit',
            ],

            'dokter' => [
                'pasien.view', 'kunjungan.view', 'kunjungan.edit',
                'asesmen.view',
                'soap.view', 'soap.create', 'soap.edit',
                'resep.view', 'resep.create', 'resep.edit',
                'tindakan.view', 'tindakan.create',
                'obat.view', 'laporan.view',
            ],

            'perawat' => [
                'pasien.view', 'pasien.create', 'pasien.edit',
                'kunjungan.view', 'kunjungan.create', 'kunjungan.edit',
                'asesmen.view', 'asesmen.create', 'asesmen.edit',
                'soap.view', 'resep.view',
                'tindakan.view', 'tindakan.create',
            ],

            'apoteker' => [
                'pasien.view', 'soap.view',
                'resep.view', 'resep.edit',
                'obat.view', 'obat.create', 'obat.edit', 'obat.delete',
                'laporan.farmasi',
            ],

            'kasir' => [
                'pasien.view', 'kunjungan.view',
                'billing.view', 'billing.create', 'billing.edit',
                'pembayaran.view', 'pembayaran.create',
                'laporan.keuangan',
            ],

            'rekam_medis' => [
                'pasien.view', 'pasien.create', 'pasien.edit',
                'rekammedis.view', 'rekammedis.create', 'rekammedis.edit',
                'laporan.view',
            ],

            'pasien' => [
                'kunjungan.view', 'rekammedis.view', 'billing.view',
            ],
        ];

        foreach ($roleMap as $roleName => $perms) {
            $role = Role::firstOrCreate(['name' => $roleName]);
            $role->syncPermissions($perms);
        }
    }
}
```

### 11.2 UserSeeder

```php
// database/seeders/UserSeeder.php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $defaultPass = Hash::make('Admin@1234');

        $users = [
            ['nama' => 'Super Administrator', 'email' => 'superadmin@emr.app', 'nip' => 'SA001',  'role' => 'super_admin'],
            ['nama' => 'Admin Klinik',        'email' => 'admin@emr.app',      'nip' => 'ADM001', 'role' => 'admin'],
            ['nama' => 'dr. Ahmad Fauzi',     'email' => 'dokter@emr.app',     'nip' => 'DKT001', 'role' => 'dokter'],
            ['nama' => 'Ns. Siti Rahayu',     'email' => 'perawat@emr.app',    'nip' => 'PRW001', 'role' => 'perawat'],
            ['nama' => 'Apt. Budi Santoso',   'email' => 'apoteker@emr.app',   'nip' => 'APT001', 'role' => 'apoteker'],
            ['nama' => 'Kasir Dewi',          'email' => 'kasir@emr.app',      'nip' => 'KSR001', 'role' => 'kasir'],
            ['nama' => 'Staff Rekam Medis',   'email' => 'rekmedis@emr.app',   'nip' => 'RM001',  'role' => 'rekam_medis'],
        ];

        foreach ($users as $data) {
            $role = $data['role'];
            unset($data['role']);

            $user = User::firstOrCreate(
                ['email' => $data['email']],
                array_merge($data, ['password' => $defaultPass])
            );

            $user->syncRoles([$role]);
        }

        $this->command->info('✅ User seeded. Default password: Admin@1234');
        $this->command->warn('⚠️  Ganti password setelah login pertama!');
    }
}
```

---

## 12. Matrix Hak Akses per Role

| Modul | Super Admin | Admin | Dokter | Perawat | Apoteker | Kasir | Rekam Medis |
|-------|:-----------:|:-----:|:------:|:-------:|:--------:|:-----:|:-----------:|
| Dashboard | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Manajemen User | ✅ | 🔒 | 🔒 | 🔒 | 🔒 | 🔒 | 🔒 |
| Master Data | ✅ | 🔒 | 🔒 | 🔒 | 🔒 | 🔒 | 🔒 |
| Pendaftaran Pasien | ✅ | ✅ | 🔒 | ✏️ | 🔒 | 🔒 | ✏️ |
| Kunjungan & Antrean | ✅ | ✅ | 👁 | ✅ | 🔒 | 👁 | 🔒 |
| Asesmen Perawat | ✅ | 🔒 | 👁 | ✅ | 🔒 | 🔒 | 🔒 |
| SOAP Note | ✅ | 🔒 | ✅ | 👁 | 👁 | 🔒 | 🔒 |
| Resep Elektronik | ✅ | 🔒 | ✏️ | 👁 | ✅ | 🔒 | 🔒 |
| Stok Obat | ✅ | 🔒 | 👁 | 🔒 | ✅ | 🔒 | 🔒 |
| Rawat Inap | ✅ | 👁 | ✅ | ✅ | 🔒 | 🔒 | 🔒 |
| Billing & Kasir | ✅ | 👁 | 🔒 | 🔒 | 🔒 | ✅ | 🔒 |
| Laporan Keuangan | ✅ | 👁 | 🔒 | 🔒 | 🔒 | 👁 | 🔒 |
| Laporan Medis | ✅ | 🔒 | 🔑 | 🔒 | 🔒 | 🔒 | 👁 |
| Laporan Farmasi | ✅ | 🔒 | 🔒 | 🔒 | 👁 | 🔒 | 🔒 |
| Rekam Medis Arsip | ✅ | 🔒 | 🔒 | 🔒 | 🔒 | 🔒 | ✅ |
| Audit Log | ✅ | 🔒 | 🔒 | 🔒 | 🔒 | 🔒 | 🔒 |
| Pengaturan Sistem | ✅ | ✅ | 🔒 | 🔒 | 🔒 | 🔒 | 🔒 |

**Legenda:** ✅ Full CRUD · 👁 Read Only · ✏️ Create & Read · 🔑 Data sendiri · 🔒 Tidak ada akses

---

*masterdata_v1.md — v2.0.0 · Laravel 12 + Livewire 3 · Living document*

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'nama'     => 'Super Admin',
                'email'    => 'superadmin@emr.app',
                'password' => Hash::make('password'),
                'role'     => 'super_admin',
            ],
            [
                'nama'     => 'Admin Klinik',
                'email'    => 'admin@emr.app',
                'password' => Hash::make('password'),
                'role'     => 'admin',
            ],
            [
                'nama'     => 'dr. Budi Santoso',
                'email'    => 'dokter@emr.app',
                'password' => Hash::make('password'),
                'role'     => 'dokter',
            ],
            [
                'nama'     => 'Sari Perawat',
                'email'    => 'perawat@emr.app',
                'password' => Hash::make('password'),
                'role'     => 'perawat',
            ],
            [
                'nama'     => 'Apoteker Rina',
                'email'    => 'apoteker@emr.app',
                'password' => Hash::make('password'),
                'role'     => 'apoteker',
            ],
            [
                'nama'     => 'Kasir Dewi',
                'email'    => 'kasir@emr.app',
                'password' => Hash::make('password'),
                'role'     => 'kasir',
            ],
        ];

        foreach ($users as $data) {
            $role = $data['role'];
            unset($data['role']);

            $user = User::firstOrCreate(['email' => $data['email']], $data);
            $user->assignRole($role);
        }
    }
}

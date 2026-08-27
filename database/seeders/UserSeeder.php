<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['name' => 'System Admin', 'email' => 'admin@abaya.test', 'role' => 'Admin'],
            ['name' => 'Purchasing User', 'email' => 'purchasing@abaya.test', 'role' => 'Purchasing'],
            ['name' => 'Warehouse User', 'email' => 'warehouse@abaya.test', 'role' => 'Warehouse'],
            ['name' => 'Sales User', 'email' => 'sales@abaya.test', 'role' => 'Sales'],
            ['name' => 'Accountant User', 'email' => 'accountant@abaya.test', 'role' => 'Accountant'],
            ['name' => 'Production Supervisor', 'email' => 'production@abaya.test', 'role' => 'Production Supervisor'],
        ];

        foreach ($users as $data) {
            $role = $data['role'];
            unset($data['role']);

            $user = User::query()->updateOrCreate(
                ['email' => $data['email']],
                $data + [
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ],
            );

            $user->syncRoles([$role]);
        }
    }
}

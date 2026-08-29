<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AuthorizationManagementSeeder extends Seeder
{
    /** @var list<string> */
    public const MANAGEMENT_PERMISSIONS = [
        'users.view',
        'users.create',
        'users.edit',
        'users.status',
        'users.assign_roles',
        'users.assign_permissions',
        'roles.view',
        'roles.create',
        'roles.edit',
        'roles.delete',
        'permissions.view',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::MANAGEMENT_PERMISSIONS as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $superAdminRole = Role::findOrCreate('Super Admin', 'web');
        $superAdminRole->givePermissionTo(Permission::query()->where('guard_name', 'web')->get());

        $user = User::query()->firstOrCreate(
            ['email' => User::SUPER_ADMIN_EMAIL],
            [
                'name' => 'Ahmed Kuled',
                'password' => Hash::make(Str::random(64)),
                'email_verified_at' => now(),
                'is_active' => true,
            ],
        );

        $user->assignRole($superAdminRole);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}

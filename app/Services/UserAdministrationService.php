<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class UserAdministrationService
{
    /** @param array<string, mixed> $data */
    public function create(User $actor, array $data): User
    {
        return DB::transaction(function () use ($actor, $data): User {
            $rolesWereSubmitted = (bool) Arr::pull($data, 'roles_submitted', array_key_exists('role_ids', $data));
            $permissionsWereSubmitted = (bool) Arr::pull($data, 'permissions_submitted', array_key_exists('permission_ids', $data));
            $roles = Arr::pull($data, 'role_ids', []);
            $permissions = Arr::pull($data, 'permission_ids', []);

            if ($rolesWereSubmitted) {
                abort_unless($actor->can('users.assign_roles'), 403);
            }

            if ($permissionsWereSubmitted) {
                abort_unless($actor->can('users.assign_permissions'), 403);
            }

            $user = User::query()->create($data);
            if ($rolesWereSubmitted) {
                $user->syncRoles($roles);
            }
            if ($permissionsWereSubmitted) {
                $user->syncPermissions($permissions);
            }

            return $user;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(User $actor, User $target, array $data): User
    {
        return DB::transaction(function () use ($actor, $target, $data): User {
            $this->ensureTargetCanBeManaged($actor, $target);

            if ($target->email === User::SUPER_ADMIN_EMAIL && $data['email'] !== User::SUPER_ADMIN_EMAIL) {
                throw ValidationException::withMessages([
                    'email' => 'لا يمكن تغيير بريد حساب Super Admin المحمي.',
                ]);
            }

            $rolesWereSubmitted = (bool) Arr::pull($data, 'roles_submitted', array_key_exists('role_ids', $data));
            $permissionsWereSubmitted = (bool) Arr::pull($data, 'permissions_submitted', array_key_exists('permission_ids', $data));
            $roles = Arr::pull($data, 'role_ids', []);
            $permissions = Arr::pull($data, 'permission_ids', []);

            if ($rolesWereSubmitted) {
                abort_unless($actor->can('users.assign_roles'), 403);
            }

            if ($permissionsWereSubmitted) {
                abort_unless($actor->can('users.assign_permissions'), 403);
            }

            $this->ensureSuperAdminContinuity($target, (bool) $data['is_active'], $rolesWereSubmitted ? $roles : null);

            if (blank($data['password'] ?? null)) {
                unset($data['password']);
            }

            $target->update($data);

            if ($rolesWereSubmitted) {
                $target->syncRoles($roles);
            }

            if ($permissionsWereSubmitted) {
                $target->syncPermissions($permissions);
            }

            return $target->refresh();
        });
    }

    public function setActive(User $actor, User $target, bool $active): User
    {
        $this->ensureTargetCanBeManaged($actor, $target);
        $this->ensureSuperAdminContinuity($target, $active, null);
        $target->update(['is_active' => $active]);

        return $target->refresh();
    }

    public function ensureCanDeleteOwnAccount(User $user): void
    {
        if ($user->email === User::SUPER_ADMIN_EMAIL) {
            throw ValidationException::withMessages([
                'password' => 'لا يمكن حذف حساب Super Admin المحمي.',
            ]);
        }

        $this->ensureSuperAdminContinuity($user, false, null);
    }

    private function ensureTargetCanBeManaged(User $actor, User $target): void
    {
        if ($target->hasRole('Super Admin') && ! $actor->hasRole('Super Admin')) {
            abort(403, 'لا يمكن لمستخدم عادي إدارة حساب Super Admin.');
        }
    }

    /** @param list<int|string>|null $roleIds */
    private function ensureSuperAdminContinuity(User $target, bool $willBeActive, ?array $roleIds): void
    {
        if (! $target->hasRole('Super Admin')) {
            return;
        }

        $superAdminRoleId = Role::findByName('Super Admin', 'web')->getKey();
        $willKeepRole = $roleIds === null || in_array((string) $superAdminRoleId, array_map('strval', $roleIds), true);

        if ($target->email === User::SUPER_ADMIN_EMAIL && (! $willBeActive || ! $willKeepRole)) {
            throw ValidationException::withMessages([
                'role_ids' => 'لا يمكن تعطيل أو إزالة دور حساب Super Admin المحمي.',
            ]);
        }

        if ($willBeActive && $willKeepRole) {
            return;
        }

        $hasAnotherActiveSuperAdmin = User::query()
            ->whereKeyNot($target->getKey())
            ->where('is_active', true)
            ->role('Super Admin')
            ->exists();

        if (! $hasAnotherActiveSuperAdmin) {
            throw ValidationException::withMessages([
                'role_ids' => 'لا يمكن تعطيل أو إزالة صلاحية آخر Super Admin نشط.',
            ]);
        }
    }
}

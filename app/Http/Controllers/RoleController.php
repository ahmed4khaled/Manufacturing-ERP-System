<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Support\PermissionGroups;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(): View
    {
        $roles = Role::query()
            ->where('guard_name', 'web')
            ->withCount(['users', 'permissions'])
            ->orderBy('name')
            ->get();

        return view('roles.index', compact('roles'));
    }

    public function create(): View
    {
        return view('roles.create', $this->formData());
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $role = Role::create(['name' => $request->validated('name'), 'guard_name' => 'web']);
        $role->syncPermissions($request->validated('permission_ids', []));

        return redirect()->route('roles.edit', $role)->with('success', 'تم إنشاء الدور بنجاح.');
    }

    public function edit(Role $role): View
    {
        abort_unless($role->guard_name === 'web', 404);
        $role->load('permissions:id,name');

        return view('roles.edit', ['managedRole' => $role, ...$this->formData()]);
    }

    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        abort_unless($role->guard_name === 'web', 404);

        if ($role->name === 'Super Admin') {
            if ($request->validated('name') !== 'Super Admin') {
                throw ValidationException::withMessages(['name' => 'لا يمكن تغيير اسم دور Super Admin المحمي.']);
            }

            $role->givePermissionTo(Permission::query()->where('guard_name', 'web')->get());
        } else {
            $role->update(['name' => $request->validated('name')]);
            $role->syncPermissions($request->validated('permission_ids', []));
        }

        return redirect()->route('roles.edit', $role)->with('success', 'تم تحديث الدور بنجاح.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        abort_unless($role->guard_name === 'web', 404);

        if ($role->name === 'Super Admin') {
            throw ValidationException::withMessages(['role' => 'لا يمكن حذف دور Super Admin المحمي.']);
        }

        if ($role->users()->exists()) {
            throw ValidationException::withMessages(['role' => 'لا يمكن حذف دور مرتبط بمستخدمين. انقل المستخدمين أولًا.']);
        }

        $role->delete();

        return redirect()->route('roles.index')->with('success', 'تم حذف الدور غير المستخدم.');
    }

    /** @return array<string, mixed> */
    private function formData(): array
    {
        $permissions = Permission::query()->where('guard_name', 'web')->orderBy('name')->get();

        return ['permissionGroups' => PermissionGroups::group($permissions)];
    }
}

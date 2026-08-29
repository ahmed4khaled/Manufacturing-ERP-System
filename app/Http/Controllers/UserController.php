<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use App\Services\UserAdministrationService;
use App\Support\PermissionGroups;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $users = User::query()
            ->with(['roles:id,name', 'permissions:id,name'])
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = trim($request->string('search')->toString());
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('is_active', $request->string('status')->toString() === 'active'))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('users.index', compact('users'));
    }

    public function create(): View
    {
        return view('users.create', $this->formData());
    }

    public function store(StoreUserRequest $request, UserAdministrationService $service): RedirectResponse
    {
        $user = $service->create($request->user(), $request->validated());

        return redirect()->route('users.edit', $user)->with('success', 'تم إنشاء المستخدم بنجاح.');
    }

    public function edit(User $user): View
    {
        $user->load(['roles:id,name', 'permissions:id,name']);

        return view('users.edit', ['managedUser' => $user, ...$this->formData()]);
    }

    public function update(UpdateUserRequest $request, User $user, UserAdministrationService $service): RedirectResponse
    {
        $service->update($request->user(), $user, $request->validated());

        return redirect()->route('users.edit', $user)->with('success', 'تم تحديث المستخدم بنجاح.');
    }

    public function status(Request $request, User $user, UserAdministrationService $service): RedirectResponse
    {
        $validated = $request->validate(['is_active' => ['required', 'boolean']]);
        $service->setActive($request->user(), $user, (bool) $validated['is_active']);

        return back()->with('success', $validated['is_active'] ? 'تم تفعيل المستخدم.' : 'تم تعطيل المستخدم.');
    }

    /** @return array<string, mixed> */
    private function formData(): array
    {
        $permissions = Permission::query()->where('guard_name', 'web')->orderBy('name')->get();

        return [
            'roles' => Role::query()->where('guard_name', 'web')->orderBy('name')->get(),
            'permissionGroups' => PermissionGroups::group($permissions),
        ];
    }
}

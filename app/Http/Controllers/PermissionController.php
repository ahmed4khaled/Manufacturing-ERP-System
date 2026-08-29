<?php

namespace App\Http\Controllers;

use App\Support\PermissionGroups;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    public function index(): View
    {
        $permissions = Permission::query()
            ->where('guard_name', 'web')
            ->withCount('roles')
            ->orderBy('name')
            ->get();

        return view('permissions.index', [
            'permissionGroups' => PermissionGroups::group($permissions),
            'permissionCount' => $permissions->count(),
        ]);
    }
}

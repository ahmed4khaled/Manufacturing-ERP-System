<?php

use App\Models\User;
use Database\Seeders\AuthorizationManagementSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function seedAuthorizationManagement(): User
{
    test()->seed(PermissionSeeder::class);
    test()->seed(AuthorizationManagementSeeder::class);

    return User::query()->where('email', User::SUPER_ADMIN_EMAIL)->firstOrFail();
}

it('lets the super admin view every administration screen through the gate bypass', function () {
    $superAdmin = seedAuthorizationManagement();

    $this->actingAs($superAdmin)
        ->get(route('users.index'))->assertOk()->assertSee('المستخدمون');
    $this->get(route('roles.index'))->assertOk()->assertSee('الأدوار');
    $this->get(route('permissions.index'))->assertOk()->assertSee('الصلاحيات');

    expect($superAdmin->hasRole('Super Admin'))->toBeTrue()
        ->and($superAdmin->can('permission.created.after.seeding'))->toBeTrue();
});

it('lets the super admin create a user with hashed password role and direct permission', function () {
    $superAdmin = seedAuthorizationManagement();
    $role = Role::findOrCreate('Auditor', 'web');
    $permission = Permission::findOrCreate('statistics.view', 'web');

    $this->actingAs($superAdmin)->post(route('users.store'), [
        'name' => 'Audit User',
        'email' => 'audit@example.com',
        'password' => 'StrongPassword123!',
        'password_confirmation' => 'StrongPassword123!',
        'is_active' => 1,
        'role_ids' => [$role->id],
        'permission_ids' => [$permission->id],
    ])->assertRedirect();

    $user = User::query()->where('email', 'audit@example.com')->firstOrFail();
    expect(Hash::check('StrongPassword123!', $user->password))->toBeTrue()
        ->and($user->password)->not->toBe('StrongPassword123!')
        ->and($user->hasRole('Auditor'))->toBeTrue()
        ->and($user->hasDirectPermission('statistics.view'))->toBeTrue()
        ->and($user->is_active)->toBeTrue();
});

it('edits user details assignments and status without changing an omitted password', function () {
    $superAdmin = seedAuthorizationManagement();
    $user = User::factory()->create(['password' => 'OriginalPassword123!', 'is_active' => true]);
    $originalPassword = $user->password;
    $role = Role::findOrCreate('Editor', 'web');
    $permission = Permission::findOrCreate('customers.view', 'web');

    $this->actingAs($superAdmin)->put(route('users.update', $user), [
        'name' => 'Edited User',
        'email' => 'edited@example.com',
        'password' => '',
        'password_confirmation' => '',
        'is_active' => 1,
        'role_ids' => [$role->id],
        'permission_ids' => [$permission->id],
    ])->assertRedirect();

    expect($user->fresh()->name)->toBe('Edited User')
        ->and($user->fresh()->password)->toBe($originalPassword)
        ->and($user->fresh()->hasRole('Editor'))->toBeTrue()
        ->and($user->fresh()->hasDirectPermission('customers.view'))->toBeTrue();

    $this->patch(route('users.status', $user), ['is_active' => 0])->assertRedirect();
    expect($user->fresh()->is_active)->toBeFalse();
    $this->patch(route('users.status', $user), ['is_active' => 1])->assertRedirect();
    expect($user->fresh()->is_active)->toBeTrue();
});

it('validates duplicate email and password confirmation', function () {
    $superAdmin = seedAuthorizationManagement();
    User::factory()->create(['email' => 'existing@example.com']);

    $this->actingAs($superAdmin)->post(route('users.store'), [
        'name' => 'Duplicate',
        'email' => 'existing@example.com',
        'password' => 'StrongPassword123!',
        'password_confirmation' => 'different',
        'is_active' => 1,
    ])->assertSessionHasErrors(['email', 'password']);

    expect(User::query()->where('email', 'existing@example.com')->count())->toBe(1);
});

it('protects administration routes from normal users and direct urls', function () {
    seedAuthorizationManagement();
    $normalUser = User::factory()->create();
    $target = User::factory()->create();
    $role = Role::findOrCreate('Restricted', 'web');

    $this->actingAs($normalUser);
    $this->get(route('users.index'))->assertForbidden();
    $this->get(route('users.create'))->assertForbidden();
    $this->get(route('users.edit', $target))->assertForbidden();
    $this->patch(route('users.status', $target), ['is_active' => 0])->assertForbidden();
    $this->get(route('roles.index'))->assertForbidden();
    $this->get(route('roles.edit', $role))->assertForbidden();
    $this->get(route('permissions.index'))->assertForbidden();
});

it('does not let a permission manager modify a super admin', function () {
    $superAdmin = seedAuthorizationManagement();
    $manager = User::factory()->create();
    $manager->givePermissionTo(['users.edit', 'users.status']);

    $this->actingAs($manager)->put(route('users.update', $superAdmin), [
        'name' => $superAdmin->name,
        'email' => $superAdmin->email,
        'is_active' => 1,
    ])->assertForbidden();
    $this->patch(route('users.status', $superAdmin), ['is_active' => 0])->assertForbidden();

    expect($superAdmin->fresh()->is_active)->toBeTrue()->and($superAdmin->fresh()->hasRole('Super Admin'))->toBeTrue();
});

it('prevents removing or disabling the last active super admin', function () {
    $superAdmin = seedAuthorizationManagement();

    $this->actingAs($superAdmin)->put(route('users.update', $superAdmin), [
        'name' => $superAdmin->name,
        'email' => $superAdmin->email,
        'is_active' => 1,
        'role_ids' => [],
        'permission_ids' => [],
    ])->assertSessionHasErrors('role_ids');
    $this->patch(route('users.status', $superAdmin), ['is_active' => 0])->assertSessionHasErrors('role_ids');

    expect($superAdmin->fresh()->is_active)->toBeTrue()->and($superAdmin->fresh()->hasRole('Super Admin'))->toBeTrue();
});

it('allows safe role management but rejects deleting used and protected roles', function () {
    $superAdmin = seedAuthorizationManagement();
    $permission = Permission::findOrCreate('customers.view', 'web');

    $this->actingAs($superAdmin)->post(route('roles.store'), [
        'name' => 'Customer Viewer',
        'permission_ids' => [$permission->id],
    ])->assertRedirect();

    $role = Role::findByName('Customer Viewer');
    expect($role->hasPermissionTo('customers.view'))->toBeTrue();

    $this->put(route('roles.update', $role), ['name' => 'Customer Reader', 'permission_ids' => []])->assertRedirect();
    expect($role->refresh()->name)->toBe('Customer Reader')->and($role->permissions)->toBeEmpty();

    $assigned = User::factory()->create();
    $assigned->assignRole($role);
    $this->delete(route('roles.destroy', $role))->assertSessionHasErrors('role');
    $this->delete(route('roles.destroy', Role::findByName('Super Admin')))->assertSessionHasErrors('role');
});

it('keeps existing users roles permissions and passwords when the seeder is repeated', function () {
    $existingRole = Role::findOrCreate('Existing Role', 'web');
    $existingPermission = Permission::findOrCreate('existing.permission', 'web');
    $existingRole->givePermissionTo($existingPermission);
    $existingUser = User::factory()->create(['email' => 'kept@example.com', 'password' => 'KeepPassword123!']);
    $existingUser->assignRole($existingRole);
    $password = $existingUser->password;

    $this->seed(AuthorizationManagementSeeder::class);
    $this->seed(AuthorizationManagementSeeder::class);

    expect(User::query()->whereKey($existingUser)->exists())->toBeTrue()
        ->and($existingUser->fresh()->password)->toBe($password)
        ->and($existingUser->fresh()->hasRole('Existing Role'))->toBeTrue()
        ->and(Role::findByName('Existing Role')->hasPermissionTo('existing.permission'))->toBeTrue()
        ->and(Permission::query()->where('name', 'existing.permission')->exists())->toBeTrue()
        ->and(User::query()->where('email', User::SUPER_ADMIN_EMAIL)->count())->toBe(1);
});

it('protects the designated super admin email role status and self deletion', function () {
    $superAdmin = seedAuthorizationManagement();
    $superAdmin->update(['password' => 'KnownPassword123!']);
    $otherSuperAdmin = User::factory()->create();
    $otherSuperAdmin->assignRole('Super Admin');

    $this->actingAs($superAdmin)->put(route('users.update', $superAdmin), [
        'name' => $superAdmin->name,
        'email' => 'changed@example.com',
        'is_active' => 1,
        'role_ids' => $superAdmin->roles->pluck('id')->all(),
    ])->assertSessionHasErrors('email');

    $this->put(route('users.update', $superAdmin), [
        'name' => $superAdmin->name,
        'email' => $superAdmin->email,
        'is_active' => 1,
        'role_ids' => [],
    ])->assertSessionHasErrors('role_ids');

    $this->delete(route('profile.destroy'), ['password' => 'KnownPassword123!'])
        ->assertSessionHasErrors('password');

    expect($superAdmin->fresh()->email)->toBe(User::SUPER_ADMIN_EMAIL)
        ->and($superAdmin->fresh()->hasRole('Super Admin'))->toBeTrue()
        ->and($superAdmin->fresh()->is_active)->toBeTrue();
});

it('keeps existing erp permission resolution working across key modules', function () {
    seedAuthorizationManagement();
    $user = User::factory()->create();
    $permissions = ['product_variants.view', 'production_orders.view', 'inventory.view', 'sales_invoices.view'];
    $user->givePermissionTo($permissions);

    expect($user->can('product_variants.view'))->toBeTrue()
        ->and($user->can('production_orders.view'))->toBeTrue()
        ->and($user->can('inventory.view'))->toBeTrue()
        ->and($user->can('sales_invoices.view'))->toBeTrue();

    $this->actingAs($user)->get(route('inventory.cost-layers.index'))->assertOk();
    $this->get(route('production-labels.batch'))->assertOk();
});

it('rejects inactive logins and records successful active logins', function () {
    $inactive = User::factory()->create(['email' => 'inactive@example.com', 'password' => 'Password123!', 'is_active' => false]);

    $this->post(route('login'), ['email' => $inactive->email, 'password' => 'Password123!'])
        ->assertSessionHasErrors('email');
    $this->assertGuest();

    $active = User::factory()->create(['email' => 'active@example.com', 'password' => 'Password123!', 'is_active' => true]);
    $this->post(route('login'), ['email' => $active->email, 'password' => 'Password123!'])->assertRedirect(route('dashboard'));
    expect($active->fresh()->last_login_at)->not->toBeNull();
});
